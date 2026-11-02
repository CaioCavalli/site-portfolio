<?php

session_set_cookie_params([
  'httponly' => true,
  'secure'   => !empty($_SERVER['HTTPS']),
  'samesite' => 'Lax',
]);

session_start();

include("./includes/geo.php");
include("./includes/dbconn.php");

date_default_timezone_set('Europe/Rome');
$now = date('Y-m-d H:i:s');

// Contact form: CSRF token, spam checks, validation and Post/Redirect/Get
$form_limits = ['name' => 100, 'email' => 200, 'subject' => 150, 'message' => 5000];
$form_values = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
$form_errors = [];

if (empty($_SESSION['csrf_contact'])) {
  $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($form_values as $field => $value) {
    $raw = $_POST[$field] ?? '';
    $form_values[$field] = is_string($raw) ? trim($raw) : '';
  }

  $csrf_form = $_POST['csrf'] ?? '';
  $honeypot_form = $_POST['website'] ?? '';
  $form_started = $_SESSION['contact_form_time'] ?? 0;

  if (!is_string($csrf_form) || !hash_equals($_SESSION['csrf_contact'], $csrf_form)) {
    $form_errors[] = 'Your session has expired. Please try again.';
  } elseif ($honeypot_form !== '') {
    // Bots fill the hidden field: pretend it worked, save nothing
    header('Location: index.php?sent=1#contact');
    exit;
  } elseif (time() - $form_started < 3) {
    $form_errors[] = 'Please wait a few seconds and try again.';
  } else {
    if ($form_values['name'] === '') {
      $form_errors[] = 'Please enter your name.';
    }
    if (!filter_var($form_values['email'], FILTER_VALIDATE_EMAIL)) {
      $form_errors[] = 'Please enter a valid email address.';
    }
    if ($form_values['subject'] === '') {
      $form_errors[] = 'Please enter a subject.';
    }
    if ($form_values['message'] === '') {
      $form_errors[] = 'Please enter a message.';
    }
    foreach ($form_limits as $field => $limit) {
      if (mb_strlen($form_values[$field]) > $limit) {
        $form_errors[] = 'The ' . $field . ' field is too long (max ' . $limit . ' characters).';
      }
    }
  }

  if (!$form_errors) {
    try {
      $sql_recent = "SELECT COUNT(*) FROM form WHERE ip = :ip AND dia > :since";
      $stmt = $conn->prepare($sql_recent);
      $stmt->execute([':ip' => $ip, ':since' => date('Y-m-d H:i:s', time() - 600)]);

      if ((int)$stmt->fetchColumn() >= 3) {
        $form_errors[] = 'You have sent several messages recently. Please try again later.';
      } else {
        $sql_form = "INSERT INTO form (name, email, subject, message, ip, geo, dia)
                     VALUES (:name, :email, :subject, :message, :ip, :geo, :dia)";
        $stmt = $conn->prepare($sql_form);
        $saved = $stmt->execute([
          ':name' => $form_values['name'],
          ':email' => $form_values['email'],
          ':subject' => $form_values['subject'],
          ':message' => $form_values['message'],
          ':ip' => $ip,
          ':geo' => $geo,
          ':dia' => $now,
        ]);

        if (!$saved) {
          throw new RuntimeException('Contact form insert failed');
        }

        $_SESSION['contact_sent_name'] = $form_values['name'];
        header('Location: index.php?sent=1#contact');
        exit;
      }
    } catch (Throwable $e) {
      error_log('Contact form error: ' . $e->getMessage());
      $form_errors[] = 'Sorry, something went wrong. Please try again later.';
    }
  }
} else {
  $_SESSION['contact_form_time'] = time();
}

$mensagem_conclusao = isset($_GET['sent']);
$name_form = $_SESSION['contact_sent_name'] ?? '';
unset($_SESSION['contact_sent_name']);

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Caio Cavalli</title>

  <?php

  $sql_log = "INSERT INTO log (ip, geo, dia) 
            VALUES (:ip, :geo, :dia)";
  $stmt = $conn->prepare($sql_log);
  $stmt->execute([':ip' => $ip, ':geo' => $geo, ':dia' => $now]);


  $id_header = 1;

  $sql_header = "SELECT title, description, keywords, author 
        FROM header 
        WHERE id = :id";

  $stmt = $conn->prepare($sql_header);
  $stmt->execute([':id' => $id_header]);

  $dados_header = $stmt->fetch();

  $title = $dados_header['title'] ?? '';
  $description = $dados_header['description'] ?? '';
  $keywords = $dados_header['keywords'] ?? '';
  $author = $dados_header['author'] ?? '';

  ?>

  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $title; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="<?= $description; ?>" />
    <meta name="keywords" content="<?= $keywords; ?>" />
    <meta name="author" content="<?= $author; ?>" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Caio Cavalli" />
    <meta property="og:description" content="PHP Developer - Data Analyst" />
    <meta property="og:url" content="https://www.caiocavalli.com/" />
    <meta property="og:site_name" content="Caio Cavalli" />
    <meta property="og:image" content="https://www.caiocavalli.com/img/og/og-default-300x300.jpg" />
    <meta property="og:image:secure_url" content="https://www.caiocavalli.com/img/og/og-default-300x300.jpg" />

    <link rel="shortcut icon" href="favicon.ico">
    <link rel="stylesheet" href="css/reset.css" type="text/css">
    <link rel="stylesheet" href="css/bootstrap-grid.min.css" type="text/css">
    <link rel="stylesheet" href="css/animations.css" type="text/css">
    <link rel="stylesheet" href="css/perfect-scrollbar.css" type="text/css">
    <link rel="stylesheet" href="css/owl.carousel.css" type="text/css">
    <link rel="stylesheet" href="css/magnific-popup.css" type="text/css">
    <link rel="stylesheet" href="css/main.css" type="text/css">

    <script src="js/modernizr.custom.js"></script>

    <style>
      ::selection {
        background-color: #9b5750;
        /* Cor de fundo */
        color: #ffffff;
        /* Cor do texto */
      }

      .contact-form .alert-danger {
        border-left: 3px solid #9b5750;
        background-color: #f9f1f0;
        color: #9b5750;
        padding: 10px 15px;
        margin-bottom: 25px;
      }

      .contact-form .alert-danger p {
        margin: 0;
      }
    </style>

  </head>


<body>
  <!-- Animated Background -->
  <div class="lm-animated-bg" style="background-image: url(img/main_bg.png);"></div>
  <!-- /Animated Background -->

  <!-- Loading animation -->
  <div class="preloader">
    <div class="preloader-animation">
      <div class="preloader-spinner">
      </div>
    </div>
  </div>
  <!-- /Loading animation -->

  <div class="page">
    <div class="page-content">

      <header id="site_header" class="header mobile-menu-hide">
        <div class="header-content">
          <div class="header-photo">
            <img src="img/main_photo_caio.jpg" alt="Caio">
          </div>
          <div class="header-titles">
            <h2>Caio Tolio Cavalli</h2>
            <h4>Data Analyst</h4>
          </div>
        </div>

        <ul class="main-menu">
          <li class="active">
            <a href="#home" class="nav-anim">
              <span class="menu-icon lnr lnr-home"></span>
              <span class="link-text">Home</span>
            </a>
          </li>
          <li>
            <a href="#about-me" class="nav-anim">
              <span class="menu-icon lnr lnr-user"></span>
              <span class="link-text">About Me</span>
            </a>
          </li>
          <li>
            <a href="#resume" class="nav-anim">
              <span class="menu-icon lnr lnr-graduation-hat"></span>
              <span class="link-text">Resume</span>
            </a>
          </li>
          <!-- <li>
            <a href="#portfolio" class="nav-anim">
              <span class="menu-icon lnr lnr-briefcase"></span>
              <span class="link-text">Portfolio</span>
            </a>
          </li> -->
          <li>
            <a href="#contact" class="nav-anim">
              <span class="menu-icon lnr lnr-envelope"></span>
              <span class="link-text">Contact</span>
            </a>
          </li>
        </ul>

        <div class="social-links">
          <ul>
            <li>
              <a href="https://wa.me/393513985208"
                target="_blank" rel="noopener"
                aria-label="Abrir WhatsApp para +39 351 398 5208">
                <i class="fab fa-whatsapp" aria-hidden="true"></i>
              </a>
            </li>
            <li>
              <a href="https://www.linkedin.com/in/caiotoliocavalli/"
                target="_blank" rel="noopener"
                aria-label="Caio Tolio Cavalli on LinkedIn">
                <i class="fab fa-linkedin" aria-hidden="true"></i>
              </a>
            </li>
          </ul>
        </div>

        <div class="header-buttons">
          <a href="download/CV%20-%20Data%20Analyst%20-%20Caio%20Cavalli.pdf" class="btn btn-primary" download="CV-Caio-Cavalli.pdf">Download CV</a>
        </div>

        <div class="copyrights">© 2025 All rights reserved.</div>
      </header>

      <!-- Mobile Navigation -->
      <div class="menu-toggle">
        <span></span>
        <span></span>
        <span></span>
      </div>
      <!-- End Mobile Navigation -->

      <!-- Arrows Nav -->
      <div class="lmpixels-arrows-nav">
        <div class="lmpixels-arrow-right"><i class="lnr lnr-chevron-right"></i></div>
        <div class="lmpixels-arrow-left"><i class="lnr lnr-chevron-left"></i></div>
      </div>
      <!-- End Arrows Nav -->

      <div class="content-area">
        <div class="animated-sections">

          <!-- Home Subpage -->
          <section data-id="home" class="animated-section start-page">
            <div class="section-content vcentered">

              <div class="row">
                <div class="col-sm-12 col-md-12 col-lg-12">
                  <div class="title-block">
                    <h2>Caio Tolio Cavalli</h2>
                    <div class="owl-carousel text-rotation">

                      <div class="item">
                        <div class="sp-subtitle">Welcome</div>
                      </div>

                      <div class="item">
                        <div class="sp-subtitle">Data Analyst</div>
                      </div>

                      <div class="item">
                        <div class="sp-subtitle">BI Specialist</div>
                      </div>

                      <div class="item">
                        <div class="sp-subtitle">Python Developer</div>
                      </div>

                      <div class="item">
                        <div class="sp-subtitle">Storyteller</div>
                      </div>

                    </div>
                  </div>
                </div>
              </div>

            </div>
          </section>
          <!-- End of Home Subpage -->

          <?php

          $id_about = 1;

          $sql_about = "SELECT * 
                  FROM about 
                  WHERE id = :id";

          $stmt = $conn->prepare($sql_about);
          $stmt->execute([':id' => $id_about]);

          $dados_about = $stmt->fetch();

          $main_title_1_about = $dados_about['main_title_1'] ?? '';
          $main_title_2_about = $dados_about['main_title_2'] ?? '';
          $main_title_3_about = $dados_about['main_title_3'] ?? '';
          $title_1_about = $dados_about['title_1'] ?? '';
          $title_2_about = $dados_about['title_2'] ?? '';
          $title_3_about = $dados_about['title_3'] ?? '';
          $title_4_about = $dados_about['title_4'] ?? '';
          $main_text_about = $dados_about['main_text'] ?? '';
          $text_1_about = $dados_about['text_1'] ?? '';
          $text_2_about = $dados_about['text_2'] ?? '';
          $text_3_about = $dados_about['text_3'] ?? '';
          $text_4_about = $dados_about['text_4'] ?? '';
          $title_age_about = $dados_about['title_age'] ?? '';
          $age_about = $dados_about['age'] ?? '';
          $title_residence_about = $dados_about['title_residence'] ?? '';
          $residence_about = $dados_about['residence'] ?? '';
          $title_email_about = $dados_about['title_email'] ?? '';
          $email_about = $dados_about['email'] ?? '';
          $title_phone_about = $dados_about['title_phone'] ?? '';
          $phone_about = $dados_about['phone'] ?? '';

          ?>

          <!-- About Me Subpage -->
          <section data-id="about-me" class="animated-section">
            <div class="page-title">
              <h2><?= $main_title_1_about; ?></h2>
            </div>

            <div class="section-content">
              <!-- Personal Information -->

              <div class="row">
                <div class="col-xs-12 col-sm-7">
                  <p><?= $main_text_about; ?></p>
                </div>

                <div class="col-xs-12 col-sm-5">
                  <div class="info-list">
                    <ul>
                      <li>
                        <span class="title"><?= $title_age_about; ?></span>
                        <span class="value"><?= $age_about; ?></span>
                      </li>

                      <li>
                        <span class="title"><?= $title_residence_about; ?></span>
                        <span class="value"><?= $residence_about; ?></span>
                      </li>

                      <li>
                        <span class="title"><?= $title_email_about; ?></span>
                        <span class="value"><a href=""><?= $email_about; ?></a></span>
                      </li>

                      <li>
                        <span class="title"><?= $title_phone_about; ?></span>
                        <span class="value"><?= $phone_about; ?></span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <!-- End of Personal Information -->

              <div class="white-space-50"></div>

              <!-- Services -->
              <div class="row">
                <div class="col-xs-12 col-sm-12">
                  <div class="block-title">
                    <h3><?= $main_title_2_about; ?></h3>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-xs-12 col-sm-6">
                  <div class="col-inner">
                    <div class="info-list-w-icon">
                      <div class="info-block-w-icon">
                        <div class="ci-icon">
                          <i class="lnr lnr-chart-bars"></i>
                        </div>
                        <div class="ci-text">
                          <h4><?= $title_1_about; ?></h4>
                          <p><?= $text_1_about; ?></p>
                        </div>
                      </div>
                      <div class="info-block-w-icon">
                        <div class="ci-icon">
                          <i class="lnr lnr-laptop"></i>
                        </div>
                        <div class="ci-text">
                          <h4><?= $title_2_about; ?></h4>
                          <p><?= $text_2_about; ?></p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-xs-12 col-sm-6">
                  <div class="col-inner">
                    <div class="info-list-w-icon">
                      <div class="info-block-w-icon">
                        <div class="ci-icon">
                          <i class="lnr lnr-pencil"></i>
                        </div>
                        <div class="ci-text">
                          <h4><?= $title_3_about; ?></h4>
                          <p><?= $text_3_about; ?></p>
                        </div>
                      </div>
                      <div class="info-block-w-icon">
                        <div class="ci-icon">
                          <i class="lnr lnr-earth"></i>
                        </div>
                        <div class="ci-text">
                          <h4><?= $title_4_about; ?></h4>
                          <p><?= $text_4_about; ?></p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- End of Services -->

              <div class="white-space-50"></div>

              <!-- Fun Facts -->
              <div class="row">
                <div class="col-xs-12 col-sm-12">

                  <div class="block-title">
                    <h3><?= $main_title_3_about; ?></h3>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-xs-12 col-sm-4">
                  <div class="fun-fact gray-default">
                    <i class="lnr lnr-apartment"></i>
                    <h4>Architect and Urban Planner</h4>
                    <span class="fun-fact-block-text"></span>
                  </div>
                </div>

                <div class="col-xs-12 col-sm-4">
                  <div class="fun-fact gray-default">
                    <i class="lnr lnr-music-note"></i>
                    <h4>Musician</h4>
                    <span class="fun-fact-block-text"></span>
                  </div>
                </div>

                <div class="col-xs-12 col-sm-4 ">
                  <div class="fun-fact gray-default">
                    <i class="lnr lnr-magic-wand"></i>
                    <h4>Songwriter</h4>
                    <span class="fun-fact-block-text"></span>
                  </div>
                </div>
              </div>
              <!-- End of Fun Facts -->
            </div>
          </section>
          <!-- End of About Me Subpage -->

          <?php

          $language_resume = 1;

          $sql_resume_title = "SELECT * 
                  FROM resume 
                  WHERE language = :language AND categoria = 'Education'
                  ORDER BY data_ref DESC;";

          $stmt = $conn->prepare($sql_resume_title);
          $stmt->execute([':language' => $language_resume]);

          $dados_resume = $stmt->fetch();

          $main_title_1_resume = $dados_resume['main_title_1'] ?? '';
          $categoria_resume_1 = $dados_resume['categoria'] ?? '';

          ?>

          <!-- Resume Subpage -->
          <section data-id="resume" class="animated-section">
            <div class="page-title">
              <h2><?= $main_title_1_resume; ?></h2>
            </div>

            <div class="section-content">

              <div class="row">
                <div class="col-xs-12 col-sm-7">

                  <div class="block-title">
                    <h3><?= $categoria_resume_1; ?></h3>
                  </div>

                  <?php

                  $sql_resume = "SELECT * 
                          FROM resume 
                          WHERE language = :language AND categoria = 'Education'
                          ORDER BY data_ref DESC;";

                  $stmt = $conn->prepare($sql_resume);
                  $stmt->execute([':language' => $language_resume]);

                  ?>

                  <div class="timeline timeline-second-style clearfix">
                    <?php

                    while ($linha = $stmt->fetch(PDO::FETCH_ASSOC)) {

                    ?>
                      <div class="timeline-item clearfix">
                        <div class="left-part">
                          <h5 class="item-period"><?= $linha['data_ref'] ?></h5>
                          <span class="item-company"><?= $linha['local_ref'] ?></span>
                        </div>
                        <div class="divider"></div>
                        <div class="right-part">
                          <h4 class="item-title"><?= $linha['title_1'] ?></h4>
                          <p><?= $linha['text_1'] ?></p>
                        </div>
                      </div>
                    <?php }
                    ?>
                  </div>

                  <div class="white-space-50"></div>

                  <?php
                  $sql_resume_title = "SELECT * 
                  FROM resume 
                  WHERE language = :language AND categoria = 'Experience'
                  ORDER BY data_ref DESC;";

                  $stmt = $conn->prepare($sql_resume_title);
                  $stmt->execute([':language' => $language_resume]);

                  $dados_resume = $stmt->fetch();

                  $categoria_resume_2 = $dados_resume['categoria'] ?? '';

                  ?>

                  <div class="block-title">
                    <h3><?= $categoria_resume_2; ?></h3>
                  </div>

                  <?php

                  $sql_resume = "SELECT * 
                      FROM resume 
                      WHERE language = :language AND categoria = 'Experience'
                      ORDER BY data_ref DESC;";

                  $stmt = $conn->prepare($sql_resume);
                  $stmt->execute([':language' => $language_resume]);
                  ?>

                  <div class="timeline timeline-second-style clearfix">
                    <?php

                    while ($linha = $stmt->fetch(PDO::FETCH_ASSOC)) {

                    ?>

                      <div class="timeline-item clearfix">
                        <div class="left-part">
                          <h5 class="item-period"><?= $linha['data_ref'] ?></h5>
                          <span class="item-company"><?= $linha['local_ref'] ?></span>
                        </div>
                        <div class="divider"></div>
                        <div class="right-part">
                          <h4 class="item-title"><?= $linha['title_1'] ?></h4>
                          <p><?= $linha['text_1'] ?></p>
                        </div>
                      </div>
                    <?php }
                    ?>
                  </div>
                </div>

                <!-- Skills & Certificates -->
                <div class="col-xs-12 col-sm-5">
                  <!-- Data Skills -->
                  <div class="block-title">
                    <h3>Data <span>Skills</span></h3>
                  </div>

                  <div class="skills-info skills-second-style">

                    <!-- Skill 8 -->
                    <div class="skill clearfix">
                      <h4>Power BI - Tableau - Looker</h4>
                      <div class="skill-value">75%</div>
                    </div>
                    <div class="skill-container skill-8">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 8 -->

                    <!-- Skill 14 -->
                    <div class="skill clearfix">
                      <h4>ETL & ELT</h4>
                      <div class="skill-value">75%</div>
                    </div>
                    <div class="skill-container skill-14">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 14 -->

                    <!-- Skill 15 -->
                    <div class="skill clearfix">
                      <h4>KPI (Key Performance Indicator)</h4>
                      <div class="skill-value">75%</div>
                    </div>
                    <div class="skill-container skill-15">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 15 -->

                    <!-- Skill 16 -->
                    <div class="skill clearfix">
                      <h4>Data Lake & Data Warehouse</h4>
                      <div class="skill-value">65%</div>
                    </div>
                    <div class="skill-container skill-16">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 16 -->

                    <!-- Skill 6 -->
                    <div class="skill clearfix">
                      <h4>Excel - Sheets</h4>
                      <div class="skill-value">90%</div>
                    </div>
                    <div class="skill-container skill-6">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 6 -->

                    <!-- Skill 7 -->
                    <div class="skill clearfix">
                      <h4>Data Visualization & Storytelling</h4>
                      <div class="skill-value">90%</div>
                    </div>
                    <div class="skill-container skill-7">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 7 -->

                    <!-- Skill 9 -->
                    <div class="skill clearfix">
                      <h4>Business Analytics</h4>
                      <div class="skill-value">75%</div>
                    </div>
                    <div class="skill-container skill-9">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 9 -->
                  </div>
                  <!-- End of Data Skills -->

                  <div class="white-space-10"></div>

                  <!-- Dev Skills -->
                  <div class="block-title">
                    <h3>DEV <span>Skills</span></h3>
                  </div>

                  <div class="skills-info skills-second-style">

                    <!-- Skill 11 -->
                    <div class="skill clearfix">
                      <h4>Python</h4>
                      <div class="skill-value">65%</div>
                    </div>
                    <div class="skill-container skill-11">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 11 -->

                    <!-- Skill 12 -->
                    <div class="skill clearfix">
                      <h4>SQL</h4>
                      <div class="skill-value">80%</div>
                    </div>
                    <div class="skill-container skill-12">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 12 -->

                    <!-- Skill 1 -->
                    <div class="skill clearfix">
                      <h4>PHP (Object-Oriented - OOP)</h4>
                      <div class="skill-value">75%</div>
                    </div>
                    <div class="skill-container skill-1">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 1 -->

                    <!-- Skill 5 -->
                    <div class="skill clearfix">
                      <h4>Database (MySQL, SQL)</h4>
                      <div class="skill-value">85%</div>
                    </div>
                    <div class="skill-container skill-5">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 5 -->

                    <!-- Skill 13 -->
                    <div class="skill clearfix">
                      <h4>Git & GitHub</h4>
                      <div class="skill-value">80%</div>
                    </div>
                    <div class="skill-container skill-13">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 13 -->

                    <!-- Skill 2 -->
                    <div class="skill clearfix">
                      <h4>Data Structure</h4>
                      <div class="skill-value">70%</div>
                    </div>
                    <div class="skill-container skill-2">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 2 -->

                    <!-- Skill 3 -->
                    <div class="skill clearfix">
                      <h4>CSS (Bootstrap)</h4>
                      <div class="skill-value">70%</div>
                    </div>
                    <div class="skill-container skill-3">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 3 -->

                    <!-- Skill 10 -->
                    <div class="skill clearfix">
                      <h4>JavaScript (jQuery)</h4>
                      <div class="skill-value">70%</div>
                    </div>
                    <div class="skill-container skill-10">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 10 -->

                    <!-- Skill 4 -->
                    <div class="skill clearfix">
                      <h4>APIs Web (REST, SOAP)</h4>
                      <div class="skill-value">65%</div>
                    </div>
                    <div class="skill-container skill-4">
                      <div class="skill-percentage"></div>
                    </div>
                    <!-- End of Skill 4 -->

                  </div>
                  <!-- End of Dev Skills -->

                  <div class="white-space-10"></div>

                  <!-- Knowledges -->
                  <div class="block-title">
                    <h3>Knowledge</h3>
                  </div>

                  <ul class="knowledges">
                    <li>Data Analyst</li>
                    <li>Problem Solving</li>
                    <li>SQL</li>
                    <li>Python</li>
                    <li>ETL & ELT</li>
                    <li>Data Cleaning</li>
                    <li>Storytelling</li>
                    <li>Dashboards</li>
                    <li>Power BI</li>
                    <li>Tableau</li>
                    <li>Data Visualization</li>
                    <li>Excel</li>
                    <li>Google Sheets</li>
                    <li>KPI - Key Performance Indicator</li>
                    <li>Data Lake & Data Warehouse</li>
                    <li>BIG Data</li>
                    <li>Business Analytics</li>
                    <li>Data Quality</li>
                    <li>Data Management</li>
                    <li>Database</li>
                    <li>Object-Oriented PHP (OOP)</li>
                    <li>PHP</li>
                    <li>MySQL</li>
                    <li>Bootstrap</li>
                    <li>jQuery</li>
                    <li>CRUD</li>
                    <li>Git & GitHub</li>
                    <li>Creative Collaboration</li>
                    <li>Time-Management</li>
                    <li>Proactive Prevention of Problems</li>
                  </ul>
                  <!-- End of Knowledges -->
                </div>
                <!-- End of Skills & Certificates -->
              </div>
            </div>
          </section>
          <!-- End of Resume Subpage -->

          <!-- Portfolio Subpage -->
          <section data-id="portfolio" class="animated-section">
            <div class="page-title">
              <h2>Portfolio</h2>
            </div>

            <div class="section-content">

              <div class="row">
                <div class="col-xs-12 col-sm-12">
                  <!-- Portfolio Content -->
                  <div class="portfolio-content">

                    <ul class="portfolio-filters">
                      <li class="active">
                        <a class="filter btn btn-sm btn-link" data-group="category_all">Projects</a>
                      </li>
                    </ul>

                    <!-- Portfolio Grid -->
                    <div class="portfolio-grid three-columns">

                      <figure class="item standard" data-groups='["category_all", "category_detailed"]'>
                        <div class="portfolio-item-img">
                          <img src="img/portfolio/Smart Step Flooring - Nida Digital/Smart Step Flooring - Nida Digital 01.jpg" alt="Media Project 2" title="" />
                          <a href="portfolio-1en.html"
                            class="ajax-page-load"></a>
                        </div>

                        <i class="far fa-file-alt"></i>
                        <h4 class="name">Smart Step Flooring</h4>
                        <span class="category">Detailed</span>
                      </figure>

                      <figure class="item standard" data-groups='["category_all", "category_detailed"]'>
                        <div class="portfolio-item-img">
                          <img src="img/portfolio/Evolution Cleaning Company - Nida Digital/Evolution Cleaning Company - Nida Digital 01.jpg" alt="Media Project 2" title="" />
                          <a href="portfolio-2en.html" class="ajax-page-load"></a>
                        </div>

                        <i class="far fa-file-alt"></i>
                        <h4 class="name">Evolution Cleaning Company</h4>
                        <span class="category">Detailed</span>
                      </figure>

                      <figure class="item standard" data-groups='["category_all", "category_detailed"]'>
                        <div class="portfolio-item-img">
                          <img src="img/portfolio/APRAFUR - Nida Digital/APRAFUR - Nida Digital 01.jpg" alt="Media Project 2" title="" />
                          <a href="portfolio-3en.html" class="ajax-page-load"></a>
                        </div>

                        <i class="far fa-file-alt"></i>
                        <h4 class="name">APRAFUR Associação Praia das Furnas</h4>
                        <span class="category">Detailed</span>
                      </figure>

                      <figure class="item standard" data-groups='["category_all", "category_detailed"]'>
                        <div class="portfolio-item-img">
                          <img src="img/portfolio/Caio Cavalli Portfolio/Caio Cavalli 01.jpg" alt="Media Project 2" title="" />
                          <a href="portfolio-4en.html" class="ajax-page-load"></a>
                        </div>

                        <i class="far fa-file-alt"></i>
                        <h4 class="name">Caio Tolio Cavalli - PHP Developer - Data Analyst</h4>
                        <span class="category">Detailed</span>
                      </figure>

                      <figure class="item standard" data-groups='["category_all", "category_detailed"]'>
                        <div class="portfolio-item-img">
                          <img src="img/portfolio/Napkinn/Napkinn 06.png" alt="Media Project 2" title="" />
                          <a href="portfolio-5en.html" class="ajax-page-load"></a>
                        </div>

                        <i class="far fa-file-alt"></i>
                        <h4 class="name">Gestão de Saúde Corporativa</h4>
                        <span class="category">Detailed</span>
                      </figure>

                      <figure class="item standard" data-groups='["category_all", "category_detailed"]'>
                        <div class="portfolio-item-img">
                          <img src="img/portfolio/Dental Uni/Dental Uni 06.png" alt="Media Project 2" title="" />
                          <a href="portfolio-6en.html" class="ajax-page-load"></a>
                        </div>

                        <i class="far fa-file-alt"></i>
                        <h4 class="name">Planos Odontológicos Paraná</h4>
                        <span class="category">Detailed</span>
                      </figure>

                    </div>
                  </div>
                  <!-- End of Portfolio Content -->
                </div>
              </div>
            </div>
          </section>
          <!-- End of Portfolio Subpage -->

          <!-- Contact Subpage -->
          <section data-id="contact" class="animated-section">
            <div class="page-title">
              <h2>Contact</h2>
            </div>

            <div class="section-content">

              <div class="row">
                <!-- Contact Info -->
                <div class="col-xs-12 col-sm-4">

                  <!-- MAPA -->
                  <div class="lm-info-block gray-default lm-as-link">
                    <a href="https://maps.app.goo.gl/qyFB5NpYEU2JuKVFA"
                      target="_blank" rel="noopener"
                      class="lm-stretched-link"
                      aria-label="Abrir Google Maps: Milan"></a>
                    <i class="lnr lnr-map-marker" aria-hidden="true"></i>
                    <h4>Milan</h4>
                    <span class="lm-info-block-value"></span>
                    <span class="lm-info-block-text"></span>
                  </div>

                  <!-- TELEFONE -->
                  <div class="lm-info-block gray-default lm-as-link">
                    <a href="tel:+393513985208"
                      class="lm-stretched-link"
                      aria-label="Ligar para +39 351 398 5208"></a>
                    <i class="lnr lnr-phone-handset" aria-hidden="true"></i>
                    <h4>+39 351 398 5208</h4>
                    <span class="lm-info-block-value"></span>
                    <span class="lm-info-block-text"></span>
                  </div>

                  <!-- E-MAIL -->
                  <div class="lm-info-block gray-default lm-as-link">
                    <a href="mailto:caiotoliocavalli@gmail.com"
                      class="lm-stretched-link"
                      aria-label="Enviar e-mail para caiotoliocavalli@gmail.com"></a>
                    <i class="lnr lnr-envelope" aria-hidden="true"></i>
                    <h4>[caiotoliocavalli@gmail.com]</h4>
                    <span class="lm-info-block-value"></span>
                    <span class="lm-info-block-text"></span>
                  </div>

                  <!-- STATUS -->
                  <div class="lm-info-block gray-default">
                    <i class="lnr lnr-checkmark-circle"></i>
                    <h4>Available to Work</h4>
                    <span class="lm-info-block-value"></span>
                    <span class="lm-info-block-text"></span>
                  </div>

                </div>
                <!-- End of Contact Info -->

                <!-- Contact Form -->
                <div class="col-xs-12 col-sm-8">
                  <div id="map" class="map">
                    <div class="lmpixels-map">
                      <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d44758.72545303235!2d9.17142260640503!3d45.48147958292689!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4786c1493f1275e7%3A0x3cffcd13c6740e8d!2zTWlsw6Nv!5e0!3m2!1sit_IT!2sit!4v1757006429045!5m2!1sit_IT!2sit" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>

                    <?php

                    if (!$mensagem_conclusao):

                    ?>
                      <div class="block-title">
                        <h3>How Can I <span>Help You?</span></h3>
                      </div>

                      <form class="contact-form" action="index.php#contact" method="post">
                        <div class="messages">
                          <?php if ($form_errors): ?>
                            <div class="alert alert-danger">
                              <?php foreach ($form_errors as $form_error): ?>
                                <p><?= htmlspecialchars($form_error, ENT_QUOTES, 'UTF-8') ?></p>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                        </div>

                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_contact'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Honeypot: hidden from people, filled in by bots -->
                        <div style="position:absolute; left:-9999px;" aria-hidden="true">
                          <label for="form_website">Website</label>
                          <input id="form_website" type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="controls two-columns">
                          <div class="fields clearfix">
                            <div class="left-column">
                              <div class="form-group form-group-with-icon">
                                <input id="form_name" type="text" name="name" class="form-control" maxlength="<?= $form_limits['name'] ?>" value="<?= htmlspecialchars($form_values['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                                <label>Full Name</label>
                                <div class="form-control-border"></div>
                                <div class="help-block with-errors"></div>
                              </div>

                              <div class="form-group form-group-with-icon">
                                <input id="form_email" type="email" name="email" class="form-control" maxlength="<?= $form_limits['email'] ?>" value="<?= htmlspecialchars($form_values['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                                <label>Email Address</label>
                                <div class="form-control-border"></div>
                                <div class="help-block with-errors"></div>
                              </div>

                              <div class="form-group form-group-with-icon">
                                <input id="form_subject" type="text" name="subject" class="form-control" maxlength="<?= $form_limits['subject'] ?>" value="<?= htmlspecialchars($form_values['subject'], ENT_QUOTES, 'UTF-8') ?>" required>
                                <label>Subject</label>
                                <div class="form-control-border"></div>
                                <div class="help-block with-errors"></div>
                              </div>
                            </div>

                            <div class="right-column">
                              <div class="form-group form-group-with-icon">
                                <textarea id="form_message" name="message" class="form-control" rows="7" maxlength="<?= $form_limits['message'] ?>" required><?= htmlspecialchars($form_values['message'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                <label>Message</label>
                                <div class="form-control-border"></div>
                                <div class="help-block with-errors"></div>
                              </div>
                            </div>
                          </div>

                          <input type="submit" class="button btn-send" value="Send message">
                        </div>
                      </form>

                    <?php

                    else:

                    ?>

                      <br><br><br>
                      <center>
                        <?php if ($name_form !== ''): ?>
                          <h4>Thank you <span style="color:#9b5750;"><?= htmlspecialchars($name_form, ENT_QUOTES, 'UTF-8') ?></span>! Message sent successfully!</h4>
                        <?php else: ?>
                          <h4>Thank you! Message sent successfully!</h4>
                        <?php endif; ?>
                      </center>

                    <?php endif; ?>

                  </div>
                  <!-- End of Contact Form -->
                </div>
              </div>
          </section>
          <!-- End of Contact Subpage -->
        </div>
      </div>
    </div>
  </div>

  <script src="js/jquery-2.1.3.min.js"></script>
  <script src="js/modernizr.custom.js"></script>
  <script src="js/animating.js"></script>

  <script src="js/imagesloaded.pkgd.min.js"></script>

  <script src='js/perfect-scrollbar.min.js'></script>
  <script src='js/jquery.shuffle.min.js'></script>
  <script src='js/masonry.pkgd.min.js'></script>
  <script src='js/owl.carousel.min.js'></script>
  <script src="js/jquery.magnific-popup.min.js"></script>

  <script src="js/validator.js"></script>
  <script src="js/main.js"></script>
</body>

</html>
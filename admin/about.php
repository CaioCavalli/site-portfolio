<?php

declare(strict_types=1);
date_default_timezone_set('Europe/Rome'); // same time zone used by index.php to log visits
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/dbconn.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// The public site reads the English "About me" text from this row.
const ABOUT_ID = 1;

// Editable columns of the `about` table, grouped as they appear on the site.
// Format: column => [label, field type, max length].
// Only these columns can ever be written by this page (whitelist).
$sections = [
    'Page title and introduction' => [
        'main_title_1' => ['Page title', 'input', 255],
        'main_text'    => ['Introduction text', 'textarea', 10000],
    ],
    'Personal information' => [
        'title_residence' => ['Residence label', 'input', 255],
        'residence'       => ['Residence', 'input', 255],
        'title_email'     => ['E-mail label', 'input', 255],
        'email'           => ['E-mail', 'input', 255],
    ],
    'What I do (four blocks)' => [
        'main_title_2' => ['Block section title', 'input', 255],
        'title_1'      => ['Block 1 title', 'input', 255],
        'text_1'       => ['Block 1 text', 'textarea', 5000],
        'title_2'      => ['Block 2 title', 'input', 255],
        'text_2'       => ['Block 2 text', 'textarea', 5000],
        'title_3'      => ['Block 3 title', 'input', 255],
        'text_3'       => ['Block 3 text', 'textarea', 5000],
        'title_4'      => ['Block 4 title', 'input', 255],
        'text_4'       => ['Block 4 text', 'textarea', 5000],
    ],
    'Fun facts' => [
        'main_title_3' => ['Fun facts section title', 'input', 255],
    ],
];

$fields = array_merge(...array_values($sections));
$columns = array_keys($fields);

if (empty($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

$errors = [];
$values = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_admin'], (string)($_POST['csrf'] ?? ''))) {
        header('Location: about.php?status=csrf');
        exit;
    }

    $values = [];
    foreach ($fields as $column => [$label, $type, $max]) {
        $value = $_POST[$column] ?? '';
        $value = is_string($value) ? trim($value) : '';
        if (mb_strlen($value) > $max) {
            $errors[] = "\"$label\" is too long (max $max characters).";
        }
        $values[$column] = $value;
    }

    if (!$errors) {
        $assignments = implode(', ', array_map(fn($c) => "`$c` = :$c", $columns));
        $params = [];
        foreach ($values as $column => $value) {
            $params[":$column"] = $value;
        }
        $params[':id'] = ABOUT_ID;

        try {
            $stmt = $conn->prepare("UPDATE about SET $assignments WHERE id = :id");
            if ($stmt->execute($params)) {
                // Post/Redirect/Get: avoids re-submitting on page refresh.
                header('Location: about.php?status=saved');
                exit;
            }
            $errors[] = 'The changes could not be saved. Please try again.';
        } catch (Throwable $e) {
            error_log('admin/about.php update failed: ' . $e->getMessage());
            $errors[] = 'The changes could not be saved. Please try again.';
        }
    }
}

// On GET (or after a successful save) load the current text from the database.
if ($values === null) {
    try {
        $columnList = implode(', ', array_map(fn($c) => "`$c`", $columns));
        $stmt = $conn->prepare("SELECT $columnList FROM about WHERE id = :id");
        $stmt->execute([':id' => ABOUT_ID]);
        $values = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('admin/about.php select failed: ' . $e->getMessage());
        $values = null;
    }
}

$status = $_GET['status'] ?? '';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="author" content="Caio Cavalli">

    <!-- Title Page-->
    <title>Edit About me</title>

    <!-- Fontfaces CSS-->
    <link href="css/font-face.css" rel="stylesheet" media="all">
    <link href="vendor/fontawesome-7.0.1/css/all.min.css" rel="stylesheet" media="all">
    <link href="vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">

    <!-- Bootstrap CSS-->
    <link href="vendor/bootstrap-5.3.8.min.css" rel="stylesheet" media="all">

    <!-- Vendor CSS-->
    <link href="css/aos.css" rel="stylesheet" media="all">
    <link href="vendor/css-hamburgers/hamburgers.min.css" rel="stylesheet" media="all">
    <link href="css/swiper-bundle-11.2.10.min.css" rel="stylesheet" media="all">
    <link href="vendor/perfect-scrollbar/perfect-scrollbar-1.5.6.css" rel="stylesheet" media="all">

    <!-- Main CSS-->
    <link href="css/theme.css" rel="stylesheet" media="all">

</head>

<body>
    <div class="page-wrapper">
        <?php
        include('topo.php');
        ?>

        <!-- MAIN CONTENT-->
        <div class="main-content">
            <div class="section__content section__content--p30">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-lg-12">

                            <?php if ($status === 'saved' && !$errors): ?>
                                <div class="alert alert-success">Changes saved. They are already live on the site.</div>
                            <?php elseif ($status === 'csrf'): ?>
                                <div class="alert alert-warning">Your session expired. Please make your changes again.</div>
                            <?php endif; ?>

                            <?php if ($errors): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $error): ?>
                                        <div><?= e($error) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($values === null): ?>
                                <div class="alert alert-danger">The "About me" text could not be loaded.</div>
                            <?php else: ?>
                                <form method="post" action="about.php" class="form-horizontal">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_admin']) ?>">

                                    <div class="card">
                                    <div class="card-header">
                                        <strong>Edit</strong> About me
                                    </div>
                                    <div class="card-body">
                                    <?php foreach ($sections as $sectionTitle => $sectionFields): ?>
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <strong><?= e($sectionTitle) ?></strong>
                                            </div>
                                            <div class="card-body">
                                                <?php foreach ($sectionFields as $column => [$label, $type, $max]): ?>
                                                    <div class="row mb-3">
                                                        <div class="col col-md-3">
                                                            <label for="<?= e($column) ?>" class="form-control-label"><?= e($label) ?></label>
                                                        </div>
                                                        <div class="col-12 col-md-9">
                                                            <?php if ($type === 'textarea'): ?>
                                                                <textarea
                                                                    id="<?= e($column) ?>"
                                                                    name="<?= e($column) ?>"
                                                                    class="form-control"
                                                                    rows="6"
                                                                    maxlength="<?= (int)$max ?>"><?= e($values[$column] ?? '') ?></textarea>
                                                            <?php else: ?>
                                                                <input
                                                                    type="text"
                                                                    id="<?= e($column) ?>"
                                                                    name="<?= e($column) ?>"
                                                                    class="form-control"
                                                                    maxlength="<?= (int)$max ?>"
                                                                    value="<?= e($values[$column] ?? '') ?>">
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <small class="form-text text-muted">HTML tags (for example &lt;br&gt; or &lt;strong&gt;) are kept and rendered on the site.</small>
                                    </div>
                                    <div class="card-footer">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fa-solid fa-floppy-disk"></i> Save changes
                                        </button>
                                        <a href="../#about-me" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site
                                        </a>
                                    </div>
                                    </div>
                                </form>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="copyright">
                            <p>© <?= date('Y') ?> All rights reserved<a href="https://caiocavalli.com" rel="nofollow" target="_blank">. Caio Cavalli</a>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Jquery JS-->
    <script src="js/vanilla-utils.js"></script>
    <!-- Bootstrap JS-->
    <script src="vendor/bootstrap-5.3.8.bundle.min.js"></script>
    <!-- Vendor JS       -->
    <script src="vendor/perfect-scrollbar/perfect-scrollbar-1.5.6.min.js"></script>
    <script src="vendor/chartjs/chart.umd.js-4.5.0.min.js"></script>

    <!-- Main JS-->
    <script src="js/bootstrap5-init.js"></script>
    <script src="js/main-vanilla.js"></script>
    <script src="js/swiper-bundle-11.2.10.min.js"></script>
    <script src="js/aos.js"></script>
    <script src="js/modern-plugins.js"></script>

</body>

</html>

<?php

declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']),
    'samesite' => 'Lax',
]);

session_start();

require_once __DIR__ . '/../includes/dbconn.php';
require_once __DIR__ . '/../includes/geo.php';

$redirectlogin = '/admin/dashboard.php';

if (!empty($_SESSION['auth'])) {
    header("Location: $redirectlogin");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = hash_equals($_SESSION['csrf_login'] ?? '', $_POST['csrf'] ?? '');
    if (!$csrfOk) {
        $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
        header('Location: index.php?erro=csrf');
        exit;
    }
    unset($_SESSION['csrf_login']);

    $tries = $_SESSION['login_tries'] ?? ['count' => 0, 'until' => 0];
    if (time() < ($tries['until'] ?? 0)) {
        header('Location: index.php?erro=wait');
        exit;
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    if (!$email || $password === '') {
        header('Location: index.php?erro=1');
        exit;
    }

    $sql_admin_users = "SELECT id, name, email, password_hash FROM admin_users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql_admin_users);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['auth'] = [
            'id'    => (int)$user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
        ];

        date_default_timezone_set('Europe/Rome');
        $now = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d H:i:s');
        try {
            $stmt = $conn->prepare("UPDATE admin_users SET last_login = :now WHERE id = :id");
            $stmt->execute([':now' => $now, ':id' => $user['id']]);
        } catch (Throwable $e) {
        }

        $_SESSION['login_tries'] = ['count' => 0, 'until' => 0];

        header("Location: $redirectlogin");
        exit;
    }

    $tries['count'] = ($tries['count'] ?? 0) + 1;
    if ($tries['count'] >= 5) {
        $tries = ['count' => 0, 'until' => time() + 5 * 60];
    }
    $_SESSION['login_tries'] = $tries;

    header('Location: index.php?erro=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="au theme template">
    <meta name="author" content="Caio Cavalli">
    <meta name="keywords" content="au theme template">

    <!-- Title Page-->
    <title>Login</title>

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
        <div class="page-content--bge5">
            <div class="container">
                <div class="login-wrap">
                    <div class="login-content">
                        <div class="login-logo">
                            <a href="#">
                                <img src="images/icon/logo.png" alt="CoolAdmin">
                            </a>
                        </div>
                        <div class="login-form">

                            <?php if (isset($_GET['erro']) && $_GET['erro'] == '1'): ?>
                                <div class="alert alert-danger" style="margin-bottom:12px;">
                                    E-mail ou senha inválidos.
                                </div>
                            <?php endif; ?>

                            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'wait'): ?>
                                <div class="alert alert-warning" style="margin-bottom:12px;">
                                    Muitas tentativas. Tente novamente em alguns minutos.
                                </div>
                            <?php endif; ?>

                            <form action="" method="post">
                                <?php if (isset($_GET['erro']) && $_GET['erro'] === 'csrf'): ?>
                                    <div class="alert alert-warning" style="margin-bottom:12px;">
                                        Sessão expirada ou requisição inválida. Recarregue a página e tente novamente.
                                    </div>
                                <?php endif; ?>

                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_login'] ?? '') ?>">
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input class="au-input au-input--full" type="email" name="email" placeholder="Email">
                                </div>
                                <div class="form-group">
                                    <label>Password</label>
                                    <input class="au-input au-input--full" type="password" name="password" placeholder="Password">
                                </div>
                                <div class="login-checkbox">
                                    <br><br>
                                    <!-- <label>
                                        <input type="checkbox" name="remember">Remember Me
                                    </label>
                                    <label>
                                        <a href="#">Forgotten Password?</a>
                                    </label> -->
                                </div>
                                <button class="au-btn au-btn--block au-btn--green m-b-20" type="submit">sign in</button>
                            </form>
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
<!-- end document-->
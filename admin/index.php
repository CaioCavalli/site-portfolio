<?php

declare(strict_types=1);
date_default_timezone_set('Europe/Rome');

session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']),
    'samesite' => 'Lax',
]);

session_start();

require_once __DIR__ . '/../includes/dbconn.php';
require_once __DIR__ . '/../includes/geo.php';

// Relative URL: works both locally (/caiosite/portfolio/admin/) and in production (/admin/).
$redirectlogin = 'dashboard.php';

// ---------- Brute-force protection (table `login_attempts`) ----------
// Failed logins are stored in the database, so clearing cookies does not reset the count.
const LOGIN_MAX_PER_IP     = 5;    // failed attempts allowed per IP...
const LOGIN_IP_WINDOW_MIN  = 15;   // ...within this many minutes
const LOGIN_MAX_GLOBAL     = 30;   // failed attempts allowed from all IPs together...
const LOGIN_GLOBAL_WINDOW  = 60;   // ...within this many minutes (stops attacks spread over many IPs)

// Real connection IP (cannot be faked with headers like X-Forwarded-For).
$clientIp = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

function minutes_ago(int $minutes): string
{
    return date('Y-m-d H:i:s', time() - $minutes * 60);
}

/**
 * True when this IP, or all IPs together, failed too many times recently.
 * If the check itself fails (e.g. table missing), login keeps working and the error is logged,
 * so the owner is never locked out by a technical problem.
 */
function login_is_blocked(PDO $conn, string $ip): bool
{
    try {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at >= :since');
        $stmt->execute([':ip' => $ip, ':since' => minutes_ago(LOGIN_IP_WINDOW_MIN)]);
        if ((int)$stmt->fetchColumn() >= LOGIN_MAX_PER_IP) {
            return true;
        }

        $stmt = $conn->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= :since');
        $stmt->execute([':since' => minutes_ago(LOGIN_GLOBAL_WINDOW)]);
        return (int)$stmt->fetchColumn() >= LOGIN_MAX_GLOBAL;
    } catch (Throwable $e) {
        error_log('admin/index.php login_attempts check failed: ' . $e->getMessage());
        return false;
    }
}

function login_record_failure(PDO $conn, string $ip, string $email): void
{
    try {
        $stmt = $conn->prepare('INSERT INTO login_attempts (ip, email, attempted_at) VALUES (:ip, :email, :now)');
        $stmt->execute([':ip' => $ip, ':email' => mb_substr($email, 0, 255), ':now' => date('Y-m-d H:i:s')]);

        // Housekeeping: keep only the last 24 hours.
        $stmt = $conn->prepare('DELETE FROM login_attempts WHERE attempted_at < :old');
        $stmt->execute([':old' => minutes_ago(24 * 60)]);
    } catch (Throwable $e) {
        error_log('admin/index.php login_attempts insert failed: ' . $e->getMessage());
    }
}

function login_clear_failures(PDO $conn, string $ip): void
{
    try {
        $stmt = $conn->prepare('DELETE FROM login_attempts WHERE ip = :ip');
        $stmt->execute([':ip' => $ip]);
    } catch (Throwable $e) {
        error_log('admin/index.php login_attempts cleanup failed: ' . $e->getMessage());
    }
}

if (!empty($_SESSION['auth'])) {
    header("Location: $redirectlogin");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf'] ?? '';
    $csrfOk = is_string($postedCsrf) && hash_equals($_SESSION['csrf_login'] ?? '', $postedCsrf);
    if (!$csrfOk) {
        $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
        header('Location: index.php?erro=csrf');
        exit;
    }
    unset($_SESSION['csrf_login']);

    if (login_is_blocked($conn, $clientIp)) {
        header('Location: index.php?erro=wait');
        exit;
    }

    $rawEmail = $_POST['email'] ?? '';
    $rawEmail = is_string($rawEmail) ? trim($rawEmail) : '';
    $email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    if (!$email || !is_string($password) || $password === '') {
        login_record_failure($conn, $clientIp, $rawEmail);
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

        $now = date('Y-m-d H:i:s');
        try {
            $stmt = $conn->prepare("UPDATE admin_users SET last_login = :now WHERE id = :id");
            $stmt->execute([':now' => $now, ':id' => $user['id']]);
        } catch (Throwable $e) {
        }

        login_clear_failures($conn, $clientIp);

        header("Location: $redirectlogin");
        exit;
    }

    login_record_failure($conn, $clientIp, $email);

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
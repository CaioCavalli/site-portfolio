<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/dbconn.php';


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


$sql_message = 'SELECT id, name, email, subject, message, ip, geo, dia
                FROM form
                ORDER BY id DESC';

$stmt = $conn->query($sql_message);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
    <title>Forms</title>

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
                            <div class="card">
                                <div class="card-header">
                                    <strong>Message</strong> From
                                </div>
                                <div class="card-body card-body">
                                    <?php if (empty($messages)): ?>
                                        <div class="alert alert-info">Nenhuma mensagem encontrada.</div>
                                    <?php else: ?>
                                        <?php foreach ($messages as $message): ?>
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <strong>Message</strong> — <?= htmlspecialchars($message['name'] ?? '', ENT_QUOTES) ?>
                                                </div>
                                                <div class="card-body">
                                                    <form class="form-horizontal">
                                                        <div class="row mb-3">
                                                            <div class="col col-md-3">
                                                                <label class="form-control-label">Name</label>
                                                            </div>
                                                            <div class="col-12 col-md-9">
                                                                <input
                                                                    type="text"
                                                                    id="name-<?= (int)$message['id']; ?>"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($message['name'] ?? '', ENT_QUOTES) ?>"
                                                                    readonly aria-readonly="true">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-3">
                                                            <div class="col col-md-3">
                                                                <label class="form-control-label">E-mail</label>
                                                            </div>
                                                            <div class="col-12 col-md-9">
                                                                <input
                                                                    type="text"
                                                                    id="email-<?= (int)$message['id']; ?>"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($message['email'] ?? '', ENT_QUOTES) ?>"
                                                                    readonly aria-readonly="true">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-3">
                                                            <div class="col col-md-3">
                                                                <label class="form-control-label">Subject</label>
                                                            </div>
                                                            <div class="col-12 col-md-9">
                                                                <input
                                                                    type="text"
                                                                    id="subject-<?= (int)$message['id']; ?>"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($message['subject'] ?? '', ENT_QUOTES) ?>"
                                                                    readonly aria-readonly="true">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-3">
                                                            <div class="col col-md-3">
                                                                <label class="form-control-label">Message</label>
                                                            </div>
                                                            <div class="col-12 col-md-9">
                                                                <textarea
                                                                    id="message-<?= (int)$message['id']; ?>"
                                                                    class="form-control"
                                                                    rows="9"
                                                                    readonly
                                                                    aria-readonly="true"><?= htmlspecialchars($message['message'] ?? '', ENT_QUOTES) ?></textarea>
                                                            </div>
                                                        </div>

                                                        <div class="row mb-3">
                                                            <div class="col col-md-3">
                                                                <label class="form-control-label">IP</label>
                                                            </div>
                                                            <div class="col-12 col-md-9">
                                                                <input
                                                                    type="text"
                                                                    id="ip-<?= (int)$message['id']; ?>"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($message['ip'] ?? '', ENT_QUOTES) ?>"
                                                                    readonly aria-readonly="true">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-3">
                                                            <div class="col col-md-3">
                                                                <label class="form-control-label">Geo</label>
                                                            </div>
                                                            <div class="col-12 col-md-9">
                                                                <input
                                                                    type="text"
                                                                    id="geo-<?= (int)$message['id']; ?>"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($message['geo'] ?? '', ENT_QUOTES) ?>"
                                                                    readonly aria-readonly="true">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-3">
                                                            <div class="col col-md-3">
                                                                <label class="form-control-label">Data</label>
                                                            </div>
                                                            <div class="col-12 col-md-9">
                                                                <input
                                                                    type="text"
                                                                    id="dia-<?= (int)$message['id']; ?>"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($message['dia'] ?? '', ENT_QUOTES) ?>"
                                                                    readonly aria-readonly="true">
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="copyright">
                            <p>© 2025 All rights reserved<a href="https://caiocavalli.com" rel="nofollow" target="_blank">. Caio Cavalli</a>.</p>
                        </div>
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
<!-- end document-->
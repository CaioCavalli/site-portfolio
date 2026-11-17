<?php

declare(strict_types=1);
date_default_timezone_set('Europe/Rome'); // same time zone used by index.php to log visits
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/dbconn.php';
require_once __DIR__ . '/visit-helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

// ---------- "Don't count my visits" on this device ----------
// index.php skips the `log` insert when this cookie is "1".
const SKIP_VISIT_COOKIE = 'skip_visit_log';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_count') {
    if (!hash_equals($_SESSION['csrf_admin'], (string)($_POST['csrf'] ?? ''))) {
        header('Location: dashboard.php?status=csrf');
        exit;
    }

    // Cookie path = the site root (one level above /admin/), so index.php can read it.
    // Locally "/caiosite/portfolio/", in production "/".
    $sitePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/') . '/';
    $skip = ($_POST['skip'] ?? '') === '1';

    setcookie(SKIP_VISIT_COOKIE, $skip ? '1' : '', [
        'expires'  => $skip ? time() + 365 * 24 * 60 * 60 : time() - 3600,
        'path'     => $sitePath,
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Post/Redirect/Get: avoids re-submitting on page refresh.
    header('Location: dashboard.php?status=' . ($skip ? 'skip-on' : 'skip-off'));
    exit;
}

$skipThisDevice = ($_COOKIE[SKIP_VISIT_COOKIE] ?? '') === '1';
$status = (string)($_GET['status'] ?? '');

// ---------- Helpers ----------

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Safe JSON for embedding inside a <script> block.
function js(mixed $value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

// ---------- Date range: the last 30 days, including today ----------

const DAYS_LONG = 30;   // main chart, unique visitors, messages, countries
const DAYS_SHORT = 14;  // small charts inside the cards

$today = date('Y-m-d');
$days = [];
for ($i = DAYS_LONG - 1; $i >= 0; $i--) {
    $days[] = date('Y-m-d', strtotime("-$i days"));
}
$since = $days[0] . ' 00:00:00';

$visitsByDay  = array_fill_keys($days, 0);
$uniqueByDay  = array_fill_keys($days, []);
$messagesByDay = array_fill_keys($days, 0);
$uniqueIps = [];
$countries = [];
$botsSelf = 0;   // user agent says it is a crawler
$botsCloud = 0;  // comes from a cloud / hosting provider
$messages30 = 0;
$latestMessages = [];
$loadError = false;

// ---------- Visits (table `log`), read-only ----------
try {
    $stmt = $conn->prepare('SELECT ip, geo, dia FROM log WHERE dia >= :since');
    $stmt->execute([':since' => $since]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $day = substr((string)$row['dia'], 0, 10);
        if (!isset($visitsByDay[$day])) {
            continue;
        }

        $geo = parse_geo((string)($row['geo'] ?? ''));
        $type = visit_type($geo);
        if ($type === 'bot') {
            $botsSelf++;
            continue;
        }
        if ($type === 'cloud') {
            $botsCloud++;
            continue;
        }

        $ip = (string)($row['ip'] ?? '');
        $visitsByDay[$day]++;
        $uniqueByDay[$day][$ip] = true;
        $uniqueIps[$ip] = true;

        $country = geo_country($geo);
        $countries[$country] = ($countries[$country] ?? 0) + 1;
    }
} catch (Throwable $ex) {
    error_log('admin/dashboard.php visits query failed: ' . $ex->getMessage());
    $loadError = true;
}

// ---------- Messages (table `form`), read-only ----------
try {
    $stmt = $conn->prepare('SELECT dia FROM form WHERE dia >= :since');
    $stmt->execute([':since' => $since]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $day = substr((string)$row['dia'], 0, 10);
        if (isset($messagesByDay[$day])) {
            $messagesByDay[$day]++;
            $messages30++;
        }
    }

    $stmt = $conn->query('SELECT name, subject, dia FROM form ORDER BY id DESC LIMIT 5');
    $latestMessages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $ex) {
    error_log('admin/dashboard.php messages query failed: ' . $ex->getMessage());
    $loadError = true;
}

// ---------- Numbers for the page ----------
$uniqueCountByDay = array_map('count', $uniqueByDay);

$visitsToday = $visitsByDay[$today] ?? 0;
$visits7 = array_sum(array_slice($visitsByDay, -7));
$unique30 = count($uniqueIps);

arsort($countries);
$topCountries = array_slice($countries, 0, 8, true);

// Doughnut: top 5 countries + "Others"
$doughnut = array_slice($countries, 0, 5, true);
$others = array_sum(array_slice($countries, 5));
if ($others > 0) {
    $doughnut['Others'] = $others;
}

// Chart labels like "17 Nov"
$labelsLong = array_map(fn($d) => date('j M', strtotime($d)), $days);
$labelsShort = array_slice($labelsLong, -DAYS_SHORT);

$cards = [
    [
        'id' => 'kpiChart1', 'color' => 'c1', 'icon' => 'zmdi-eye',
        'value' => $visitsToday, 'label' => 'visits today',
        'series' => array_values(array_slice($visitsByDay, -DAYS_SHORT)),
    ],
    [
        'id' => 'kpiChart2', 'color' => 'c2', 'icon' => 'zmdi-calendar-note',
        'value' => $visits7, 'label' => 'visits (7 days)',
        'series' => array_values(array_slice($visitsByDay, -DAYS_SHORT)),
    ],
    [
        'id' => 'kpiChart3', 'color' => 'c3', 'icon' => 'zmdi-account-o',
        'value' => $unique30, 'label' => 'unique visitors (30 days)',
        'series' => array_values(array_slice($uniqueCountByDay, -DAYS_SHORT)),
    ],
    [
        'id' => 'kpiChart4', 'color' => 'c4', 'icon' => 'zmdi-email',
        'value' => $messages30, 'label' => 'messages (30 days)',
        'series' => array_values(array_slice($messagesByDay, -DAYS_SHORT)),
    ],
];

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="author" content="Caio Cavalli">

    <!-- Title Page-->
    <title>Dashboard</title>

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

    <link rel="shortcut icon" href="favicon.ico">

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
                        <div class="col-md-12">
                            <div class="overview-wrap">
                                <h2 class="title-1">overview</h2>
                            </div>
                            <small class="form-text text-muted">
                                Bots ignored in the last <?= DAYS_LONG ?> days: <?= number_format($botsSelf + $botsCloud) ?>
                                (<?= number_format($botsSelf) ?> self-declared, <?= number_format($botsCloud) ?> from cloud servers).
                            </small>
                        </div>
                    </div>

                    <?php if ($status === 'skip-on'): ?>
                        <div class="row m-t-25">
                            <div class="col-md-12">
                                <div class="alert alert-success">Done. Your visits on this device will no longer be counted.</div>
                            </div>
                        </div>
                    <?php elseif ($status === 'skip-off'): ?>
                        <div class="row m-t-25">
                            <div class="col-md-12">
                                <div class="alert alert-success">Done. Your visits on this device will be counted again.</div>
                            </div>
                        </div>
                    <?php elseif ($status === 'csrf'): ?>
                        <div class="row m-t-25">
                            <div class="col-md-12">
                                <div class="alert alert-warning">Your session expired. Please try again.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($loadError): ?>
                        <div class="row m-t-25">
                            <div class="col-md-12">
                                <div class="alert alert-danger">Some data could not be loaded. The numbers below may be incomplete.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- KPI cards -->
                    <div class="row m-t-25">
                        <?php foreach ($cards as $card): ?>
                            <div class="col-sm-6 col-lg-3">
                                <div class="overview-item overview-item--<?= e($card['color']) ?>">
                                    <div class="overview__inner">
                                        <div class="overview-box clearfix">
                                            <div class="icon">
                                                <i class="zmdi <?= e($card['icon']) ?>"></i>
                                            </div>
                                            <div class="text">
                                                <h2><?= number_format((int)$card['value']) ?></h2>
                                                <span><?= e($card['label']) ?></span>
                                            </div>
                                        </div>
                                        <div class="overview-chart">
                                            <canvas id="<?= e($card['id']) ?>"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Charts -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="au-card recent-report">
                                <div class="au-card-inner">
                                    <h3 class="title-2">visits per day (last <?= DAYS_LONG ?> days)</h3>
                                    <div class="recent-report__chart" style="position: relative; height: 250px;">
                                        <canvas id="visitsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="au-card chart-percent-card">
                                <div class="au-card-inner">
                                    <h3 class="title-2 tm-b-5">visits by country</h3>
                                    <?php if (!$doughnut): ?>
                                        <p class="text-muted m-t-25">No visits in the last <?= DAYS_LONG ?> days.</p>
                                    <?php else: ?>
                                        <div style="position: relative; height: 280px;">
                                            <canvas id="countriesChart"></canvas>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tables -->
                    <div class="row">
                        <div class="col-lg-8">
                            <h2 class="title-1 m-b-25">Latest messages</h2>
                            <div class="table-responsive table--no-card m-b-25">
                                <table class="table table-borderless table-striped table-earning">
                                    <thead>
                                        <tr>
                                            <th>date</th>
                                            <th>name</th>
                                            <th>subject</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$latestMessages): ?>
                                            <tr>
                                                <td colspan="3">No messages yet.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($latestMessages as $message): ?>
                                            <tr>
                                                <td class="text-nowrap"><?= e(substr((string)$message['dia'], 0, 16)) ?></td>
                                                <td><?= e($message['name']) ?></td>
                                                <td><?= e($message['subject']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="m-b-40">
                                <a href="message.php" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-envelope"></i> See all messages
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <h2 class="title-1 m-b-25">Top countries</h2>
                            <div class="au-card au-card--bg-blue au-card-top-countries m-b-40">
                                <div class="au-card-inner">
                                    <div class="table-responsive">
                                        <table class="table table-top-countries">
                                            <tbody>
                                                <?php if (!$topCountries): ?>
                                                    <tr>
                                                        <td>No visits yet.</td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php foreach ($topCountries as $country => $count): ?>
                                                    <tr>
                                                        <td><?= e((string)$country) ?></td>
                                                        <td class="text-end"><?= number_format($count) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- This device: count my own visits or not -->
                    <div class="row">
                        <div class="col-lg-12">
                            <form method="post" action="dashboard.php">
                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_admin']) ?>">
                                <input type="hidden" name="action" value="toggle_count">
                                <input type="hidden" name="skip" value="<?= $skipThisDevice ? '0' : '1' ?>">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>This device</strong>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($skipThisDevice): ?>
                                            <span class="badge bg-success">Not counted</span>
                                            Your visits to the site from this browser are <strong>not</strong> recorded.
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Counted</span>
                                            Your visits to the site from this browser are recorded like any other visitor.
                                        <?php endif; ?>
                                        <small class="form-text text-muted d-block mt-2">
                                            Applies only to this browser on this device. Clearing cookies, private windows or another browser will count visits again.
                                        </small>
                                    </div>
                                    <div class="card-footer">
                                        <?php if ($skipThisDevice): ?>
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                <i class="fa-solid fa-eye"></i> Count my visits again
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fa-solid fa-eye-slash"></i> Don't count my visits
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="copyright">
                                <p>© <?= date('Y') ?> All rights reserved<a href="https://caiocavalli.com" rel="nofollow"
                                        target="_blank">. Caio Cavalli</a>.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END MAIN CONTENT-->
        <!-- END PAGE CONTAINER-->
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

    <!-- Dashboard charts (real data) -->
    <script>
        (() => {
            "use strict";
            if (typeof Chart === 'undefined') {
                return;
            }

            const cards = <?= js(array_map(fn($c) => ['id' => $c['id'], 'series' => $c['series']], $cards)) ?>;
            const labelsShort = <?= js($labelsShort) ?>;
            const labelsLong = <?= js($labelsLong) ?>;
            const visits = <?= js(array_values($visitsByDay)) ?>;
            const unique = <?= js(array_values($uniqueCountByDay)) ?>;
            const countryLabels = <?= js(array_map('strval', array_keys($doughnut))) ?>;
            const countryValues = <?= js(array_values($doughnut)) ?>;

            // Small white line charts inside the coloured cards
            cards.forEach((card) => {
                const canvas = document.getElementById(card.id);
                if (!canvas) {
                    return;
                }
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labelsShort,
                        datasets: [{
                            data: card.series,
                            label: 'Total',
                            fill: true,
                            tension: 0.4,
                            backgroundColor: 'rgba(255,255,255,.1)',
                            borderColor: 'rgba(255,255,255,.55)',
                            borderWidth: 2,
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { display: false },
                            y: { display: false, beginAtZero: true }
                        },
                        elements: { point: { radius: 0, hitRadius: 10, hoverRadius: 4 } }
                    }
                });
            });

            // Visits per day: total and unique
            const visitsCanvas = document.getElementById('visitsChart');
            if (visitsCanvas) {
                new Chart(visitsCanvas, {
                    type: 'line',
                    data: {
                        labels: labelsLong,
                        datasets: [{
                            label: 'Visits',
                            data: visits,
                            fill: true,
                            tension: 0.3,
                            backgroundColor: 'rgba(0, 181, 233, 0.4)',
                            borderColor: '#00b5e9',
                            pointHoverBackgroundColor: '#fff',
                            borderWidth: 2,
                        }, {
                            label: 'Unique visitors',
                            data: unique,
                            fill: true,
                            tension: 0.3,
                            backgroundColor: 'rgba(250, 66, 81, 0.4)',
                            borderColor: '#fa4251',
                            pointHoverBackgroundColor: '#fff',
                            borderWidth: 2,
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: { usePointStyle: true, padding: 15, font: { size: 12 } }
                            }
                        },
                        scales: {
                            x: { grid: { drawOnChartArea: false } },
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        },
                        elements: { point: { radius: 0, hitRadius: 10, hoverRadius: 4, hoverBorderWidth: 3 } }
                    }
                });
            }

            // Visits by country (top 5 + Others)
            const countriesCanvas = document.getElementById('countriesChart');
            if (countriesCanvas) {
                new Chart(countriesCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: countryLabels,
                        datasets: [{
                            label: 'Visits',
                            data: countryValues,
                            backgroundColor: ['#00b5e9', '#fa4251', '#28a745', '#ff6b35', '#6f42c1', '#adb5bd'],
                            borderWidth: 2,
                            hoverBorderColor: '#ffffff'
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        cutout: '75%',
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: { usePointStyle: true, padding: 15, font: { size: 12 } }
                            }
                        }
                    }
                });
            }
        })();
    </script>

</body>

</html>
<!-- end document-->

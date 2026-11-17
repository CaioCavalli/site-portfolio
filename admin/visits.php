<?php

declare(strict_types=1);
date_default_timezone_set('Europe/Rome'); // same time zone used by index.php to log visits
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/dbconn.php';
require_once __DIR__ . '/visit-helpers.php';

// ---------- Helpers ----------

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ---------- Filters (validated against fixed lists) ----------

const PER_PAGE = 50;

$periods = [
    'today' => ['Today', date('Y-m-d') . ' 00:00:00'],
    '7'     => ['Last 7 days', date('Y-m-d', strtotime('-6 days')) . ' 00:00:00'],
    '30'    => ['Last 30 days', date('Y-m-d', strtotime('-29 days')) . ' 00:00:00'],
    'all'   => ['All', null],
];

$period = (string)($_GET['period'] ?? '30');
if (!isset($periods[$period])) {
    $period = '30';
}
$showBots = ($_GET['bots'] ?? '') === '1';
$page = max(1, (int)($_GET['page'] ?? 1));

// Build the WHERE clause from fixed pieces only; values go in as parameters.
$where = [];
$params = [];
if ($periods[$period][1] !== null) {
    $where[] = 'dia >= :since';
    $params[':since'] = $periods[$period][1];
}
if (!$showBots) {
    $where[] = sql_exclude_bots();
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- Load visits (read-only) ----------

$total = 0;
$pages = 1;
$visits = [];
$loadError = false;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM log $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $pages = max(1, (int)ceil($total / PER_PAGE));
    $page = min($page, $pages);

    $stmt = $conn->prepare("SELECT ip, geo, dia FROM log $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', PER_PAGE, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * PER_PAGE, PDO::PARAM_INT);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $geo = parse_geo((string)($row['geo'] ?? ''));
        $success = ($geo['status'] ?? '') === 'success';
        $visits[] = [
            'dia'     => substr((string)$row['dia'], 0, 16),
            'country' => geo_country($geo),
            'city'    => $success && ($geo['city'] ?? '') !== '' ? $geo['city'] : '—',
            'agent'   => summarize_agent($geo['navegador']),
            'ip'      => (string)($row['ip'] ?? ''),
            'type'    => visit_type($geo),
        ];
    }
} catch (Throwable $ex) {
    error_log('admin/visits.php query failed: ' . $ex->getMessage());
    $loadError = true;
}

// Link to another page while keeping the current filters.
function page_url(int $page, string $period, bool $showBots): string
{
    $query = ['period' => $period, 'page' => $page];
    if ($showBots) {
        $query['bots'] = '1';
    }
    return 'visits.php?' . http_build_query($query);
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
    <title>Visits</title>

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

                            <!-- Filters -->
                            <form method="get" action="visits.php" class="form-horizontal">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Visits</strong> filters
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col col-md-3">
                                                <label for="period" class="form-control-label">Period</label>
                                            </div>
                                            <div class="col-12 col-md-9">
                                                <select id="period" name="period" class="form-select">
                                                    <?php foreach ($periods as $key => [$label]): ?>
                                                        <option value="<?= e((string)$key) ?>" <?= (string)$key === $period ? 'selected' : '' ?>><?= e($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col col-md-3">
                                                <label for="bots" class="form-control-label">Bots</label>
                                            </div>
                                            <div class="col-12 col-md-9">
                                                <div class="form-check">
                                                    <input type="checkbox" id="bots" name="bots" value="1" class="form-check-input" <?= $showBots ? 'checked' : '' ?>>
                                                    <label for="bots" class="form-check-label">Show bots</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fa-solid fa-filter"></i> Apply
                                        </button>
                                        <a href="visits.php" class="btn btn-secondary btn-sm">
                                            <i class="fa-solid fa-ban"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>

                            <?php if ($loadError): ?>
                                <div class="alert alert-danger">The visits could not be loaded.</div>
                            <?php else: ?>
                                <!-- Results -->
                                <div class="card">
                                    <div class="card-header">
                                        <strong><?= number_format($total) ?></strong> <?= $total === 1 ? 'visit' : 'visits' ?> found
                                        <span class="text-muted">· <?= e($periods[$period][0]) ?><?= $showBots ? ' · bots included' : ' · bots hidden' ?></span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!$visits): ?>
                                            <div class="alert alert-info mb-0">No visits for this filter.</div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-borderless table-striped table-earning">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Country</th>
                                                            <th>City</th>
                                                            <th>Browser / OS</th>
                                                            <th>IP</th>
                                                            <th>Type</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($visits as $visit): ?>
                                                            <tr>
                                                                <td class="text-nowrap"><?= e($visit['dia']) ?></td>
                                                                <td><?= e($visit['country']) ?></td>
                                                                <td><?= e($visit['city']) ?></td>
                                                                <td><?= e($visit['agent']) ?></td>
                                                                <td class="text-nowrap"><?= e($visit['ip']) ?></td>
                                                                <td>
                                                                    <?php if ($visit['type'] === 'bot'): ?>
                                                                        <span class="badge bg-secondary">Bot</span>
                                                                    <?php elseif ($visit['type'] === 'cloud'): ?>
                                                                        <span class="badge bg-warning text-dark" title="Comes from a cloud / hosting provider">Cloud</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-success">Visitor</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($pages > 1): ?>
                                        <div class="card-footer d-flex align-items-center gap-2">
                                            <?php if ($page > 1): ?>
                                                <a href="<?= e(page_url($page - 1, $period, $showBots)) ?>" class="btn btn-secondary btn-sm">
                                                    <i class="fa-solid fa-chevron-left"></i> Previous
                                                </a>
                                            <?php endif; ?>
                                            <span class="text-muted">Page <?= (int)$page ?> of <?= (int)$pages ?></span>
                                            <?php if ($page < $pages): ?>
                                                <a href="<?= e(page_url($page + 1, $period, $showBots)) ?>" class="btn btn-secondary btn-sm">
                                                    Next <i class="fa-solid fa-chevron-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
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

<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/dbconn.php';
require_once __DIR__ . '/visit-helpers.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Safe JSON for embedding inside a <script> block.
function js(mixed $value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

// ---------- Filters (validated against fixed lists, same as visits.php) ----------

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

// ---------- Group visits by city (read-only) ----------

$places = [];        // key => [city, country, lat, lon, count, visitor, cloud, bot]
$total = 0;
$withoutLocation = 0;
$loadError = false;

try {
    $stmt = $conn->prepare("SELECT geo FROM log $whereSql");
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $total++;
        $geo = parse_geo((string)($row['geo'] ?? ''));

        $lat = $geo['lat'] ?? '';
        $lon = $geo['lon'] ?? '';
        if (($geo['status'] ?? '') !== 'success' || !is_numeric($lat) || !is_numeric($lon)) {
            $withoutLocation++;
            continue;
        }

        $country = geo_country($geo);
        $city = ($geo['city'] ?? '') !== '' ? $geo['city'] : 'Unknown city';
        $key = $city . '|' . $country;

        if (!isset($places[$key])) {
            $places[$key] = [
                'city'    => $city,
                'country' => $country,
                'lat'     => round((float)$lat, 4),
                'lon'     => round((float)$lon, 4),
                'count'   => 0,
                'visitor' => 0,   // counts per visit_type(), used for the circle colours
                'cloud'   => 0,
                'bot'     => 0,
            ];
        }
        $places[$key]['count']++;
        $places[$key][visit_type($geo)]++;
    }
} catch (Throwable $ex) {
    error_log('admin/map.php query failed: ' . $ex->getMessage());
    $loadError = true;
}

usort($places, fn($a, $b) => $b['count'] <=> $a['count']);
$topCities = array_slice($places, 0, 10);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="author" content="Caio Cavalli">

    <!-- Title Page-->
    <title>Visitor map</title>

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
    <!-- Leaflet CSS-->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

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
                            <form method="get" action="map.php" class="form-horizontal">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Visitor map</strong> filters
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
                                        <a href="map.php" class="btn btn-secondary btn-sm">
                                            <i class="fa-solid fa-ban"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>

                            <?php if ($loadError): ?>
                                <div class="alert alert-danger">The visits could not be loaded.</div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <?php if (!$loadError): ?>
                        <div class="row">
                            <!-- Map -->
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <strong><?= number_format($total) ?></strong> <?= $total === 1 ? 'visit' : 'visits' ?>
                                        from <strong><?= number_format(count($places)) ?></strong> <?= count($places) === 1 ? 'city' : 'cities' ?>
                                        <span class="text-muted">· <?= e($periods[$period][0]) ?><?= $showBots ? ' · bots included' : ' · bots hidden' ?></span>
                                    </div>
                                    <div class="card-body">
                                        <div id="visitorMap" style="height: 500px; border-radius: 4px;"></div>
                                        <small class="form-text text-muted">
                                            Locations are approximate (based on the IP address, usually the city or the internet provider).
                                            <?php if ($withoutLocation > 0): ?>
                                                <?= number_format($withoutLocation) ?> <?= $withoutLocation === 1 ? 'visit' : 'visits' ?> without location (not on the map).
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Top cities -->
                            <div class="col-lg-4">
                                <h2 class="title-1 m-b-25">Top cities</h2>
                                <div class="au-card au-card--bg-blue au-card-top-countries m-b-40">
                                    <div class="au-card-inner">
                                        <div class="table-responsive">
                                            <table class="table table-top-countries">
                                                <tbody>
                                                    <?php if (!$topCities): ?>
                                                        <tr>
                                                            <td>No visits for this filter.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                    <?php foreach ($topCities as $place): ?>
                                                        <tr>
                                                            <td><?= e($place['city']) ?>, <?= e($place['country']) ?></td>
                                                            <td class="text-end"><?= number_format($place['count']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

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
        <!-- END MAIN CONTENT-->
    </div>

    <!-- Jquery JS-->
    <script src="js/vanilla-utils.js"></script>
    <!-- Bootstrap JS-->
    <script src="vendor/bootstrap-5.3.8.bundle.min.js"></script>
    <!-- Vendor JS       -->
    <script src="vendor/perfect-scrollbar/perfect-scrollbar-1.5.6.min.js"></script>
    <script src="vendor/chartjs/chart.umd.js-4.5.0.min.js"></script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Main JS-->
    <script src="js/bootstrap5-init.js"></script>
    <script src="js/main-vanilla.js"></script>
    <script src="js/swiper-bundle-11.2.10.min.js"></script>
    <script src="js/aos.js"></script>
    <script src="js/modern-plugins.js"></script>

    <!-- Visitor map (real data) -->
    <script>
        (() => {
            "use strict";
            const container = document.getElementById('visitorMap');
            if (!container || typeof L === 'undefined') {
                return;
            }

            const places = <?= js(array_values($places)) ?>;
            const showBots = <?= js($showBots) ?>;

            // Same colours as the badges on the Visits page.
            // Drawn in this order, so real visitors end up on top.
            const types = [
                { key: 'bot',     label: 'Bot',     color: '#6c757d' },
                { key: 'cloud',   label: 'Cloud',   color: '#ffc107' },
                { key: 'visitor', label: 'Visitor', color: '#00b5e9' }
            ];

            const map = L.map(container, {
                center: [20, 0],
                zoom: 2,
                scrollWheelZoom: false,
                worldCopyJump: true
            });

            // OpenStreetMap requires this attribution to be visible.
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                minZoom: 1,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
            }).addTo(map);

            const plural = (n) => n + (n === 1 ? ' visit' : ' visits');

            // Popup built as plain text (no HTML) because the names come from an external API.
            function buildPopup(place) {
                const popup = document.createElement('div');
                const title = document.createElement('strong');
                title.textContent = place.city + ', ' + place.country;
                popup.appendChild(title);
                popup.appendChild(document.createElement('br'));
                popup.appendChild(document.createTextNode(plural(place.count)));

                // With bots shown, add one line per type that has visits.
                if (showBots) {
                    types.slice().reverse().forEach((type) => {
                        if (place[type.key] > 0) {
                            popup.appendChild(document.createElement('br'));
                            popup.appendChild(document.createTextNode(type.label + ': ' + place[type.key]));
                        }
                    });
                }
                return popup;
            }

            const bounds = [];
            types.forEach((type) => {
                places.forEach((place) => {
                    const count = place[type.key];
                    if (!count) {
                        return;
                    }
                    const marker = L.circleMarker([place.lat, place.lon], {
                        radius: Math.min(30, 6 + Math.sqrt(count) * 3),
                        fillColor: type.color,
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.7
                    }).addTo(map);

                    marker.bindPopup(buildPopup(place));
                    marker.on('mouseover', function () { this.setStyle({ fillOpacity: 0.9, weight: 3 }); });
                    marker.on('mouseout', function () { this.setStyle({ fillOpacity: 0.7, weight: 2 }); });

                    bounds.push([place.lat, place.lon]);
                });
            });

            // Colour legend, only useful when bots are shown.
            if (showBots) {
                const legend = L.control({ position: 'bottomleft' });
                legend.onAdd = function () {
                    const box = L.DomUtil.create('div');
                    box.style.cssText = 'background: rgba(255,255,255,0.9); padding: 6px 10px; border-radius: 4px; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);';
                    types.slice().reverse().forEach((type) => {
                        const row = document.createElement('div');
                        const dot = document.createElement('span');
                        dot.style.cssText = 'display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; background: ' + type.color + ';';
                        row.appendChild(dot);
                        row.appendChild(document.createTextNode(type.label));
                        box.appendChild(row);
                    });
                    return box;
                };
                legend.addTo(map);
            }

            // Zoom to show every circle (but not too close when there is only one city).
            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 6 });
            }
        })();
    </script>

</body>

</html>
<!-- end document-->

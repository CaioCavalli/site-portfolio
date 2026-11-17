        <?php
        // Data for the header. This file is included by every admin page,
        // so all variables are prefixed with "topo" to avoid overwriting the page's own.

        // Logged-in user (set by admin/index.php at login).
        $topoUserName = (string)($_SESSION['auth']['name'] ?? 'Admin');
        $topoUserEmail = (string)($_SESSION['auth']['email'] ?? '');

        // Contact-form messages: count of the last 7 days + the 3 most recent (read-only).
        $topoMsgCount = null;
        $topoMsgLatest = [];
        if (isset($conn) && $conn instanceof PDO) {
            try {
                $topoStmt = $conn->prepare('SELECT COUNT(*) FROM form WHERE dia >= :since');
                $topoStmt->execute([':since' => date('Y-m-d H:i:s', strtotime('-7 days'))]);
                $topoMsgCount = (int)$topoStmt->fetchColumn();

                $topoStmt = $conn->query('SELECT name, subject, dia FROM form ORDER BY id DESC LIMIT 3');
                $topoMsgLatest = $topoStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $topoEx) {
                error_log('admin/topo.php messages query failed: ' . $topoEx->getMessage());
                $topoMsgCount = null;
                $topoMsgLatest = [];
            }
        }
        ?>
        <!-- HEADER MOBILE-->
        <header class="header-mobile d-block d-lg-none">
            <div class="header-mobile__bar">
                <div class="container-fluid">
                    <div class="header-mobile-inner">
                        <a class="logo" href="dashboard.php">
                            <img src="images/icon/logo.png" alt="Caio Cavalli" />
                        </a>
                        <button class="hamburger hamburger--slider" type="button">
                            <span class="hamburger-box">
                                <span class="hamburger-inner"></span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
            <nav class="navbar-mobile">
                <div class="container-fluid">
                    <ul class="navbar-mobile__list list-unstyled">
                        <li>
                            <a href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>Dashboard</a>
                        </li>
                        <li>
                            <a href="visits.php">
                                <i class="fa-solid fa-eye"></i>Visits</a>
                        </li>
                        <li>
                            <a href="message.php">
                                <i class="fas fa-envelope"></i>Messages</a>
                        </li>
                        <li>
                            <a href="about.php">
                                <i class="fa-solid fa-user"></i>About</a>
                        </li>
                        <li>
                            <a href="resume.php">
                                <i class="fa-solid fa-file-lines"></i>Resume</a>
                        </li>
                        <li>
                            <a href="map.php">
                                <i class="fa-solid fa-location-dot"></i>Visitor map</a>
                        </li>
                    </ul>
                </div>
            </nav>
        </header>
        <!-- END HEADER MOBILE-->

        <!-- MENU SIDEBAR-->
        <aside class="menu-sidebar d-none d-lg-block">
            <div class="logo">
                <a href="dashboard.php">
                    <img src="images/icon/logo.png" alt="Caio Cavalli" />
                </a>
            </div>
            <div class="menu-sidebar__content js-scrollbar1">
                <nav class="navbar-sidebar">
                    <ul class="list-unstyled navbar__list">
                        <li>
                            <a href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>Dashboard</a>
                        </li>
                        <li>
                            <a href="visits.php">
                                <i class="fa-solid fa-eye"></i>Visits</a>
                        </li>
                        <li>
                            <a href="message.php">
                                <i class="fas fa-envelope"></i>Messages</a>
                        </li>
                        <li>
                            <a href="about.php">
                                <i class="fa-solid fa-user"></i>About</a>
                        </li>
                        <li>
                            <a href="resume.php">
                                <i class="fa-solid fa-file-lines"></i>Resume</a>
                        </li>
                        <li>
                            <a href="map.php">
                                <i class="fa-solid fa-location-dot"></i>Visitor map</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>
        <!-- END MENU SIDEBAR-->

        <!-- PAGE CONTAINER-->
        <div class="page-container">
            <!-- HEADER DESKTOP-->
            <header class="header-desktop">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <div class="header-wrap" style="display:flex; align-items:center; justify-content:flex-end;">
                            <div class="header-button">
                                <div class="noti-wrap">
                                    <!-- Contact-form messages (real data) -->
                                    <div class="noti__item js-item-menu">
                                        <i class="zmdi zmdi-email"></i>
                                        <?php if ($topoMsgCount): ?>
                                            <span class="quantity"><?= (int)$topoMsgCount ?></span>
                                        <?php endif; ?>
                                        <div class="email-dropdown js-dropdown">
                                            <div class="email__title">
                                                <?php if ($topoMsgCount === null): ?>
                                                    <p>Messages could not be loaded</p>
                                                <?php elseif ($topoMsgCount === 0): ?>
                                                    <p>No messages in the last 7 days</p>
                                                <?php else: ?>
                                                    <p><?= (int)$topoMsgCount ?> <?= $topoMsgCount === 1 ? 'message' : 'messages' ?> in the last 7 days</p>
                                                <?php endif; ?>
                                            </div>
                                            <?php foreach ($topoMsgLatest as $topoMsg): ?>
                                                <div class="email__item">
                                                    <div class="image img-cir img-40 bg-c1 d-flex align-items-center justify-content-center">
                                                        <i class="zmdi zmdi-account" style="color: #fff; font-size: 20px;"></i>
                                                    </div>
                                                    <div class="content">
                                                        <p><?= htmlspecialchars(mb_strimwidth((string)$topoMsg['subject'], 0, 40, '…'), ENT_QUOTES, 'UTF-8') ?></p>
                                                        <span>
                                                            <?= htmlspecialchars((string)$topoMsg['name'], ENT_QUOTES, 'UTF-8') ?>,
                                                            <?= htmlspecialchars(date('j M Y, H:i', strtotime((string)$topoMsg['dia'])), ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <div class="email__footer">
                                                <a href="message.php">See all messages</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="account-wrap">
                                    <div class="account-item clearfix js-item-menu">
                                        <div class="image d-flex align-items-center justify-content-center" style="background: #e5e5e5;">
                                            <i class="fa-solid fa-user" style="font-size: 22px; color: #666;"></i>
                                        </div>
                                        <div class="content">
                                            <a class="js-acc-btn" href="#"><?= htmlspecialchars($topoUserName, ENT_QUOTES, 'UTF-8') ?></a>
                                        </div>
                                        <div class="account-dropdown js-dropdown">
                                            <div class="info clearfix">
                                                <div class="image d-flex align-items-center justify-content-center" style="background: #e5e5e5;">
                                                    <i class="fa-solid fa-user" style="font-size: 32px; color: #666;"></i>
                                                </div>
                                                <div class="content">
                                                    <h5 class="name">
                                                        <a href="account.php"><?= htmlspecialchars($topoUserName, ENT_QUOTES, 'UTF-8') ?></a>
                                                    </h5>
                                                    <span class="email"><?= htmlspecialchars($topoUserEmail, ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            </div>
                                            <div class="account-dropdown__body">
                                                <div class="account-dropdown__item">
                                                    <a href="account.php">
                                                        <i class="zmdi zmdi-account"></i>Account</a>
                                                </div>
                                            </div>
                                            <div class="account-dropdown__footer">
                                                <a href="logout.php">
                                                    <i class="zmdi zmdi-power"></i>Logout</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <!-- HEADER DESKTOP-->
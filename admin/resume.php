<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/dbconn.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// The public site shows only the English rows (language = 1).
// Every query on this page is restricted to these rows.
const RESUME_LANGUAGE = '1';

// Allowed values for the `categoria` column, in display order.
const CATEGORIES = ['Education', 'Experience'];

const PAGE_TITLE_MAX = 200;

// Editable columns of a resume item.
// Format: column => [label, field type, max length, required, placeholder].
$itemFields = [
    'data_ref'  => ['Period', 'input', 200, true, 'e.g. 2019 - 2021'],
    'local_ref' => ['Institution / company', 'input', 200, true, ''],
    'title_1'   => ['Title', 'input', 200, true, ''],
    'text_1'    => ['Description', 'textarea', 10000, false, ''],
];

if (empty($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $query): void
{
    header('Location: resume.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

// Same rule as index.php: the title of the most recent Education item.
function load_page_title(PDO $conn): string
{
    $stmt = $conn->prepare(
        "SELECT main_title_1 FROM resume
         WHERE language = :language
         ORDER BY (categoria = 'Education') DESC, data_ref DESC
         LIMIT 1"
    );
    $stmt->execute([':language' => RESUME_LANGUAGE]);
    $title = $stmt->fetchColumn();
    return is_string($title) && $title !== '' ? $title : 'Resume';
}

function item_exists(PDO $conn, int $id): bool
{
    $stmt = $conn->prepare('SELECT id FROM resume WHERE id = :id AND language = :language');
    $stmt->execute([':id' => $id, ':language' => RESUME_LANGUAGE]);
    return $stmt->fetchColumn() !== false;
}

$errors = [];
$view = 'list';   // 'list' or 'form'
$item = null;     // item shown in the form (edit or add)
$genericError = 'The changes could not be saved. Please try again.';

// ---------- Handle form submissions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_admin'], (string)($_POST['csrf'] ?? ''))) {
        redirect_to('status=csrf');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_title') {
        $title = trim((string)($_POST['page_title'] ?? ''));
        if ($title === '') {
            $errors[] = 'The page title cannot be empty.';
        } elseif (mb_strlen($title) > PAGE_TITLE_MAX) {
            $errors[] = 'The page title is too long (max ' . PAGE_TITLE_MAX . ' characters).';
        } else {
            try {
                // The title is stored on every row; keep them all in sync.
                $stmt = $conn->prepare('UPDATE resume SET main_title_1 = :title WHERE language = :language');
                $stmt->execute([':title' => $title, ':language' => RESUME_LANGUAGE]);
                redirect_to('status=title');
            } catch (Throwable $ex) {
                error_log('admin/resume.php title update failed: ' . $ex->getMessage());
                $errors[] = $genericError;
            }
        }
    } elseif ($action === 'save_item') {
        $id = (int)($_POST['id'] ?? 0);
        $categoria = (string)($_POST['categoria'] ?? '');

        // Keep the posted values so the form can be shown again on error.
        $item = ['id' => $id, 'categoria' => $categoria];
        foreach ($itemFields as $column => [$label, $type, $max, $required]) {
            $value = $_POST[$column] ?? '';
            $value = is_string($value) ? trim($value) : '';
            if ($required && $value === '') {
                $errors[] = "\"$label\" is required.";
            } elseif (mb_strlen($value) > $max) {
                $errors[] = "\"$label\" is too long (max $max characters).";
            }
            $item[$column] = $value;
        }
        if (!in_array($categoria, CATEGORIES, true)) {
            $errors[] = 'Please choose a valid section.';
        }
        $view = 'form';

        if (!$errors) {
            $params = [':categoria' => $categoria, ':language' => RESUME_LANGUAGE];
            foreach (array_keys($itemFields) as $column) {
                $params[":$column"] = $item[$column];
            }

            try {
                if ($id > 0) {
                    if (!item_exists($conn, $id)) {
                        redirect_to('status=notfound');
                    }
                    $params[':id'] = $id;
                    $stmt = $conn->prepare(
                        'UPDATE resume
                         SET categoria = :categoria, data_ref = :data_ref, local_ref = :local_ref,
                             title_1 = :title_1, text_1 = :text_1
                         WHERE id = :id AND language = :language'
                    );
                    $stmt->execute($params);
                    redirect_to('status=saved');
                } else {
                    // New items get the same page title as the existing ones.
                    $params[':main_title_1'] = load_page_title($conn);
                    $stmt = $conn->prepare(
                        'INSERT INTO resume (main_title_1, data_ref, local_ref, title_1, text_1, categoria, language)
                         VALUES (:main_title_1, :data_ref, :local_ref, :title_1, :text_1, :categoria, :language)'
                    );
                    $stmt->execute($params);
                    redirect_to('status=added');
                }
            } catch (Throwable $ex) {
                error_log('admin/resume.php item save failed: ' . $ex->getMessage());
                $errors[] = $genericError;
            }
        }
    } elseif ($action === 'delete_item') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $conn->prepare('DELETE FROM resume WHERE id = :id AND language = :language');
            $stmt->execute([':id' => $id, ':language' => RESUME_LANGUAGE]);
            redirect_to($stmt->rowCount() > 0 ? 'status=deleted' : 'status=notfound');
        } catch (Throwable $ex) {
            error_log('admin/resume.php delete failed: ' . $ex->getMessage());
            $errors[] = 'The item could not be deleted. Please try again.';
        }
    } else {
        redirect_to('');
    }
}

// ---------- Decide what to show (GET) ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = (string)($_GET['action'] ?? '');

    if ($action === 'edit') {
        $id = (int)($_GET['id'] ?? 0);
        try {
            $stmt = $conn->prepare(
                'SELECT id, categoria, data_ref, local_ref, title_1, text_1
                 FROM resume WHERE id = :id AND language = :language'
            );
            $stmt->execute([':id' => $id, ':language' => RESUME_LANGUAGE]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $ex) {
            error_log('admin/resume.php item load failed: ' . $ex->getMessage());
            $item = null;
        }
        if ($item === null) {
            redirect_to('status=notfound');
        }
        $view = 'form';
    } elseif ($action === 'add') {
        $categoria = (string)($_GET['categoria'] ?? '');
        $item = [
            'id' => 0,
            'categoria' => in_array($categoria, CATEGORIES, true) ? $categoria : CATEGORIES[0],
        ];
        foreach (array_keys($itemFields) as $column) {
            $item[$column] = '';
        }
        $view = 'form';
    }
}

// ---------- Load data for the list view ----------
$pageTitle = '';
$groups = array_fill_keys(CATEGORIES, []);
$loadFailed = false;

if ($view === 'list') {
    try {
        $pageTitle = load_page_title($conn);
        // Same order as the public site.
        $stmt = $conn->prepare(
            'SELECT id, categoria, data_ref, local_ref, title_1
             FROM resume WHERE language = :language
             ORDER BY data_ref DESC'
        );
        $stmt->execute([':language' => RESUME_LANGUAGE]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $groups[$row['categoria']][] = $row;
        }
    } catch (Throwable $ex) {
        error_log('admin/resume.php list load failed: ' . $ex->getMessage());
        $loadFailed = true;
    }
}

$status = (string)($_GET['status'] ?? '');
$statusMessages = [
    'saved'    => ['success', 'Item saved. The change is already live on the site.'],
    'added'    => ['success', 'Item added. It is already live on the site.'],
    'deleted'  => ['success', 'Item deleted.'],
    'title'    => ['success', 'Page title saved.'],
    'notfound' => ['warning', 'That item was not found. It may have been deleted already.'],
    'csrf'     => ['warning', 'Your session expired. Please make your changes again.'],
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
    <title>Edit Resume</title>

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

                            <?php if (!$errors && isset($statusMessages[$status])): ?>
                                <div class="alert alert-<?= e($statusMessages[$status][0]) ?>"><?= e($statusMessages[$status][1]) ?></div>
                            <?php endif; ?>

                            <?php if ($errors): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $error): ?>
                                        <div><?= e($error) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($view === 'form'): ?>
                                <!-- Add / edit item -->
                                <form method="post" action="resume.php" class="form-horizontal">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_admin']) ?>">
                                    <input type="hidden" name="action" value="save_item">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

                                    <div class="card">
                                        <div class="card-header">
                                            <strong><?= (int)$item['id'] > 0 ? 'Edit' : 'Add' ?></strong> resume item
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-3">
                                                <div class="col col-md-3">
                                                    <label for="categoria" class="form-control-label">Section</label>
                                                </div>
                                                <div class="col-12 col-md-9">
                                                    <select id="categoria" name="categoria" class="form-select">
                                                        <?php foreach (CATEGORIES as $category): ?>
                                                            <option value="<?= e($category) ?>" <?= $item['categoria'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <?php foreach ($itemFields as $column => [$label, $type, $max, $required, $placeholder]): ?>
                                                <div class="row mb-3">
                                                    <div class="col col-md-3">
                                                        <label for="<?= e($column) ?>" class="form-control-label"><?= e($label) ?><?= $required ? ' *' : '' ?></label>
                                                    </div>
                                                    <div class="col-12 col-md-9">
                                                        <?php if ($type === 'textarea'): ?>
                                                            <textarea
                                                                id="<?= e($column) ?>"
                                                                name="<?= e($column) ?>"
                                                                class="form-control"
                                                                rows="6"
                                                                maxlength="<?= (int)$max ?>"
                                                                <?= $required ? 'required' : '' ?>><?= e($item[$column] ?? '') ?></textarea>
                                                        <?php else: ?>
                                                            <input
                                                                type="text"
                                                                id="<?= e($column) ?>"
                                                                name="<?= e($column) ?>"
                                                                class="form-control"
                                                                maxlength="<?= (int)$max ?>"
                                                                placeholder="<?= e($placeholder) ?>"
                                                                value="<?= e($item[$column] ?? '') ?>"
                                                                <?= $required ? 'required' : '' ?>>
                                                        <?php endif; ?>
                                                        <?php if ($column === 'data_ref'): ?>
                                                            <small class="form-text text-muted">Items are listed by this field, newest first. Start it with the year so the order is right.</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>

                                            <small class="form-text text-muted">* Required. HTML tags (for example &lt;br&gt; or &lt;strong&gt;) are kept and rendered on the site.</small>
                                        </div>
                                        <div class="card-footer">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fa-solid fa-floppy-disk"></i> Save item
                                            </button>
                                            <a href="resume.php" class="btn btn-secondary btn-sm">
                                                <i class="fa-solid fa-ban"></i> Cancel
                                            </a>
                                        </div>
                                    </div>
                                </form>

                            <?php elseif ($loadFailed): ?>
                                <div class="alert alert-danger">The resume could not be loaded.</div>

                            <?php else: ?>
                                <!-- Page title -->
                                <form method="post" action="resume.php" class="form-horizontal">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_admin']) ?>">
                                    <input type="hidden" name="action" value="save_title">

                                    <div class="card">
                                        <div class="card-header">
                                            <strong>Resume</strong> page title
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-3">
                                                <div class="col col-md-3">
                                                    <label for="page_title" class="form-control-label">Page title *</label>
                                                </div>
                                                <div class="col-12 col-md-9">
                                                    <input
                                                        type="text"
                                                        id="page_title"
                                                        name="page_title"
                                                        class="form-control"
                                                        maxlength="<?= PAGE_TITLE_MAX ?>"
                                                        value="<?= e($pageTitle) ?>"
                                                        required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fa-solid fa-floppy-disk"></i> Save title
                                            </button>
                                            <a href="../#resume" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site
                                            </a>
                                        </div>
                                    </div>
                                </form>

                                <!-- Items by section -->
                                <?php foreach ($groups as $category => $rows): ?>
                                    <div class="card">
                                        <div class="card-header">
                                            <strong><?= e($category) ?></strong>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!$rows): ?>
                                                <div class="alert alert-info mb-0">No items in this section yet.</div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-borderless table-striped table-earning">
                                                        <thead>
                                                            <tr>
                                                                <th>Period</th>
                                                                <th>Institution / company</th>
                                                                <th>Title</th>
                                                                <th class="text-end">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($rows as $row): ?>
                                                                <tr>
                                                                    <td><?= e($row['data_ref']) ?></td>
                                                                    <td><?= e($row['local_ref']) ?></td>
                                                                    <td><?= e($row['title_1']) ?></td>
                                                                    <td class="text-end text-nowrap">
                                                                        <a href="resume.php?action=edit&amp;id=<?= (int)$row['id'] ?>" class="btn btn-primary btn-sm">
                                                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                                                        </a>
                                                                        <form method="post" action="resume.php" class="d-inline"
                                                                            onsubmit="return confirm('Delete this item? This cannot be undone.');">
                                                                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_admin']) ?>">
                                                                            <input type="hidden" name="action" value="delete_item">
                                                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                                <i class="fa-solid fa-trash"></i> Delete
                                                                            </button>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (in_array($category, CATEGORIES, true)): ?>
                                            <div class="card-footer">
                                                <a href="resume.php?action=add&amp;categoria=<?= urlencode($category) ?>" class="btn btn-success btn-sm">
                                                    <i class="fa-solid fa-plus"></i> Add <?= e(strtolower($category)) ?> item
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

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

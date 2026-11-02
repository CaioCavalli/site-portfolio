<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/dbconn.php';


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = $_SESSION['auth']['id'] ?? null;
if (!$userId) {
    http_response_code(403);
    exit('Usuário da sessão não identificado (id ausente).');
}

$stmt = $conn->prepare('SELECT name, email, password_hash FROM admin_users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    http_response_code(404);
    exit('Usuário não encontrado na base de dados.');
}

$currentName   = $account['name']  ?? '';
$currentEmail  = $account['email'] ?? '';
$currentHash   = $account['password_hash'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['csrf_account'])) {
    $_SESSION['csrf_account'] = bin2hex(random_bytes(32));
}

$flash = null;

function validar_forca_senha(string $pw): array
{
    $erros = [];
    if (strlen($pw) < 12) {
        $erros[] = 'Mínimo de 12 caracteres.';
    }
    if (!preg_match('/[A-Z]/', $pw)) {
        $erros[] = 'Inclua pelo menos 1 letra maiúscula.';
    }
    if (!preg_match('/[a-z]/', $pw)) {
        $erros[] = 'Inclua pelo menos 1 letra minúscula.';
    }
    if (!preg_match('/\d/', $pw)) {
        $erros[] = 'Inclua pelo menos 1 número.';
    }
    if (!preg_match('/[\W_]/', $pw)) {
        $erros[] = 'Inclua pelo menos 1 símbolo.';
    }
    if (preg_match('/\s/', $pw)) {
        $erros[] = 'Não use espaços.';
    }
    $ok = empty($erros);
    return [$ok, $ok ? 'forte' : 'fraca', $erros];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = hash_equals($_SESSION['csrf_account'] ?? '', $_POST['csrf'] ?? '');
    if (!$csrfOk) {
        $_SESSION['csrf_account'] = bin2hex(random_bytes(32));
        $flash = ['type' => 'danger', 'msg' => 'Falha de segurança (CSRF inválido). Tente novamente.'];
    } else {
        unset($_POST['username']);

        // --- EMAIL ---
        $rawEmail = trim($_POST['email'] ?? '');
        $newEmail = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
        if ($newEmail && strcasecmp($newEmail, $currentEmail) !== 0) {
            $q = $conn->prepare('SELECT 1 FROM admin_users WHERE email = :email AND id <> :id LIMIT 1');
            $q->execute([':email' => $newEmail, ':id' => $userId]);
            if ($q->fetchColumn()) {
                $flash = ['type' => 'warning', 'msg' => 'Este email já está em uso por outro usuário.'];
            } else {
                $upd = $conn->prepare('UPDATE admin_users SET email = :email WHERE id = :id');
                $upd->execute([':email' => $newEmail, ':id' => $userId]);
                $_SESSION['auth']['email'] = $newEmail;
                $currentEmail = $newEmail;
                $flash = ['type' => 'success', 'msg' => 'Email atualizado com sucesso.'];
            }
        }

        // --- SENHA ---
        $curPass  = trim((string)($_POST['current_password'] ?? ''));
        $rawPass  = trim((string)($_POST['password'] ?? ''));
        $rawPass2 = trim((string)($_POST['password_check'] ?? ''));

        $querTrocarSenha = ($curPass !== '' || $rawPass !== '' || $rawPass2 !== '');

        if ($querTrocarSenha) {
            if ($curPass === '' || $rawPass === '' || $rawPass2 === '') {
                $flash = ['type' => 'warning', 'msg' => 'Para trocar a senha, preencha senha atual, nova senha e confirmação.'];
            } elseif (!password_verify($curPass, $currentHash)) {
                $flash = ['type' => 'danger', 'msg' => 'Senha atual incorreta.'];
            } elseif (!hash_equals($rawPass, $rawPass2)) {
                $flash = ['type' => 'warning', 'msg' => 'As novas senhas não conferem.'];
            } elseif (password_verify($rawPass, $currentHash)) {
                $flash = ['type' => 'warning', 'msg' => 'A nova senha não pode ser igual à senha atual.'];
            } else {
                [$forte, $label, $errosSenha] = validar_forca_senha($rawPass);
                if (!$forte) {
                    $motivo = implode(' ', array_slice($errosSenha, 0, 2));
                    $flash = ['type' => 'warning', 'msg' => "Senha $label. $motivo"];
                } else {
                    $hash = defined('PASSWORD_ARGON2ID')
                        ? password_hash($rawPass, PASSWORD_ARGON2ID, ['memory_cost' => 1 << 17, 'time_cost' => 3, 'threads' => 2])
                        : password_hash($rawPass, PASSWORD_DEFAULT);

                    if ($hash === false) {
                        $flash = ['type' => 'danger', 'msg' => 'Falha ao gerar o hash da nova senha.'];
                    } else {
                        $updPw = $conn->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id');
                        $updPw->execute([':hash' => $hash, ':id' => $userId]);
                        $flash = ['type' => 'success', 'msg' => 'Senha atualizada com sucesso.'];
                    }
                }
            }
        }
        $_SESSION['csrf_account'] = bin2hex(random_bytes(32));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_POST['username']);
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
                                <div class="card-header">Account Settings</div>
                                <div class="card-body card-body">
                                    <?php if (!empty($flash)): ?>
                                        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> mb-3" role="alert"> <?= htmlspecialchars($flash['msg']) ?> </div>
                                    <?php endif; ?>
                                    <form action="" method="post" class="">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_account'] ?? '', ENT_QUOTES) ?>">
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <div class="input-group-addon">
                                                    <i class="fa-solid fa-user"></i>
                                                </div>
                                                <input type="text" id="username" placeholder="Username" class="form-control" value="<?= htmlspecialchars($currentName, ENT_QUOTES) ?>" readonly aria-readonly="true" />
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <div class="input-group-addon">
                                                    <i class="fa-solid fa-envelope"></i>
                                                </div>
                                                <input type="email" id="email" name="email" placeholder="Email" class="form-control" value="<?= htmlspecialchars($currentEmail, ENT_QUOTES) ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <div class="input-group-addon">
                                                    <i class="fa-solid fa-lock"></i>
                                                </div>
                                                <input type="password" id="current_password" name="current_password" placeholder="Current Password" class="form-control" autocomplete="current-password">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <div class="input-group-addon">
                                                    <i class="fa-solid fa-asterisk"></i>
                                                </div>
                                                <input type="password" id="password" name="password" placeholder="New Password" class="form-control" autocomplete="new-password">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <div class="input-group-addon">
                                                    <i class="fa-solid fa-asterisk"></i>
                                                </div>
                                                <input type="password" id="password_check" name="password_check" placeholder="Check New Password" class="form-control" autocomplete="new-password">
                                            </div>
                                            <div id="pw-hints" class="d-none">
                                                <div class="form-text mt-2">
                                                    Use no mínimo 12 caracteres, com maiúscula, minúscula, número e símbolo.
                                                </div>

                                                <div class="mt-2" id="pw-strength" aria-live="polite">
                                                    <div class="progress" style="height:8px;">
                                                        <div class="progress-bar" id="pw-bar" role="progressbar" style="width:0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <small id="pw-label" class="text-muted d-block mt-1">Força: —</small>
                                                </div>

                                                <ul class="list-unstyled small mt-2 mb-0" id="pw-requirements">
                                                    <li data-req="len">• Mínimo de 12 caracteres</li>
                                                    <li data-req="upper">• Pelo menos 1 maiúscula</li>
                                                    <li data-req="lower">• Pelo menos 1 minúscula</li>
                                                    <li data-req="digit">• Pelo menos 1 número</li>
                                                    <li data-req="symbol">• Pelo menos 1 símbolo</li>
                                                    <li data-req="space">• Sem espaços</li>
                                                </ul>

                                                <div class="small mt-1" id="pw-match" aria-live="polite"></div>
                                            </div>
                                        </div>
                                        <div class="form-actions form-group">
                                            <button type="submit" class="btn btn-success btn-sm">Submit</button>
                                        </div>
                                </div>
                                </form>
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

    <!-- Bar Progress Password-->
    <script>
        (function() {
            const pw = document.getElementById('password');
            const pw2 = document.getElementById('password_check');
            const hints = document.getElementById('pw-hints'); // << novo
            const bar = document.getElementById('pw-bar');
            const label = document.getElementById('pw-label');
            const reqList = document.getElementById('pw-requirements');
            const matchEl = document.getElementById('pw-match');
            if (!pw || !bar || !label || !reqList || !hints) return;

            function checkRequirements(v) {
                return {
                    len: v.length >= 12,
                    upper: /[A-Z]/.test(v),
                    lower: /[a-z]/.test(v),
                    digit: /\d/.test(v),
                    symbol: /[\W_]/.test(v),
                    space: !/\s/.test(v),
                };
            }

            function scorePassword(v) {
                const r = checkRequirements(v);
                let score = 0;
                if (r.len) score++;
                if (r.upper) score++;
                if (r.lower) score++;
                if (r.digit) score++;
                if (r.symbol) score++;
                if (r.space) score++;
                return {
                    score,
                    req: r
                };
            }

            function setReqClass(li, ok) {
                li.classList.toggle('text-success', ok);
                li.classList.toggle('text-danger', !ok);
            }

            function renderStrength(v) {
                // Mostra/esconde TODO o bloco de dicas
                const show = v.length > 0; // só quando digitar no New Password
                hints.classList.toggle('d-none', !show);

                const {
                    score,
                    req
                } = scorePassword(v);

                // Atualiza lista de requisitos
                reqList.querySelectorAll('li').forEach(li => {
                    const key = li.getAttribute('data-req');
                    setReqClass(li, !!req[key]);
                });

                // Barra/label
                const percent = Math.round((score / 6) * 100);
                bar.style.width = percent + '%';
                bar.setAttribute('aria-valuenow', String(percent));

                let cls = 'bg-danger',
                    txt = 'Muito fraca';
                if (percent >= 80) {
                    cls = 'bg-success';
                    txt = 'Muito forte';
                } else if (percent >= 60) {
                    cls = 'bg-success';
                    txt = 'Forte';
                } else if (percent >= 40) {
                    cls = 'bg-info';
                    txt = 'Média';
                } else if (percent >= 20) {
                    cls = 'bg-warning';
                    txt = 'Fraca';
                }

                bar.classList.remove('bg-danger', 'bg-warning', 'bg-info', 'bg-success');
                bar.classList.add(cls);
                label.textContent = 'Força: ' + txt;

                // Se apagar tudo, também limpamos o "conferem"
                if (!show) {
                    matchEl.textContent = '';
                    matchEl.classList.remove('text-success', 'text-danger');
                } else if (pw2 && pw2.value.length > 0) {
                    renderMatch();
                }
            }

            function renderMatch() {
                if (!pw2) return;
                if (pw.value === '' && pw2.value === '') {
                    matchEl.textContent = '';
                    matchEl.classList.remove('text-success', 'text-danger');
                    return;
                }
                if (pw.value === pw2.value) {
                    matchEl.textContent = 'As senhas conferem.';
                    matchEl.classList.add('text-success');
                    matchEl.classList.remove('text-danger');
                } else {
                    matchEl.textContent = 'As senhas não conferem.';
                    matchEl.classList.add('text-danger');
                    matchEl.classList.remove('text-success');
                }
            }

            pw.addEventListener('input', e => renderStrength(e.target.value));
            if (pw2) pw2.addEventListener('input', renderMatch);

            // Estado inicial (esconde se estiver vazio)
            renderStrength(pw.value || '');
        })();
    </script>

</body>

</html>
<!-- end document-->
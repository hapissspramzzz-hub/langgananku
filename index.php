<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
    }
    session_destroy();
    header('Location: index.php'); exit;
}
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'login') {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $pass = $_POST['password'] ?? '';
        $stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([$email]); $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id']; $_SESSION['role'] = $user['role'];
            $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
            header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'index.php')); exit;
        }
        $error_msg = 'Email atau password salah.';
    } elseif (($_POST['action'] ?? '') === 'register') {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $name = trim($_POST['name'] ?? ''); $pass = $_POST['password'] ?? '';
        if (!$email || $name === '' || mb_strlen($name) > 100 || strlen($pass) < 6) {
            $error_msg = 'Isi nama dan email yang valid. Password minimal 6 karakter.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $stmt->execute([$email]);
            if ($stmt->fetch()) $error_msg = 'Email sudah terdaftar. Silakan masuk.';
            else {
                $stmt = $pdo->prepare('INSERT INTO users (email, name, password_hash, data_version) VALUES (?, ?, ?, 0)');
                $stmt->execute([$email, $name, password_hash($pass, PASSWORD_DEFAULT)]);
                session_regenerate_id(true); $_SESSION['user_id'] = (int) $pdo->lastInsertId(); $_SESSION['role'] = 'user';
                header('Location: index.php'); exit;
            }
        }
    }
}
$isRegister = ($_GET['mode'] ?? '') === 'register'; $loggedIn = isset($_SESSION['user_id']);
$userName = ''; $userRole = $_SESSION['role'] ?? 'user';
if ($loggedIn) {
    $stmt = $pdo->prepare('SELECT name, role FROM users WHERE id = ?'); $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch();
    if (!$profile) { session_destroy(); header('Location: index.php'); exit; }
    $userName = $profile['name']; $userRole = $profile['role']; $_SESSION['role'] = $userRole;
}
function h($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="csrf-token" content="<?= h($_SESSION['csrf_token']) ?>">
<title>LanggananKu</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><script src="https://unpkg.com/@phosphor-icons/web"></script><link rel="stylesheet" href="syle.css"></head><body>
<?php if (!$loggedIn): ?>
<main class="auth-layout"><aside class="auth-aside"><a class="brand" href="index.php">Langganan<span>Ku</span></a><div><p class="eyebrow">KELOLA DENGAN TENANG</p><h2>Semua langgananmu, tercatat rapi.</h2><p>Pantau biaya dan dapatkan pengingat sebelum waktu perpanjangan.</p></div><span class="aside-note">Dashboard pribadi yang sederhana.</span></aside><section id="authScreen"><div class="auth-box"><span class="auth-kicker"><?= $isRegister ? 'BUAT AKUN BARU' : 'SELAMAT DATANG KEMBALI' ?></span><h1><?= $isRegister ? 'Buat akun' : 'Masuk ke akun' ?></h1><p class="auth-description"><?= $isRegister ? 'Mulai kelola pengeluaran langgananmu.' : 'Lanjutkan ke dashboard LanggananKu.' ?></p>
<?php if ($error_msg): ?><div class="error-msg" role="alert"><?= h($error_msg) ?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="action" value="<?= $isRegister ? 'register' : 'login' ?>"><?php if ($isRegister): ?><label for="name">Nama</label><input id="name" name="name" type="text" maxlength="100" placeholder="Nama lengkap" autocomplete="name" required><?php endif; ?><label for="email">Email</label><input id="email" name="email" type="email" placeholder="nama@email.com" autocomplete="email" required><label for="password">Password</label><input id="password" name="password" type="password" minlength="6" placeholder="Minimal 6 karakter" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" required><button class="btn-primary auth-submit" type="submit"><?= $isRegister ? 'Daftar akun' : 'Masuk' ?></button></form>
<?php if ($isRegister): ?><p class="auth-switch">Sudah punya akun? <a href="index.php">Masuk</a></p><?php else: ?><p class="auth-switch">Belum punya akun? <a href="index.php?mode=register">Daftar sekarang</a></p><?php endif; ?><a class="site-link" href="index.php">Lihat halaman LanggananKu</a></div></section></main>
<?php else: ?>
<main class="container"><header class="app-header"><div><a class="brand" href="index.php">Langganan<span>Ku</span></a><p class="welcome">Halo, <?= h($userName) ?>. Ini ringkasan langgananmu.</p></div><div class="toolbar">
<?php if ($userRole === 'admin'): ?><a class="btn btn-secondary" href="admin.php"><i class="ph ph-shield-check"></i> Panel admin</a><?php endif; ?><a class="btn btn-secondary" href="export.php"><i class="ph ph-download-simple"></i> Ekspor</a><button type="button" class="btn btn-secondary" id="enableNotifications"><i class="ph ph-bell"></i> Aktifkan notifikasi</button><a class="btn btn-primary" href="tambah.php"><i class="ph ph-plus"></i> Tambah langganan</a><a class="btn btn-secondary" href="index.php?logout=1"><i class="ph ph-sign-out"></i> Logout</a></div></header>
<section class="summary"><article class="card"><span class="label">Pengeluaran per bulan</span><div class="card-icon primary"><i class="ph ph-wallet"></i></div><div class="value primary" id="totalMonthly">Rp 0</div></article><article class="card"><span class="label">Perkiraan per tahun</span><div class="card-icon success"><i class="ph ph-trend-up"></i></div><div class="value success" id="totalYearly">Rp 0</div></article><article class="card"><span class="label">Langganan aktif</span><div class="card-icon info"><i class="ph ph-television"></i></div><div class="value" id="activeCount">0</div></article></section>
<section class="table-container"><div class="table-heading"><div><h2>Langganan saya</h2><p>Pengingat browser aktif selama halaman ini terbuka.</p></div></div><div class="table-header"><span>Nama langganan</span><span>Status</span><span>Perpanjangan</span><span class="text-right">Harga</span><span class="text-right">Aksi</span></div><div id="list" aria-live="polite"></div></section><footer class="app-footer"><a href="index.php">Lihat website</a><span>LanggananKu - Kelola langgananmu dengan mudah</span></footer></main>
<div id="toastRoot" class="toast-root" aria-live="polite"></div><script src="app.js"></script>
<?php endif; ?></body></html>

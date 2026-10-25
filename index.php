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
$isRegister = ($_GET['mode'] ?? '') === 'register';
$showAuth = in_array($_GET['mode'] ?? '', ['login', 'register'], true);
$loggedIn = isset($_SESSION['user_id']);
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
<title>LanggananKu</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><script src="https://unpkg.com/@phosphor-icons/web"></script><link rel="stylesheet" href="syle.css"><script src="toggle-password.js" defer></script></head><body class="<?= !$loggedIn && !$showAuth ? 'landing-page' : '' ?>">
<?php if (!$loggedIn): ?>
<?php if ($showAuth): ?>
<main class="auth-layout"><aside class="auth-aside"><a class="brand" href="index.php">Langganan<span>Ku</span></a><div><p class="eyebrow">KELOLA DENGAN TENANG</p><h2>Semua langgananmu, tercatat rapi.</h2><p>Pantau biaya dan dapatkan pengingat sebelum waktu perpanjangan.</p></div><span class="aside-note">Dashboard pribadi yang sederhana.</span></aside><section id="authScreen"><div class="auth-box"><span class="auth-kicker"><?= $isRegister ? 'BUAT AKUN BARU' : 'SELAMAT DATANG KEMBALI' ?></span><h1><?= $isRegister ? 'Buat akun' : 'Masuk ke akun' ?></h1><p class="auth-description"><?= $isRegister ? 'Mulai kelola pengeluaran langgananmu.' : 'Lanjutkan ke dashboard LanggananKu.' ?></p>
<?php if ($error_msg): ?><div class="error-msg" role="alert"><?= h($error_msg) ?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="action" value="<?= $isRegister ? 'register' : 'login' ?>"><?php if ($isRegister): ?><label for="name">Nama</label><input id="name" name="name" type="text" maxlength="100" placeholder="Nama lengkap" autocomplete="name" required><?php endif; ?><label for="email">Email</label><input id="email" name="email" type="email" placeholder="nama@email.com" autocomplete="email" required><label for="password">Password</label><div class="auth-password-field"><input id="password" name="password" type="password" minlength="6" placeholder="Minimal 6 karakter" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" aria-describedby="togglePassword" required><button class="password-toggle" id="togglePassword" type="button" aria-controls="password" aria-pressed="false">Lihat</button></div><button class="btn-primary auth-submit" type="submit"><?= $isRegister ? 'Daftar akun' : 'Masuk' ?></button></form>
<?php if ($isRegister): ?><p class="auth-switch">Sudah punya akun? <a href="index.php?mode=login">Masuk</a></p><?php else: ?><p class="auth-switch">Belum punya akun? <a href="index.php?mode=register">Daftar sekarang</a></p><?php endif; ?><a class="site-link" href="index.php">Kembali ke halaman utama</a></div></section></main>
<?php else: ?>
<main class="landing-container">
    <header class="landing-header">
        <a class="brand" href="index.php">Langganan<span>Ku</span></a>
        <nav class="landing-nav" aria-label="Navigasi utama">
            <a href="#fitur">Fitur</a>
            <a href="#cara-kerja">Cara kerja</a>
            <a class="btn btn-secondary" href="index.php?mode=login">Masuk</a>
            <a class="btn btn-primary" href="index.php?mode=register">Mulai gratis <i class="ph ph-arrow-right"></i></a>
        </nav>
    </header>
    <section class="landing-hero">
        <div class="hero-copy">
            <span class="hero-badge"><i class="ph ph-sparkle"></i> Langganan rapi, pikiran lebih tenang</span>
            <h1>Langganan banyak,<br><span>nggak perlu</span><br>pusing ngaturnya.</h1>
            <p>Catat semua layanan langgananmu di satu tempat. Pantau pengeluaran, cek jadwal perpanjangan, dan dapatkan pengingat sebelum waktunya.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="index.php?mode=register">Buat akun gratis <i class="ph ph-arrow-right"></i></a>
                <a class="hero-login-link" href="index.php?mode=login">Sudah punya akun? Masuk</a>
            </div>
            <div class="hero-points"><span><i class="ph ph-check-circle"></i> Mudah digunakan</span><span><i class="ph ph-check-circle"></i> Data tersimpan pribadi</span></div>
        </div>
        <div class="hero-visual" aria-label="Contoh tampilan ringkasan LanggananKu">
            <div class="visual-glow"></div>
            <div class="preview-card">
                <div class="preview-top"><div><span class="preview-overline">DASHBOARD</span><h2>Ringkasan kamu</h2></div><span class="preview-avatar">K</span></div>
                <div class="preview-total"><span>Total pengeluaran per bulan</span><strong>Rp 428.000</strong><span class="preview-trend"><i class="ph ph-trend-up"></i> Semua langganan terpantau</span></div>
                <div class="preview-list-title"><strong>Langganan aktif</strong><span>3 layanan</span></div>
                <div class="preview-subscription"><span class="service-icon service-purple"><i class="ph ph-play"></i></span><span class="service-name"><strong>Streaming</strong><small>Perpanjangan 12 Okt</small></span><strong class="service-price">Rp 149.000</strong></div>
                <div class="preview-subscription"><span class="service-icon service-green"><i class="ph ph-music-notes"></i></span><span class="service-name"><strong>Musik</strong><small>Perpanjangan 18 Okt</small></span><strong class="service-price">Rp 55.000</strong></div>
                <div class="preview-subscription"><span class="service-icon service-orange"><i class="ph ph-cloud"></i></span><span class="service-name"><strong>Penyimpanan cloud</strong><small>Perpanjangan 24 Okt</small></span><strong class="service-price">Rp 224.000</strong></div>
            </div>
            <div class="reminder-note"><span class="reminder-icon"><i class="ph ph-bell-ringing"></i></span><span><strong>Pengingat aktif</strong><small>Perpanjangan makin dekat</small></span><i class="ph ph-check-circle reminder-check"></i></div>
        </div>
    </section>
    <section class="features-section" id="fitur">
        <div class="section-heading"><span class="eyebrow">SEMUA DALAM SATU TEMPAT</span><h2>Lebih teratur, tanpa ribet.</h2><p>Informasi penting soal langgananmu jadi gampang dilihat dan dikelola.</p></div>
        <div class="feature-grid">
            <article class="feature-card"><span class="feature-icon feature-violet"><i class="ph ph-wallet"></i></span><h3>Pantau pengeluaran</h3><p>Lihat total biaya langganan bulanan dan perkiraan pengeluaran tahunan dalam satu ringkasan.</p></article>
            <article class="feature-card"><span class="feature-icon feature-blue"><i class="ph ph-calendar-check"></i></span><h3>Ingat jadwal perpanjangan</h3><p>Catat tanggal perpanjangan dan aktifkan pengingat browser agar tidak terlewat.</p></article>
            <article class="feature-card"><span class="feature-icon feature-green"><i class="ph ph-sliders-horizontal"></i></span><h3>Kelola dengan mudah</h3><p>Tambah, ubah, dan atur status langgananmu kapan saja dari dashboard pribadi.</p></article>
        </div>
    </section>
    <section class="steps-section" id="cara-kerja">
        <div class="section-heading"><span class="eyebrow">MULAI DALAM BEBERAPA LANGKAH</span><h2>Gampang dari awal.</h2></div>
        <div class="steps-grid">
            <article class="step-card"><span>01</span><div><h3>Buat akun</h3><p>Daftar untuk punya ruang pribadi mengelola semua langgananmu.</p></div></article>
            <article class="step-card"><span>02</span><div><h3>Catat langganan</h3><p>Tambahkan nama layanan, harga, dan tanggal perpanjangannya.</p></div></article>
            <article class="step-card"><span>03</span><div><h3>Pantau semuanya</h3><p>Cek ringkasan biaya dan jadwal perpanjangan lewat dashboard.</p></div></article>
        </div>
    </section>
    <section class="landing-cta"><div><span class="eyebrow">SIAP MULAI?</span><h2>Biar LanggananKu yang bantu mengingat.</h2><p>Mulai rapikan langgananmu sekarang.</p></div><a class="btn btn-primary" href="index.php?mode=register">Daftar sekarang <i class="ph ph-arrow-right"></i></a></section>
    <footer class="landing-footer"><a class="brand" href="index.php">Langganan<span>Ku</span></a><span>Kelola langgananmu dengan lebih mudah.</span><span class="footer-credit">by hapisss pramzzzzzzzzzzzzzzzzzzzzzzzzzzz</span></footer>
</main>
<?php endif; ?>
<?php else: ?>
<main class="container"><header class="app-header"><div><a class="brand" href="index.php">Langganan<span>Ku</span></a><p class="welcome">Halo, <?= h($userName) ?>. Ini ringkasan langgananmu.</p></div><div class="toolbar">
<?php if ($userRole === 'admin'): ?><a class="btn btn-secondary" href="admin.php"><i class="ph ph-shield-check"></i> Panel admin</a><?php endif; ?><a class="btn btn-secondary" href="impor.php"><i class="ph ph-upload-simple"></i> Impor</a><a class="btn btn-secondary" href="export.php"><i class="ph ph-download-simple"></i> Ekspor</a><a class="btn btn-primary" href="tambah.php"><i class="ph ph-plus"></i> Tambah langganan</a><button type="button" class="btn btn-secondary" id="enableNotifications"><i class="ph ph-bell"></i> Aktifkan notifikasi</button><a class="btn btn-secondary" href="index.php?logout=1"><i class="ph ph-sign-out"></i> Logout</a></div></header>
<section class="summary"><article class="card"><span class="label">Pengeluaran per bulan</span><div class="card-icon primary"><i class="ph ph-wallet"></i></div><div class="value primary" id="totalMonthly">Rp 0</div></article><article class="card"><span class="label">Perkiraan per tahun</span><div class="card-icon success"><i class="ph ph-trend-up"></i></div><div class="value success" id="totalYearly">Rp 0</div></article><article class="card"><span class="label">Langganan aktif</span><div class="card-icon info"><i class="ph ph-television"></i></div><div class="value" id="activeCount">0</div></article></section>
<section class="table-container"><div class="table-heading"><div><h2>Langganan saya</h2><p>Pengingat browser 24 jam sebelum perpanjangan aktif selama halaman ini terbuka.</p></div></div><div class="table-header"><span>Nama langganan</span><span>Status</span><span>Perpanjangan</span><span class="text-right">Harga</span><span class="text-right">Aksi</span></div><div id="list" aria-live="polite"></div></section><footer class="app-footer"><a href="index.php">Lihat website</a><span>LanggananKu - Kelola langgananmu dengan mudah</span></footer></main>
<div id="toastRoot" class="toast-root" aria-live="polite"></div><script src="app.js"></script>
<?php endif; ?></body></html>

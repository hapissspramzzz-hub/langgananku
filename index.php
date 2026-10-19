<?php
session_start();
require_once 'db.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $pass = $_POST['password'] ?? '';
        $stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'index.php'));
            exit;
        }
        $error_msg = 'Email atau password salah!';
    }
    if ($action === 'register') {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $name = trim($_POST['name'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (!$email || $name === '' || strlen($pass) < 6) {
            $error_msg = 'Nama wajib diisi, email harus valid, dan password minimal 6 karakter';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_msg = 'Email sudah terdaftar!';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)');
                $stmt->execute([$email, $name, $hash]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['role'] = 'user';
                header('Location: index.php');
                exit;
            }
        }
    }
}
$isRegister = isset($_GET['mode']) && $_GET['mode'] === 'register';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LanggananKu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<link rel="stylesheet" href="syle.css">
</head>
<body>
<?php if (!isset($_SESSION['user_id'])): ?>
<div id="authScreen"><div class="auth-box"><h1>Langganan<span>Ku</span></h1><p style="margin-bottom:20px;color:var(--text-muted)">Silakan masuk atau daftar.</p>
<?php if ($error_msg): ?><div class="error-msg"><?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="action" value="<?= $isRegister ? 'register' : 'login' ?>"><?php if ($isRegister): ?><input type="text" name="name" placeholder="Nama lengkap" required><?php endif; ?><input type="email" name="email" placeholder="Email Anda" required><input type="password" name="password" placeholder="Password (min. 6 karakter)" required><button type="submit" class="btn-primary"><?= $isRegister ? 'Daftar Akun' : 'Masuk' ?></button></form>
<?php if ($isRegister): ?><a href="index.php" class="auth-toggle">Sudah punya akun? Masuk di sini</a><?php else: ?><a href="index.php?mode=register" class="auth-toggle">Belum punya akun? Daftar di sini</a><?php endif; ?>
</div></div>
<?php else: ?>
<div class="container"><header><h1>Langganan<span>Ku</span></h1><div class="toolbar">
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a class="btn btn-secondary" href="admin.php"><i class="ph ph-shield-check"></i> Admin</a><?php endif; ?>
<button class="btn-primary" onclick="document.getElementById('modalForm').classList.add('active')"><i class="ph ph-plus"></i> Tambah</button><a href="index.php?logout=1" class="btn btn-secondary" onclick="return confirm('Yakin ingin logout?')"><i class="ph ph-sign-out"></i> Logout</a></div></header>
<div class="summary"><div class="card"><span class="label">Total Bulanan</span><div class="card-icon primary"><i class="ph ph-wallet"></i></div><div class="value primary" id="totalMonthly">Rp 0</div></div><div class="card"><span class="label">Total Tahunan</span><div class="card-icon success"><i class="ph ph-trend-up"></i></div><div class="value success" id="totalYearly">Rp 0</div></div><div class="card"><span class="label">Langganan Aktif</span><div class="card-icon info"><i class="ph ph-television"></i></div><div class="value" id="activeCount">0</div></div></div>
<div class="table-container"><div class="table-header"><span>Nama Langganan</span><span>Status</span><span>Pengingat</span><span class="text-right">Harga / Bulan</span><span class="text-right">Aksi</span></div><div id="list"></div></div></div>
<div class="modal-overlay" id="modalForm"><div class="modal"><h2>Tambah Langganan</h2><form id="formSubscription"><div class="form-group"><label>Nama Layanan *</label><input type="text" id="subName" required placeholder="Netflix"></div><div class="form-row"><div class="form-group"><label>Harga *</label><input type="number" id="subPrice" required min="0" placeholder="50000"></div><div class="form-group"><label>Siklus *</label><select id="subCycle"><option value="monthly">Bulanan</option><option value="yearly">Tahunan</option></select></div></div><div class="form-group"><label>Tanggal Renewal</label><input type="date" id="subRenewal"></div><div class="modal-actions"><button type="button" class="btn-secondary" onclick="document.getElementById('modalForm').classList.remove('active')">Batal</button><button type="submit" class="btn-primary">Simpan</button></div></form></div></div>
<script src="app.js"></script>
<?php endif; ?>
</body></html>



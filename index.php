<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'db.php';

// ==========================================
// 1. LOGIKA BACKEND (FULL PHP)
// ==========================================
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // LOGIN
    if ($action === 'login') {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $pass = $_POST['password'];
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: index.php");
            exit;
        } else {
            $error_msg = 'Email atau password salah!';
        }
    }

    // REGISTER
    if ($action === 'register') {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $pass = $_POST['password'];
        if (!$email || strlen($pass) < 6) {
            $error_msg = 'Email tidak valid / password minimal 6 karakter';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_msg = 'Email sudah terdaftar!';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                $stmt->execute([$email, $hash]);
                $_SESSION['user_id'] = $pdo->lastInsertId();
                header("Location: index.php");
                exit;
            }
        }
    }

    // TAMBAH DATA
    if ($action === 'add_sub' && isset($_SESSION['user_id'])) {
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $cycle = $_POST['cycle'];
        $renewal = !empty($_POST['renewal']) ? $_POST['renewal'] : null;
        $id = uniqid();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("INSERT INTO user_subscriptions (id, user_id, name, price, cycle, renewal_date, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)");
        $stmt->execute([$id, $_SESSION['user_id'], $name, $price, $cycle, $renewal, $now, $now]);
        header("Location: index.php");
        exit;
    }
}

// HAPUS DATA
if (isset($_GET['delete_id']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("DELETE FROM user_subscriptions WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['delete_id'], $_SESSION['user_id']]);
    header("Location: index.php");
    exit;
}

// ==========================================
// 2. AMBIL DATA
// ==========================================
$subscriptions = [];
$totalMonthly = 0;
$totalYearly = 0;
$activeCount = 0;

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM user_subscriptions WHERE user_id = ? ORDER BY renewal_date ASC");
    $stmt->execute([$_SESSION['user_id']]);
    $subscriptions = $stmt->fetchAll();

    foreach ($subscriptions as $sub) {
        if ($sub['active'] == 1) {
            $activeCount++;
            if ($sub['cycle'] === 'monthly') {
                $totalMonthly += $sub['price'];
                $totalYearly += $sub['price'] * 12;
            } elseif ($sub['cycle'] === 'yearly') {
                $totalMonthly += $sub['price'] / 12;
                $totalYearly += $sub['price'];
            }
        }
    }
}

function formatIDR($number) { return 'Rp ' . number_format($number, 0, ',', '.'); }
$isRegister = isset($_GET['mode']) && $_GET['mode'] === 'register';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LanggananKu</title>
<!-- Font & Ikon -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<!-- Panggil File CSS -->
<link rel="stylesheet" href="style.css">
</head>
<body>

<?php if (!isset($_SESSION['user_id'])): ?>
    <!-- HALAMAN LOGIN / REGISTER -->
    <div id="authScreen">
        <div class="auth-box">
            <h1>Langganan<span>Ku</span></h1>
            <p style="margin-bottom: 20px; color: var(--text-muted);">Silakan masuk atau daftar.</p>
            
            <?php if ($error_msg): ?><div class="error-msg"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="<?= $isRegister ? 'register' : 'login' ?>">
                <input type="email" name="email" placeholder="Email Anda" required>
                <input type="password" name="password" placeholder="Password (min. 6 karakter)" required>
                <button type="submit" class="btn-primary"><?= $isRegister ? 'Daftar Akun' : 'Masuk' ?></button>
            </form>

            <?php if ($isRegister): ?>
                <a href="index.php" class="auth-toggle">Sudah punya akun? Masuk di sini</a>
            <?php else: ?>
                <a href="index.php?mode=register" class="auth-toggle">Belum punya akun? Daftar di sini</a>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- DASHBOARD -->
    <div class="container">
        <header>
            <h1>Langganan<span>Ku</span></h1>
            <div class="toolbar">
                <a href="export.php" class="btn btn-secondary"><i class="ph ph-download-simple"></i> Export</a>
                <button class="btn btn-secondary" onclick="document.getElementById('modalImport').classList.add('active')"><i class="ph ph-upload-simple"></i> Import</button>
                <button class="btn-primary" onclick="document.getElementById('modalForm').classList.add('active')"><i class="ph ph-plus"></i> Tambah</button>
                <a href="index.php?logout=1" class="btn btn-secondary" onclick="return confirm('Yakin ingin logout?')"><i class="ph ph-sign-out"></i> Logout</a>
            </div>
        </header>

        <div class="summary">
            <div class="card">
                <span class="label">Total Bulanan</span>
                <div class="card-icon primary"><i class="ph ph-wallet"></i></div>
                <div class="value primary"><?= formatIDR($totalMonthly) ?></div>
            </div>
            <div class="card">
                <span class="label">Total Tahunan</span>
                <div class="card-icon success"><i class="ph ph-trend-up"></i></div>
                <div class="value success"><?= formatIDR($totalYearly) ?></div>
            </div>
            <div class="card">
                <span class="label">Langganan Aktif</span>
                <div class="card-icon info"><i class="ph ph-television"></i></div>
                <div class="value"><?= $activeCount ?></div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <span>Nama Langganan</span>
                <span>Status</span>
                <span>Pengingat</span>
                <span class="text-right">Harga / Bulan</span>
                <span class="text-right">Aksi</span>
            </div>
            
            <?php if (empty($subscriptions)): ?>
                <div style="padding:30px; text-align:center; color:#666;">Belum ada data. Klik "Tambah".</div>
            <?php else: ?>
                <?php foreach ($subscriptions as $s): ?>
                    <?php 
                    $timerHtml = '<span style="color:#9ca3af; font-size:13px;">-</span>';
                    if (!empty($s['renewal_date'])) {
                        $today = new DateTime(date('Y-m-d'));
                        $target = new DateTime($s['renewal_date']);
                        $diff = (int)$today->diff($target)->format("%r%a");

                        if ($diff < 0) $timerHtml = '<span class="timer-badge timer-danger"><i class="ph ph-clock"></i> Terlewat ' . abs($diff) . ' hari</span>';
                        elseif ($diff == 0) $timerHtml = '<span class="timer-badge timer-danger"><i class="ph ph-clock"></i> Hari ini!</span>';
                        elseif ($diff <= 7) $timerHtml = '<span class="timer-badge timer-warning"><i class="ph ph-clock"></i> Sisa ' . $diff . ' hari</span>';
                        else $timerHtml = '<span class="timer-badge timer-normal"><i class="ph ph-clock"></i> ' . $diff . ' hari lagi</span>';
                    }
                    
                    $colors = ['#ef4444', '#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6'];
                    $color = $colors[strlen($s['name']) % 5];
                    ?>
                    <div class="list-item">
                        <div class="item-main">
                            <div class="item-logo" style="background-color: <?= $color ?>"><?= htmlspecialchars($s['name'][0]) ?></div>
                            <div>
                                <div class="item-name"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="item-meta"><?= $s['cycle'] === 'monthly' ? 'Bulanan' : 'Tahunan' ?></div>
                            </div>
                        </div>
                        <div><span class="status-badge">Aktif</span></div>
                        <div><?= $timerHtml ?></div>
                        <div class="item-price"><?= formatIDR($s['price']) ?></div>
                        <div><a href="index.php?delete_id=<?= $s['id'] ?>" class="btn-delete" onclick="return confirm('Hapus langganan ini?')"><i class="ph ph-trash"></i></a></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL TAMBAH -->
    <div class="modal-overlay" id="modalForm">
        <div class="modal">
            <h2>Tambah Langganan</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_sub">
                <div class="form-group"><label>Nama Layanan *</label><input type="text" name="name" required placeholder="Netflix"></div>
                <div class="form-row">
                    <div class="form-group"><label>Harga *</label><input type="number" name="price" required min="0" placeholder="50000"></div>
                    <div class="form-group"><label>Siklus *</label><select name="cycle"><option value="monthly">Bulanan</option><option value="yearly">Tahunan</option></select></div>
                </div>
                <div class="form-group"><label>Tanggal Renewal</label><input type="date" name="renewal"></div>
                <div class="modal-actions"><button type="button" class="btn-secondary" onclick="document.getElementById('modalForm').classList.remove('active')">Batal</button><button type="submit" class="btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
<?php
session_start();
require_once 'db.php';

// ==========================================
// 1. LOGIKA BACKEND (LOGIN & CRUD)
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

    // IMPORT JSON
    if ($action === 'import_data' && isset($_SESSION['user_id'])) {
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] == 0) {
            $json = file_get_contents($_FILES['json_file']['tmp_name']);
            $data = json_decode($json, true);
            if (is_array($data)) {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("INSERT INTO user_subscriptions (id, user_id, name, price, cycle, renewal_date, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $now = date('Y-m-d H:i:s');
                    $count = 0;
                    foreach ($data as $row) {
                        if (isset($row['name']) && isset($row['price'])) {
                            $stmt->execute([uniqid(), $_SESSION['user_id'], $row['name'], $row['price'], $row['cycle'] ?? 'monthly', $row['renewal_date'] ?? null, $row['active'] ?? 1, $now, $now]);
                            $count++;
                        }
                    }
                    $pdo->commit();
                    $success_msg = "Berhasil mengimpor $count data!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_msg = "Gagal import: " . $e->getMessage();
                }
            }
        }
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
// 2. AMBIL DATA UNTUK DASHBOARD
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<style>
/* CSS */
* { box-sizing: border-box; margin: 0; padding: 0; }
:root { --primary: #5a4fcf; --primary-hover: #4a3fbf; --success: #0f9d58; --danger: #ea4335; --warning: #fbbc04; --bg: #f8f9fa; --card: #ffffff; --text-main: #202124; --text-muted: #5f6368; --border: #dadce0; --radius: 16px; --radius-sm: 8px; --shadow: 0 4px 12px rgba(0,0,0,0.05); }
body { font-family: 'Inter', sans-serif; background-color: var(--bg); color: var(--text-main); padding: 30px 20px; line-height: 1.5; }
.container { max-width: 1100px; margin: 0 auto; }

/* HALAMAN LOGIN */
#authScreen { display: flex; justify-content: center; align-items: center; height: 80vh; }
.auth-box { background: #fff; padding: 40px; border-radius: var(--radius); box-shadow: var(--shadow); width: 100%; max-width: 400px; border: 1px solid var(--border); text-align: center; }
.auth-box h1 { margin-bottom: 20px; font-size: 28px; font-weight: 700; }
.auth-box h1 span { color: var(--primary); }
.auth-box input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid var(--border); border-radius: var(--radius-sm); outline: none; font-family: inherit; }
.auth-box button { width: 100%; justify-content: center; margin-top: 10px; }
.auth-toggle { margin-top: 15px; font-size: 14px; color: var(--primary); text-decoration: none; display: block; }
.auth-toggle:hover { text-decoration: underline; }

/* HEADER DASHBOARD */
header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 16px; }
h1 { font-size: 28px; font-weight: 700; }
h1 span { color: var(--primary); }
.toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
button, .btn { font: inherit; cursor: pointer; border: none; border-radius: var(--radius-sm); padding: 10px 16px; font-weight: 500; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-hover); }
.btn-secondary { background: #fff; color: var(--text-main); border: 1px solid var(--border); }
.btn-secondary:hover { background: #f1f3f4; }

/* POSISI ABSOLUTE UNTUK CARD */
.summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
.card { background: var(--card); border-radius: var(--radius); padding: 24px; border: 1px solid var(--border); box-shadow: var(--shadow); position: relative; height: 140px; }
.card .label { position: absolute; top: 24px; left: 24px; color: var(--text-muted); font-size: 14px; font-weight: 500; }
.card-icon { position: absolute; top: 24px; right: 24px; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.card-icon.primary { background: #eef2ff; color: var(--primary); }
.card-icon.success { background: #ecfdf5; color: var(--success); }
.card-icon.info { background: #e0f2fe; color: #0284c7; }
.card .value { position: absolute; bottom: 24px; left: 24px; font-size: 28px; font-weight: 700; }
.card .value.primary { color: var(--primary); }
.card .value.success { color: var(--success); }

/* TABEL */
.table-container { background: var(--card); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden; }
.table-header { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 0.5fr; gap: 16px; padding: 16px 24px; background: #f8f9fa; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600; color: var(--text-muted); }
.list-item { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 0.5fr; gap: 16px; padding: 16px 24px; border-bottom: 1px solid var(--border); align-items: center; }
.item-main { display: flex; align-items: center; gap: 16px; }
.item-logo { width: 44px; height: 44px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 20px; color: white; text-transform: uppercase; }
.item-name { font-weight: 600; font-size: 15px; }
.item-meta { font-size: 13px; color: var(--text-muted); }
.item-price { font-weight: 700; font-size: 15px; text-align: right; }
.btn-delete { background: #fef2f2; color: var(--danger); border: none; width: 36px; height: 36px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; cursor: pointer; margin-left: auto; text-decoration: none; }

/* BADGES TIMER */
.status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #ecfdf5; color: #047857; }
.status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #047857; }
.timer-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.timer-normal { background: #f1f3f4; color: #5f6368; }
.timer-warning { background: #fffbeb; color: #b45309; }
.timer-danger { background: #fef2f2; color: #b91c1c; }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 100; }
.modal-overlay.active { display: flex; }
.modal { background: #fff; border-radius: var(--radius); padding: 30px; width: 100%; max-width: 480px; }
.modal h2 { margin-bottom: 24px; font-size: 22px; font-weight: 700; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; }
.form-group input, .form-group select { width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: var(--radius-sm); outline: none; font-family: inherit; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 30px; }
.error-msg { background: #fef2f2; color: #b91c1c; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
.success-msg { background: #ecfdf5; color: #047857; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
</style>
</head>
<body>

<!-- LOGIKA UTAMA: JIKA BELUM LOGIN, TAMPILKAN FORM -->
<?php if (!isset($_SESSION['user_id'])): ?>
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

<!-- JIKA SUDAH LOGIN, TAMPILKAN DASHBOARD -->
<?php else: ?>
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

        <?php if ($success_msg): ?><div class="success-msg"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="error-msg"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

        <!-- KARTU (ANGKA SUDAH ABSOLUTE) -->
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
                    // LOGIKA TIMER (MENGGUNAKAN PHP)
                    $timerHtml = '<span style="color:#9ca3af; font-size:13px;">-</span>';
                    if (!empty($s['renewal_date'])) {
                        $today = new DateTime(date('Y-m-d'));
                        $target = new DateTime($s['renewal_date']);
                        $diff = (int)$today->diff($target)->format("%r%a");

                        if ($diff < 0) {
                            $timerHtml = '<span class="timer-badge timer-danger"><i class="ph ph-clock"></i> Terlewat ' . abs($diff) . ' hari</span>';
                        } elseif ($diff == 0) {
                            $timerHtml = '<span class="timer-badge timer-danger"><i class="ph ph-clock"></i> Hari ini!</span>';
                        } elseif ($diff <= 7) {
                            $timerHtml = '<span class="timer-badge timer-warning"><i class="ph ph-clock"></i> Sisa ' . $diff . ' hari</span>';
                        } else {
                            $timerHtml = '<span class="timer-badge timer-normal"><i class="ph ph-clock"></i> ' . $diff . ' hari lagi</span>';
                        }
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

    <!-- MODAL IMPORT -->
    <div class="modal-overlay" id="modalImport">
        <div class="modal">
            <h2>Import Data JSON</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_data">
                <div class="form-group">
                    <label>Pilih file backup (.json) *</label>
                    <input type="file" name="json_file" accept=".json" required style="padding: 10px; border: 1px dashed var(--border); width: 100%;">
                </div>
                <div class="modal-actions"><button type="button" class="btn-secondary" onclick="document.getElementById('modalImport').classList.remove('active')">Batal</button><button type="submit" class="btn-primary">Import</button></div>
            </form>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
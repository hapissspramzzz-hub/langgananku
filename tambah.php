<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$cycles = [
    'weekly' => 'Mingguan',
    'monthly' => 'Bulanan',
    'quarterly' => 'Per 3 bulan',
    'yearly' => 'Tahunan',
];
$values = [
    'name' => '',
    'price' => '',
    'cycle' => 'monthly',
    'renewal_date' => '',
    'renewal_time' => '09:00',
    'notes' => '',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        foreach ($values as $key => $value) {
            if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
                $error = 'Data yang dikirim tidak valid.';
                break;
            }
            $values[$key] = trim($_POST[$key]);
        }

        $priceInput = str_replace(',', '.', str_replace('.', '', $values['price']));
        $price = filter_var($priceInput, FILTER_VALIDATE_FLOAT);
        $date = $values['renewal_date'];
        $time = $values['renewal_time'];
        $dateValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
        $timeValid = preg_match('/^\d{2}:\d{2}$/', $time)
            && (int) substr($time, 0, 2) <= 23
            && (int) substr($time, 3, 2) <= 59;

        if ($error === '' && ($values['name'] === '' || mb_strlen($values['name']) > 120
            || $price === false || $price < 0
            || !isset($cycles[$values['cycle']])
            || !$dateValid || !$timeValid
            || mb_strlen($values['notes']) > 500)) {
            $error = 'Periksa kembali nama, harga, siklus, tanggal, waktu, dan catatan.';
        } elseif ($error === '') {
            $id = sprintf(
                '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff),
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff)
            );
            $stmt = $pdo->prepare(
                'INSERT INTO user_subscriptions
                (id, user_id, name, price, cycle, renewal_date, renewal_time, notes, active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
            );
            $stmt->execute([
                $id,
                (int) $_SESSION['user_id'],
                $values['name'],
                $price,
                $values['cycle'],
                $date,
                $time . ':00',
                $values['notes'] !== '' ? $values['notes'] : null,
            ]);
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Langganan - LanggananKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="syle.css">
</head>
<body>
<main class="container">
    <section class="page-card">
        <a class="brand" href="index.php">Langganan<span>Ku</span></a>
        <h1>Tambah langganan</h1>
        <p class="page-description">Isi data langganan baru di bawah ini.</p>
        <?php if ($error !== ''): ?><div class="error-msg" role="alert"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label for="name">Nama layanan</label>
                <input id="name" name="name" type="text" maxlength="120" value="<?= h($values['name']) ?>" placeholder="Contoh: Netflix" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="price">Harga</label>
                    <input id="price" name="price" type="text" inputmode="decimal" data-price-input value="<?= h($values['price']) ?>" placeholder="Contoh: 50.000" required>
                </div>
                <div class="form-group">
                    <label for="cycle">Siklus pembayaran</label>
                    <select id="cycle" name="cycle">
                        <?php foreach ($cycles as $key => $label): ?>
                            <option value="<?= h($key) ?>" <?= $values['cycle'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="renewal_date">Tanggal perpanjangan</label>
                    <input id="renewal_date" name="renewal_date" type="date" value="<?= h($values['renewal_date']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="renewal_time">Waktu perpanjangan</label>
                    <input id="renewal_time" name="renewal_time" type="time" value="<?= h($values['renewal_time']) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="notes">Catatan <span>(opsional)</span></label>
                <input id="notes" name="notes" type="text" maxlength="500" value="<?= h($values['notes']) ?>" placeholder="Contoh: paket keluarga">
            </div>
            <div class="page-actions">
                <a class="btn btn-secondary" href="index.php">Batal</a>
                <button class="btn btn-primary" type="submit">Simpan langganan</button>
            </div>
        </form>
    </section>
</main>
<script src="format-harga.js"></script>
</body>
</html>

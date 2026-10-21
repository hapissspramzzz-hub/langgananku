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
$id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
$stmt = $pdo->prepare('SELECT * FROM user_subscriptions WHERE id = ? AND user_id = ?');
$stmt->execute([$id, (int) $_SESSION['user_id']]);
$subscription = $stmt->fetch();

if (!$subscription) {
    http_response_code(404);
    exit('Langganan tidak ditemukan.');
}

$values = [
    'name' => $subscription['name'],
    'price' => (string) $subscription['price'],
    'cycle' => $subscription['cycle'],
    'renewal_date' => $subscription['renewal_date'],
    'renewal_time' => $subscription['renewal_time'] ? substr($subscription['renewal_time'], 0, 5) : '09:00',
    'notes' => $subscription['notes'] ?? '',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        foreach ($values as $key => $value) {
            $values[$key] = trim((string) ($_POST[$key] ?? $value));
        }

        $price = filter_var($values['price'], FILTER_VALIDATE_FLOAT);
        $date = $values['renewal_date'];
        $time = $values['renewal_time'];
        $dateValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
        $timeValid = preg_match('/^\d{2}:\d{2}$/', $time)
            && (int) substr($time, 0, 2) <= 23
            && (int) substr($time, 3, 2) <= 59;

        if ($values['name'] === '' || mb_strlen($values['name']) > 120
            || $price === false || $price < 0
            || !isset($cycles[$values['cycle']])
            || !$dateValid || !$timeValid
            || mb_strlen($values['notes']) > 500) {
            $error = 'Periksa kembali nama, harga, siklus, tanggal, waktu, dan catatan.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE user_subscriptions
                SET name = ?, price = ?, cycle = ?, renewal_date = ?, renewal_time = ?, notes = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([
                $values['name'],
                $price,
                $values['cycle'],
                $date,
                $time . ':00',
                $values['notes'] !== '' ? $values['notes'] : null,
                $id,
                (int) $_SESSION['user_id'],
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
    <title>Edit Langganan - LanggananKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="syle.css">
</head>
<body>
<main class="container">
    <section class="page-card">
        <a class="brand" href="index.php">Langganan<span>Ku</span></a>
        <h1>Edit langganan</h1>
        <p class="page-description">Ubah data langganan, lalu simpan perubahan.</p>
        <?php if ($error !== ''): ?><div class="error-msg" role="alert"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="id" value="<?= h($id) ?>">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label for="name">Nama layanan</label>
                <input id="name" name="name" type="text" maxlength="120" value="<?= h($values['name']) ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="price">Harga</label>
                    <input id="price" name="price" type="number" min="0" step="0.01" value="<?= h($values['price']) ?>" required>
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
                <input id="notes" name="notes" type="text" maxlength="500" value="<?= h($values['notes']) ?>">
            </div>
            <div class="page-actions">
                <a class="btn btn-secondary" href="index.php">Batal</a>
                <button class="btn btn-primary" type="submit">Simpan perubahan</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>

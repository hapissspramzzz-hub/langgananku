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

function importDateIsValid($date)
{
    if ($date === null || $date === '') {
        return true;
    }

    return is_string($date)
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
        && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
}

function importTimeIsValid($time)
{
    if ($time === null || $time === '') {
        return true;
    }

    return is_string($time)
        && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time) === 1
        && (int) substr($time, 0, 2) <= 23
        && (int) substr($time, 3, 2) <= 59
        && (strlen($time) === 5 || (int) substr($time, 6, 2) <= 59);
}

function newSubscriptionId()
{
    return sprintf(
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
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!isset($_FILES['backup']) || !is_array($_FILES['backup'])) {
        $error = 'Pilih file backup JSON untuk diimpor.';
    } else {
        $file = $_FILES['backup'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = $file['error'] === UPLOAD_ERR_NO_FILE
                ? 'Pilih file backup JSON untuk diimpor.'
                : 'File gagal diunggah. Coba lagi dengan file yang lebih kecil.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = 'Ukuran file maksimal 2 MB.';
        } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'json') {
            $error = 'Pilih file backup berformat .json dari fitur Ekspor.';
        } else {
            $content = file_get_contents($file['tmp_name']);

            try {
                $items = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $items = null;
                $error = 'Isi file bukan JSON yang valid.';
            }

            if ($error === '' && (!is_array($items) || !array_is_list($items))) {
                $error = 'Format backup tidak sesuai. Gunakan file JSON dari fitur Ekspor.';
            } elseif ($error === '' && count($items) > 500) {
                $error = 'Satu kali impor dibatasi maksimal 500 langganan.';
            } elseif ($error === '') {
                $cycles = ['weekly', 'monthly', 'quarterly', 'yearly'];
                foreach ($items as $index => $item) {
                    $row = $index + 1;
                    if (!is_array($item) || !isset($item['name'], $item['price'], $item['cycle'])) {
                        $error = "Data langganan pada baris {$row} tidak lengkap.";
                        break;
                    }

                    $name = $item['name'];
                    $price = $item['price'];
                    $cycle = $item['cycle'];
                    $date = $item['renewal_date'] ?? null;
                    $time = $item['renewal_time'] ?? null;
                    $notes = $item['notes'] ?? null;
                    $active = $item['active'] ?? 1;
                    $activeIsValid = in_array($active, [0, 1, '0', '1', true, false], true);

                    if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 120
                        || (!is_int($price) && !is_float($price) && !is_string($price))
                        || !is_numeric($price) || !is_finite((float) $price) || (float) $price < 0
                        || !is_string($cycle) || !in_array($cycle, $cycles, true)
                        || !importDateIsValid($date) || !importTimeIsValid($time)
                        || ($notes !== null && (!is_string($notes) || mb_strlen($notes) > 500))
                        || !$activeIsValid) {
                        $error = "Data langganan pada baris {$row} tidak valid.";
                        break;
                    }
                }
            }

            if ($error === '') {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare(
                        'INSERT INTO user_subscriptions
                        (id, user_id, name, price, cycle, renewal_date, renewal_time, notes, active, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                    );

                    foreach ($items as $item) {
                        $date = $item['renewal_date'] ?? null;
                        $time = $item['renewal_time'] ?? null;
                        $stmt->execute([
                            newSubscriptionId(),
                            (int) $_SESSION['user_id'],
                            trim($item['name']),
                            (float) $item['price'],
                            $item['cycle'],
                            $date !== '' ? $date : null,
                            $time !== '' ? (strlen($time) === 5 ? $time . ':00' : $time) : null,
                            isset($item['notes']) && $item['notes'] !== '' ? $item['notes'] : null,
                            (int) ($item['active'] ?? 1),
                        ]);
                    }

                    $pdo->commit();
                    $success = count($items) . ' langganan berhasil diimpor. Data sebelumnya tetap tersimpan.';
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log($exception->__toString());
                    $error = 'Impor gagal karena masalah database. Tidak ada data yang diubah.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impor Langganan - LanggananKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="syle.css">
</head>
<body>
<main class="container">
    <section class="page-card">
        <a class="brand" href="index.php">Langganan<span>Ku</span></a>
        <h1>Impor langganan</h1>
        <p class="page-description">Pilih file JSON dari fitur Ekspor. Langganan akan ditambahkan tanpa menghapus data yang sudah ada. Maksimal 500 data, ukuran file 2 MB.</p>
        <?php if ($error !== ''): ?><div class="error-msg" role="alert"><?= h($error) ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="success-msg" role="status"><?= h($success) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label for="backup">File backup JSON</label>
                <input id="backup" name="backup" type="file" accept=".json,application/json" required>
            </div>
            <div class="page-actions">
                <a class="btn btn-secondary" href="index.php">Kembali</a>
                <button class="btn btn-primary" type="submit">Impor langganan</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>

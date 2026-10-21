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

$rawId = $_GET['id'] ?? $_POST['id'] ?? '';
$id = is_string($rawId) ? trim($rawId) : '';
$stmt = $pdo->prepare('SELECT id, name FROM user_subscriptions WHERE id = ? AND user_id = ?');
$stmt->execute([$id, (int) $_SESSION['user_id']]);
$subscription = $stmt->fetch();

if (!$subscription) {
    http_response_code(404);
    exit('Langganan tidak ditemukan.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM user_subscriptions WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, (int) $_SESSION['user_id']]);
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Langganan - LanggananKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="syle.css">
</head>
<body>
<main class="container">
    <section class="page-card">
        <a class="brand" href="index.php">Langganan<span>Ku</span></a>
        <h1>Hapus langganan</h1>
        <p class="page-description">Yakin ingin menghapus <strong><?= h($subscription['name']) ?></strong>? Data yang dihapus tidak bisa dikembalikan.</p>
        <?php if ($error !== ''): ?><div class="error-msg" role="alert"><?= h($error) ?></div><?php endif; ?>
        <form method="post" class="page-actions">
            <input type="hidden" name="id" value="<?= h($id) ?>">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <a class="btn btn-secondary" href="index.php">Batal</a>
            <button class="btn btn-delete" type="submit">Ya, hapus</button>
        </form>
    </section>
</main>
</body>
</html>

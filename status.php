<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(419);
    exit('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
}

$id = trim((string) ($_POST['id'] ?? ''));
$stmt = $pdo->prepare(
    'UPDATE user_subscriptions
    SET active = IF(active = 1, 0, 1), updated_at = NOW()
    WHERE id = ? AND user_id = ?'
);
$stmt->execute([$id, (int) $_SESSION['user_id']]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    exit('Langganan tidak ditemukan.');
}

header('Location: index.php');
exit;

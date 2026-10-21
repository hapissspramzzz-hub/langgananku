<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

function respond($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    respond(['error' => 'Sesi berakhir. Silakan login kembali.'], 401);
}
$uid = (int) $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT id, name, price, cycle, renewal_date, renewal_time, notes, active FROM user_subscriptions WHERE user_id = ? ORDER BY renewal_date IS NULL, renewal_date ASC, renewal_time ASC');
    $stmt->execute([$uid]);
    respond(['items' => $stmt->fetchAll()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') respond(['error' => 'Metode tidak didukung.'], 405);
respond(['error' => 'Aksi tidak dikenal.'], 404);
?>

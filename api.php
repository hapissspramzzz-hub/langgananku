<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function respond($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['error' => 'Metode tidak didukung.'], 405);
}

if (!isset($_SESSION['user_id'])) {
    respond(['error' => 'Sesi berakhir. Silakan login kembali.'], 401);
}

$action = $_GET['action'] ?? '';
if (!is_string($action) || $action !== 'list') {
    respond(['error' => 'Aksi tidak dikenal.'], 404);
}

require_once 'db.php';

try {
    $stmt = $pdo->prepare(
        'SELECT id, name, price, cycle, renewal_date, renewal_time, notes, active
        FROM user_subscriptions
        WHERE user_id = ?
        ORDER BY renewal_date IS NULL, renewal_date ASC, renewal_time ASC'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    respond(['items' => $stmt->fetchAll()]);
} catch (PDOException $exception) {
    error_log($exception->__toString());
    respond(['error' => 'Gagal memuat daftar langganan. Periksa koneksi database.'], 500);
}

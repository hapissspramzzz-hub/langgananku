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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Metode tidak didukung.'], 405);
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    respond(['error' => 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.'], 419);
}
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) respond(['error' => 'Data permintaan tidak valid.'], 400);

if ($action === 'add') {
    $name = trim((string) ($body['name'] ?? ''));
    $price = filter_var($body['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $cycle = (string) ($body['cycle'] ?? '');
    $date = trim((string) ($body['renewalDate'] ?? ''));
    $time = trim((string) ($body['renewalTime'] ?? ''));
    $notes = trim((string) ($body['notes'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120 || $price === false || $price < 0 || !in_array($cycle, ['weekly', 'monthly', 'quarterly', 'yearly'], true)) {
        respond(['error' => 'Periksa nama, harga, dan siklus langganan.'], 422);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
        respond(['error' => 'Pilih tanggal perpanjangan yang valid.'], 422);
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $time) || (int) substr($time, 0, 2) > 23 || (int) substr($time, 3, 2) > 59) {
        respond(['error' => 'Pilih waktu perpanjangan yang valid.'], 422);
    }
    if (mb_strlen($notes) > 500) respond(['error' => 'Catatan maksimal 500 karakter.'], 422);
    $uuid = sprintf('%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x0fff), random_int(0, 0x3fff) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
    $stmt = $pdo->prepare('INSERT INTO user_subscriptions (id, user_id, name, price, cycle, renewal_date, renewal_time, notes, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())');
    $stmt->execute([$uuid, $uid, $name, $price, $cycle, $date, $time . ':00', $notes !== '' ? $notes : null]);
    respond(['success' => true]);
}
if ($action === 'delete') {
    $stmt = $pdo->prepare('DELETE FROM user_subscriptions WHERE id = ? AND user_id = ?');
    $stmt->execute([(string) ($body['id'] ?? ''), $uid]);
    respond(['success' => true]);
}
if ($action === 'toggle') {
    $stmt = $pdo->prepare('UPDATE user_subscriptions SET active = IF(active = 1, 0, 1), updated_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->execute([(string) ($body['id'] ?? ''), $uid]);
    if ($stmt->rowCount() === 0) respond(['error' => 'Langganan tidak ditemukan.'], 404);
    respond(['success' => true]);
}
respond(['error' => 'Aksi tidak dikenal.'], 404);
?>

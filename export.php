<?php
session_start();
require_once 'db.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak. Silakan login.");
}

$stmt = $pdo->prepare("SELECT name, price, cycle, renewal_date, renewal_time, notes, active FROM user_subscriptions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$data = $stmt->fetchAll();

// Set header untuk memaksa download file JSON
$filename = 'langgananku-backup-' . date('Y-m-d') . '.json';
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output JSON
echo json_encode($data, JSON_PRETTY_PRINT);
exit;
?>

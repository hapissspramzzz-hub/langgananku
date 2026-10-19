<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

// Tolak akses jika belum login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}
$uid = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// AMBIL DATA
if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM user_subscriptions WHERE user_id = ? ORDER BY renewal_date ASC");
    $stmt->execute([$uid]);
    echo json_encode($stmt->fetchAll()); exit;
}

// TAMBAH DATA
if ($action === 'add') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO user_subscriptions (id, user_id, name, price, cycle, renewal_date, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)");
    $stmt->execute([
        uniqid(), $uid, $data['name'], $data['price'], $data['cycle'], 
        empty($data['renewalDate']) ? null : $data['renewalDate'], 
        date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
    ]);
    echo json_encode(['success' => true]); exit;
}

// HAPUS DATA
if ($action === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("DELETE FROM user_subscriptions WHERE id = ? AND user_id = ?");
    $stmt->execute([$data['id'], $uid]);
    echo json_encode(['success' => true]); exit;
}
?>
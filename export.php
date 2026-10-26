<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/subscription-spreadsheet.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT name, price, cycle, renewal_date, renewal_time, notes, active
        FROM user_subscriptions
        WHERE user_id = ?
        ORDER BY renewal_date IS NULL, renewal_date ASC, renewal_time ASC, name ASC'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $subscriptions = $stmt->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->__toString());
    http_response_code(500);
    exit('Ekspor gagal karena data langganan tidak dapat dibaca. Silakan coba lagi.');
}

$cycleNames = [
    'weekly' => 'Mingguan',
    'monthly' => 'Bulanan',
    'quarterly' => 'Per 3 bulan',
    'yearly' => 'Tahunan',
];
$rows = array_map(static function ($subscription) use ($cycleNames) {
    return [
        $subscription['name'],
        $subscription['price'] === null ? null : (float) $subscription['price'],
        $cycleNames[$subscription['cycle']] ?? $subscription['cycle'],
        $subscription['renewal_date'],
        $subscription['renewal_time'],
        $subscription['notes'],
        (int) $subscription['active'] === 1 ? 'Aktif' : 'Dijeda',
    ];
}, $subscriptions);

downloadSubscriptionSpreadsheet($rows, 'langgananku-' . date('Y-m-d') . '.xlsx');

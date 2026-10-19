<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Periksa role dari database setiap kali halaman dibuka.
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'admin') {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini hanya untuk admin.');
}

$users = $pdo->query(
    'SELECT u.id, u.email, u.role, COUNT(s.id) AS subscription_count,
            COALESCE(SUM(CASE WHEN s.active = 1 THEN s.price ELSE 0 END), 0) AS active_spend
     FROM users u
     LEFT JOIN user_subscriptions s ON s.user_id = u.id
     GROUP BY u.id, u.email, u.role
     ORDER BY u.id DESC'
)->fetchAll();
$totalUsers = count($users);
$totalSubscriptions = array_sum(array_column($users, 'subscription_count'));
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - LanggananKu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#182230;font:15px Inter,Arial,sans-serif}.wrap{max-width:1100px;margin:40px auto;padding:0 20px}header{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:28px}h1{font-size:26px;margin:0}h1 span{color:#4f46e5}.actions{display:flex;gap:10px;flex-wrap:wrap}a{color:inherit;text-decoration:none}.btn{display:inline-block;padding:10px 14px;border-radius:9px;background:#4f46e5;color:#fff;font-weight:600}.btn.secondary{background:#fff;color:#374151;border:1px solid #dbe1ea}.stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:22px}.card,.table{background:#fff;border:1px solid #e7ebf1;border-radius:14px}.card{padding:20px}.label{color:#667085;font-size:13px}.value{font-size:28px;font-weight:700;margin-top:8px}.table{overflow:auto}table{width:100%;border-collapse:collapse;min-width:650px}th,td{text-align:left;padding:15px 18px;border-bottom:1px solid #edf0f4}th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#667085;background:#fafbfc}tr:last-child td{border-bottom:0}.role{display:inline-block;padding:5px 9px;border-radius:99px;background:#eef2ff;color:#4338ca;font-size:12px;font-weight:600}.empty{text-align:center;color:#667085;padding:28px}@media(max-width:600px){.wrap{margin:24px auto}.stats{grid-template-columns:1fr}header{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body><main class="wrap">
<header><h1>Langganan<span>Ku</span> Admin</h1><nav class="actions"><a class="btn secondary" href="index.php">Dashboard</a><a class="btn" href="index.php?logout=1">Logout</a></nav></header>
<section class="stats"><div class="card"><div class="label">Total pengguna</div><div class="value"><?= $totalUsers ?></div></div><div class="card"><div class="label">Total langganan tercatat</div><div class="value"><?= (int)$totalSubscriptions ?></div></div></section>
<section class="table"><table><thead><tr><th>Email</th><th>Role</th><th>Jumlah langganan</th><th>Total biaya aktif</th></tr></thead><tbody>
<?php if (!$users): ?><tr><td colspan="4" class="empty">Belum ada pengguna.</td></tr><?php else: foreach ($users as $user): ?>
<tr><td><?= h($user['email']) ?></td><td><span class="role"><?= h($user['role']) ?></span></td><td><?= (int)$user['subscription_count'] ?></td><td>Rp <?= number_format((float)$user['active_spend'], 0, ',', '.') ?></td></tr>
<?php endforeach; endif; ?>
</tbody></table></section>
</main></body></html>

<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

$timezoneName = getenv('APP_TIMEZONE') ?: 'Asia/Jakarta';
date_default_timezone_set($timezoneName);

$smtpHost = getenv('SMTP_HOST');
$smtpUsername = getenv('SMTP_USERNAME');
$smtpPassword = getenv('SMTP_PASSWORD');
$smtpFromEmail = getenv('SMTP_FROM_EMAIL');
$smtpPort = filter_var(getenv('SMTP_PORT') ?: '587', FILTER_VALIDATE_INT);
$smtpEncryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');
$smtpFromName = getenv('SMTP_FROM_NAME') ?: 'LanggananKu';
$dryRun = in_array('--dry-run', $argv, true);

if (!$dryRun) {
    if (!$smtpHost || !$smtpFromEmail || !filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "Atur SMTP_HOST dan SMTP_FROM_EMAIL dengan alamat email yang valid.\n");
        exit(1);
    }
    if ($smtpPort === false || $smtpPort < 1 || $smtpPort > 65535) {
        fwrite(STDERR, "SMTP_PORT tidak valid.\n");
        exit(1);
    }
    if (!in_array($smtpEncryption, ['tls', 'ssl', 'none'], true)) {
        fwrite(STDERR, "SMTP_ENCRYPTION harus tls, ssl, atau none.\n");
        exit(1);
    }
    if (($smtpUsername === false) !== ($smtpPassword === false)) {
        fwrite(STDERR, "Atur SMTP_USERNAME dan SMTP_PASSWORD bersama-sama, atau kosongkan keduanya.\n");
        exit(1);
    }
}

$now = new DateTimeImmutable('now');
$earliestRenewal = $now->modify('+24 hours');
$latestRenewal = $earliestRenewal->modify('+5 minutes');
$dueQuery = $pdo->prepare(
    "SELECT s.id, s.name, s.price, s.cycle,
            TIMESTAMP(s.renewal_date, COALESCE(s.renewal_time, '23:59:59')) AS renewal_at,
            u.email, u.name AS account_name
    FROM user_subscriptions s
    INNER JOIN users u ON u.id = s.user_id
    WHERE s.active = 1
      AND s.renewal_date IS NOT NULL
      AND TIMESTAMP(s.renewal_date, COALESCE(s.renewal_time, '23:59:59')) > ?
      AND TIMESTAMP(s.renewal_date, COALESCE(s.renewal_time, '23:59:59')) <= ?
    ORDER BY renewal_at ASC"
);
$dueQuery->execute([
    $earliestRenewal->format('Y-m-d H:i:s'),
    $latestRenewal->format('Y-m-d H:i:s'),
]);
$subscriptions = $dueQuery->fetchAll();

if ($dryRun) {
    printf(
        "Mode uji: %d langganan akan menerima pengingat setidaknya 24 jam sebelum perpanjangan.\n",
        count($subscriptions)
    );
    exit(0);
}

$cycleNames = [
    'weekly' => 'minggu',
    'monthly' => 'bulan',
    'quarterly' => '3 bulan',
    'yearly' => 'tahun',
];
$sent = 0;
$skipped = 0;
$failed = 0;

foreach ($subscriptions as $subscription) {
    $lockName = 'langganaku:email:' . $subscription['id'];
    $lockQuery = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $lockQuery->execute([$lockName]);
    $locked = (int) $lockQuery->fetchColumn() === 1;

    if (!$locked) {
        $skipped++;
        continue;
    }

    try {
        $sentQuery = $pdo->prepare(
            'SELECT 1 FROM subscription_email_reminders WHERE subscription_id = ? AND renewal_at = ?'
        );
        $sentQuery->execute([$subscription['id'], $subscription['renewal_at']]);
        if ($sentQuery->fetchColumn()) {
            $skipped++;
            continue;
        }

        $platform = htmlspecialchars($subscription['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $accountName = htmlspecialchars($subscription['account_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $renewalAt = new DateTimeImmutable($subscription['renewal_at']);
        $renewalText = $renewalAt->format('d-m-Y H:i');
        $priceText = 'Rp ' . number_format((float) $subscription['price'], 0, ',', '.');
        $cycle = $cycleNames[$subscription['cycle']] ?? $subscription['cycle'];
        $subject = 'Pengingat: langganan ' . $subscription['name'] . ' habis dalam 24 jam';
        $plainText = "Halo {$subscription['account_name']},\n\n"
            . "Langganan Anda di platform {$subscription['name']} akan habis dalam waktu 24 jam lagi"
            . " (perkiraan perpanjangan: {$renewalText}).\n"
            . "Segera perpanjang agar layanan tetap aktif. Harga saat ini: {$priceText} per {$cycle}.\n\n"
            . "Salam,\nLanggananKu";

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->Timeout = 15;
        $mail->SMTPAuth = $smtpUsername !== false && $smtpUsername !== '';
        if ($mail->SMTPAuth) {
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
        }
        if ($smtpEncryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtpEncryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($smtpFromEmail, $smtpFromName);
        $mail->addAddress($subscription['email'], $subscription['account_name']);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = '<p>Halo ' . $accountName . ',</p>'
            . '<p>Langganan Anda di platform <strong>' . $platform . '</strong> akan habis dalam waktu '
            . '<strong>24 jam lagi</strong> (perkiraan perpanjangan: ' . $renewalText . ').</p>'
            . '<p>Segera perpanjang agar layanan tetap aktif. Harga saat ini: <strong>'
            . $priceText . ' per ' . htmlspecialchars($cycle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</strong>.</p>'
            . '<p>Salam,<br>LanggananKu</p>';
        $mail->AltBody = $plainText;
        $mail->send();

        $recordQuery = $pdo->prepare(
            'INSERT INTO subscription_email_reminders (subscription_id, renewal_at, sent_at) VALUES (?, ?, NOW())'
        );
        $recordQuery->execute([$subscription['id'], $subscription['renewal_at']]);
        $sent++;
    } catch (MailException | PDOException $exception) {
        $failed++;
        fwrite(STDERR, "Gagal memproses pengingat untuk langganan {$subscription['id']}: "
            . $exception->getMessage() . "\n");
    } finally {
        try {
            $releaseQuery = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $releaseQuery->execute([$lockName]);
        } catch (PDOException $exception) {
            $failed++;
            fwrite(STDERR, "Gagal melepaskan kunci pengingat untuk langganan {$subscription['id']}.\n");
        }
    }
}

printf("Selesai: %d email terkirim, %d dilewati, %d gagal.\n", $sent, $skipped, $failed);
exit($failed > 0 ? 1 : 0);

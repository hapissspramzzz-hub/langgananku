<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/subscription-spreadsheet.php';

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

function importDateIsValid($date)
{
    if ($date === null || $date === '') {
        return true;
    }

    return is_string($date)
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
        && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
}

function importTimeIsValid($time)
{
    if ($time === null || $time === '') {
        return true;
    }

    return is_string($time)
        && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time) === 1
        && (int) substr($time, 0, 2) <= 23
        && (int) substr($time, 3, 2) <= 59
        && (strlen($time) === 5 || (int) substr($time, 6, 2) <= 59);
}

function normalizeImportHeader($value)
{
    return preg_replace('/[\s_-]+/u', '', mb_strtolower(trim((string) $value)));
}

function importHeaderAliases()
{
    return [
        'name' => ['nama', 'namalayanan', 'name', 'subscription', 'subscriptionname', 'service', 'servicename'],
        'price' => ['harga', 'price', 'biaya'],
        'cycle' => ['siklus', 'sikluspembayaran', 'cycle', 'periode'],
        'renewal_date' => ['tanggalperpanjangan', 'renewaldate', 'tanggalrenewal'],
        'renewal_time' => ['waktuperpanjangan', 'renewaltime', 'wakturenewal'],
        'notes' => ['catatan', 'notes', 'keterangan'],
        'active' => ['aktif', 'status', 'active'],
    ];
}

function importTextDate($value)
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    $value = trim((string) $value);
    foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }
    }
    return $value;
}

function importTextRows($contents)
{
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
    $lines = preg_split('/\r\n|\r|\n/', $contents);
    $firstLineIndex = null;
    foreach ($lines as $index => $line) {
        if (trim($line) !== '') {
            $firstLineIndex = $index;
            break;
        }
    }
    if ($firstLineIndex === null) {
        return [];
    }

    $firstLine = $lines[$firstLineIndex];
    $delimiter = null;
    $delimiterCount = 0;
    foreach (["\t", ';', ',', '|'] as $candidate) {
        $count = substr_count($firstLine, $candidate);
        if ($count > $delimiterCount) {
            $delimiter = $candidate;
            $delimiterCount = $count;
        }
    }

    if ($delimiter === null) {
        $rows = [];
        foreach ($lines as $line) {
            $name = trim($line);
            if ($name !== '') {
                $rows[] = ['raw' => ['name' => $name], 'extra' => [], 'line' => count($rows) + 1];
            }
        }
        return $rows;
    }

    $headers = str_getcsv($firstLine, $delimiter);
    $aliasMap = [];
    foreach (importHeaderAliases() as $key => $aliases) {
        foreach ($aliases as $alias) {
            $aliasMap[$alias] = $key;
        }
    }
    $columns = [];
    $nameColumnFound = false;
    foreach ($headers as $index => $header) {
        $normalized = normalizeImportHeader($header);
        if (isset($aliasMap[$normalized])) {
            $columns[$index] = ['key' => $aliasMap[$normalized], 'label' => trim($header)];
            if ($aliasMap[$normalized] === 'name') {
                $nameColumnFound = true;
            }
        } else {
            $columns[$index] = ['key' => null, 'label' => trim($header) !== '' ? trim($header) : 'Kolom ' . ($index + 1)];
        }
    }
    if (!$nameColumnFound && isset($columns[0])) {
        $columns[0]['key'] = 'name';
        $columns[0]['label'] = trim($headers[0]) !== '' ? trim($headers[0]) : 'Nama';
    }

    $rows = [];
    for ($lineIndex = $firstLineIndex + 1; $lineIndex < count($lines); $lineIndex++) {
        if (trim($lines[$lineIndex]) === '') {
            continue;
        }
        $values = str_getcsv($lines[$lineIndex], $delimiter);
        $raw = [];
        $extra = [];
        foreach ($columns as $columnIndex => $column) {
            $value = trim((string) ($values[$columnIndex] ?? ''));
            if ($value === '') {
                continue;
            }
            if ($column['key'] !== null) {
                $raw[$column['key']] = $value;
            } else {
                $extra[] = $column['label'] . ': ' . $value;
            }
        }
        if ($raw !== [] || $extra !== []) {
            $rows[] = ['raw' => $raw, 'extra' => $extra, 'line' => $lineIndex + 1];
        }
    }
    return $rows;
}

function importExcelDate($cell)
{
    $value = $cell->getValue();
    if ($value === null || $value === '') {
        return null;
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    if (is_numeric($value) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
        return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
    }

    $value = trim((string) $value);
    foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }
    }
    return $value;
}

function importExcelTime($cell)
{
    $value = $cell->getValue();
    if ($value === null || $value === '') {
        return null;
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('H:i:s');
    }
    if (is_numeric($value) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
        return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('H:i:s');
    }
    return trim((string) $value);
}

function importExcelCycle($value)
{
    $normalized = normalizeImportHeader($value);
    return [
        'weekly' => 'weekly',
        'mingguan' => 'weekly',
        'monthly' => 'monthly',
        'bulanan' => 'monthly',
        'quarterly' => 'quarterly',
        'triwulanan' => 'quarterly',
        'per3bulan' => 'quarterly',
        'yearly' => 'yearly',
        'tahunan' => 'yearly',
    ][$normalized] ?? null;
}

function importExcelActive($value)
{
    if ($value === null || $value === '') {
        return 1;
    }
    $normalized = normalizeImportHeader($value);
    if (in_array($normalized, ['1', 'aktif', 'active', 'true', 'ya'], true)) {
        return 1;
    }
    if (in_array($normalized, ['0', 'dijeda', 'nonaktif', 'inactive', 'false', 'tidak'], true)) {
        return 0;
    }
    return null;
}

function importExcelPrice($value)
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || is_float($value)) {
        return is_finite((float) $value) && $value >= 0 ? (float) $value : false;
    }
    if (!is_string($value)) {
        return false;
    }
    $value = trim(str_ireplace(['rp', ' '], '', $value));
    if (preg_match('/^(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/', $value) !== 1) {
        return false;
    }
    $price = filter_var(str_replace(',', '.', str_replace('.', '', $value)), FILTER_VALIDATE_FLOAT);
    return $price !== false && is_finite((float) $price) && $price >= 0 ? (float) $price : false;
}

function newSubscriptionId()
{
    return sprintf(
        '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff),
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff)
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_pending_import') {
    header('Content-Type: application/json; charset=utf-8');
    $respond = static function ($payload, $status = 200) {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    };
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $respond(['error' => 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.'], 403);
    }

    $pendingItems = $_SESSION['pending_import_items'] ?? [];
    $pendingIndex = (int) ($_SESSION['pending_import_index'] ?? 0);
    $requestedIndex = filter_var($_POST['item_index'] ?? null, FILTER_VALIDATE_INT);
    if (!is_array($pendingItems) || !isset($pendingItems[$pendingIndex])
        || $requestedIndex === false || $requestedIndex !== $pendingIndex) {
        $respond(['error' => 'Data impor sudah berubah atau sudah disimpan. Muat ulang dashboard.'], 409);
    }

    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $priceText = isset($_POST['price']) && is_string($_POST['price']) ? trim($_POST['price']) : '';
    $cycle = $_POST['cycle'] ?? '';
    $date = isset($_POST['renewal_date']) && is_string($_POST['renewal_date']) && $_POST['renewal_date'] !== ''
        ? $_POST['renewal_date']
        : null;
    $time = isset($_POST['renewal_time']) && is_string($_POST['renewal_time']) && $_POST['renewal_time'] !== ''
        ? $_POST['renewal_time']
        : null;
    $notes = isset($_POST['notes']) && is_string($_POST['notes']) ? trim($_POST['notes']) : '';
    $activeValue = isset($_POST['active']) && is_string($_POST['active']) ? $_POST['active'] : null;
    $active = importExcelActive($activeValue);
    $price = importExcelPrice($priceText);
    if ($name === '' || mb_strlen($name) > 120
        || !is_string($cycle) || !in_array($cycle, ['weekly', 'monthly', 'quarterly', 'yearly'], true)
        || $price === false || ($price !== null && $price > 9999999999.99)
        || !importDateIsValid($date) || !importTimeIsValid($time)
        || ($time !== null && $date === null)
        || mb_strlen($notes) > 500 || $active === null) {
        $respond(['error' => 'Periksa nama, harga, siklus, tanggal, waktu, status, dan batas 500 karakter catatan.'], 422);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO user_subscriptions
            (id, user_id, name, price, cycle, renewal_date, renewal_time, notes, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            newSubscriptionId(),
            (int) $_SESSION['user_id'],
            $name,
            $price,
            $cycle,
            $date,
            $time !== null ? (strlen($time) === 5 ? $time . ':00' : $time) : null,
            $notes !== '' ? $notes : null,
            $active,
        ]);
    } catch (PDOException $exception) {
        error_log($exception->__toString());
        $respond(['error' => 'Langganan gagal disimpan. Periksa koneksi database lalu coba lagi.'], 500);
    }

    $_SESSION['pending_import_index'] = $pendingIndex + 1;
    $done = $_SESSION['pending_import_index'] >= count($pendingItems);
    if ($done) {
        $_SESSION['import_success'] = count($pendingItems);
        unset($_SESSION['pending_import_items'], $_SESSION['pending_import_index']);
    }
    $respond(['success' => true, 'done' => $done, 'nextIndex' => $_SESSION['pending_import_index'] ?? null]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_pending_import') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        exit('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
    }
    unset($_SESSION['pending_import_items'], $_SESSION['pending_import_index']);
    header('Location: impor.php?cancelled=1');
    exit;
}

$error = '';
$success = '';

if (($_GET['template'] ?? '') === '1') {
    downloadSubscriptionSpreadsheetTemplate();
}
if (($_GET['cancelled'] ?? '') === '1') {
    $success = 'Data impor yang tertunda berhasil dibatalkan. Anda sekarang bisa mengunggah file baru.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (isset($_SESSION['pending_import_items']) && is_array($_SESSION['pending_import_items'])) {
        $error = 'Selesaikan data impor yang sedang menunggu melalui modal di dashboard sebelum mengunggah file lain.';
    } elseif (!isset($_FILES['backup']) || !is_array($_FILES['backup'])) {
        $error = 'Pilih file .xlsx, .txt, atau .csv untuk diimpor.';
    } else {
        $file = $_FILES['backup'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = $file['error'] === UPLOAD_ERR_NO_FILE
                ? 'Pilih file .xlsx, .txt, atau .csv untuk diimpor.'
                : 'File gagal diunggah. Coba lagi dengan file yang lebih kecil.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Ukuran file maksimal 5 MB.';
        } elseif (!in_array($extension, ['xlsx', 'txt', 'csv'], true)) {
            $error = 'Pilih file Excel .xlsx atau file teks .txt/.csv.';
        } else {
            $records = [];
            try {
                if ($extension === 'xlsx') {
                    if (!class_exists(\ZipArchive::class)) {
                        $error = 'Fitur Excel membutuhkan ekstensi PHP zip. Aktifkan extension=zip pada php.ini lalu restart PHP.';
                    } else {
                        $readerType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($file['tmp_name']);
                        if ($readerType !== 'Xlsx') {
                            throw new \PhpOffice\PhpSpreadsheet\Reader\Exception('File yang diunggah bukan workbook XLSX.');
                        }
                        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                        $reader->setReadDataOnly(false);
                        $spreadsheet = $reader->load($file['tmp_name']);
                        $sheet = $spreadsheet->getActiveSheet();
                        $highestRow = $sheet->getHighestDataRow();
                        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                            $sheet->getHighestDataColumn()
                        );
                        if ($highestColumn > 200) {
                            $error = 'File Excel maksimal memiliki 200 kolom agar kolom tambahan dapat disimpan ke Catatan.';
                        } elseif ($highestRow > 501) {
                            $error = 'Satu kali impor dibatasi maksimal 500 data.';
                        } else {
                            $aliasMap = [];
                            foreach (importHeaderAliases() as $key => $aliases) {
                                foreach ($aliases as $alias) {
                                    $aliasMap[$alias] = $key;
                                }
                            }
                            $columns = [];
                            $nameColumnFound = false;
                            for ($column = 1; $column <= $highestColumn; $column++) {
                                $headerValue = $sheet->getCell([$column, 1])->getValue();
                                $header = normalizeImportHeader($headerValue);
                                if (isset($aliasMap[$header])) {
                                    $columns[$column] = [
                                        'key' => $aliasMap[$header],
                                        'label' => trim((string) $headerValue),
                                    ];
                                    if ($aliasMap[$header] === 'name') {
                                        $nameColumnFound = true;
                                    }
                                } else {
                                    $columns[$column] = [
                                        'key' => null,
                                        'label' => trim((string) $headerValue) !== ''
                                            ? trim((string) $headerValue)
                                            : 'Kolom ' . $column,
                                    ];
                                }
                            }
                            if (!$nameColumnFound && isset($columns[1])) {
                                $columns[1]['key'] = 'name';
                                $columns[1]['label'] = trim((string) $sheet->getCell([1, 1])->getValue()) ?: 'Nama';
                            }
                            for ($row = 2; $error === '' && $row <= $highestRow; $row++) {
                                $raw = [];
                                $extra = [];
                                foreach ($columns as $column => $definition) {
                                    $cell = $sheet->getCell([$column, $row]);
                                    $value = $cell->getValue();
                                    if ($value === null || $value === '') {
                                        continue;
                                    }
                                    if ($definition['key'] === 'renewal_date') {
                                        $value = importExcelDate($cell);
                                    } elseif ($definition['key'] === 'renewal_time') {
                                        $value = importExcelTime($cell);
                                    }
                                    if ($definition['key'] === null) {
                                        $extra[] = $definition['label'] . ': ' . trim($cell->getFormattedValue());
                                    } else {
                                        $raw[$definition['key']] = $value;
                                    }
                                }
                                if ($raw !== [] || $extra !== []) {
                                    $records[] = ['raw' => $raw, 'extra' => $extra, 'line' => $row];
                                }
                            }
                        }
                        $spreadsheet->disconnectWorksheets();
                    }
                } else {
                    $contents = file_get_contents($file['tmp_name']);
                    if ($contents === false || preg_match('//u', $contents) !== 1) {
                        $error = 'File teks harus menggunakan encoding UTF-8.';
                    } else {
                        $records = importTextRows($contents);
                        if (count($records) > 500) {
                            $error = 'Satu kali impor dibatasi maksimal 500 data.';
                        }
                    }
                }
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception | \PhpOffice\PhpSpreadsheet\Exception $exception) {
                $error = 'File Excel tidak dapat dibaca. Pastikan file .xlsx valid dan tidak rusak.';
            }

            $items = [];
            foreach ($records as $record) {
                $raw = $record['raw'];
                $extraNotes = $record['extra'];
                $line = $record['line'];
                $name = isset($raw['name']) && (is_string($raw['name']) || is_numeric($raw['name']))
                    ? trim((string) $raw['name'])
                    : '';
                if ($name === '' || mb_strlen($name) > 120) {
                    $error = "Nama langganan kosong atau terlalu panjang pada baris {$line}. Isi nama agar data dapat disimpan.";
                    break;
                }

                $notes = isset($raw['notes']) ? trim((string) $raw['notes']) : '';
                $addNote = static function ($label, $value) use (&$notes) {
                    $value = trim((string) $value);
                    if ($value !== '') {
                        $notes .= ($notes === '' ? '' : ' | ') . $label . ': ' . $value;
                    }
                };
                foreach ($extraNotes as $extraNote) {
                    $notes .= ($notes === '' ? '' : ' | ') . $extraNote;
                }

                $price = importExcelPrice($raw['price'] ?? null);
                if ($price === false || ($price !== null && $price > 9999999999.99)) {
                    $addNote('Harga tidak terbaca', $raw['price'] ?? '');
                    $price = null;
                }

                $cycleValue = $raw['cycle'] ?? '';
                $cycle = $cycleValue === '' ? 'monthly' : importExcelCycle($cycleValue);
                if ($cycle === null) {
                    $addNote('Siklus tidak terbaca', $cycleValue);
                    $cycle = 'monthly';
                }

                $date = importTextDate($raw['renewal_date'] ?? null);
                if (!importDateIsValid($date)) {
                    $addNote('Tanggal perpanjangan tidak terbaca', $date);
                    $date = null;
                }

                $time = isset($raw['renewal_time']) && trim((string) $raw['renewal_time']) !== ''
                    ? trim((string) $raw['renewal_time'])
                    : null;
                if (!importTimeIsValid($time)) {
                    $addNote('Waktu perpanjangan tidak terbaca', $time);
                    $time = null;
                }
                if ($date === null && $time !== null) {
                    $addNote('Waktu perpanjangan tanpa tanggal', $time);
                    $time = null;
                }

                $activeValue = $raw['active'] ?? null;
                $active = importExcelActive($activeValue);
                if ($active === null) {
                    $addNote('Status tidak terbaca', $activeValue);
                    $active = 1;
                }
                if (mb_strlen($notes) > 500) {
                    $error = "Catatan pada baris {$line} melebihi batas 500 karakter. Ringkas kolom tambahan lalu coba lagi.";
                    break;
                }

                $items[] = [
                    'name' => $name,
                    'price' => $price,
                    'cycle' => $cycle,
                    'renewal_date' => $date,
                    'renewal_time' => $time,
                    'notes' => $notes !== '' ? $notes : null,
                    'active' => $active,
                ];
            }
            if ($error === '' && $items === []) {
                $error = 'File belum berisi data langganan. Untuk file teks, isi satu nama langganan per baris atau gunakan header dan kolom.';
            }

            if ($error === '') {
                $_SESSION['pending_import_items'] = $items;
                $_SESSION['pending_import_index'] = 0;
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impor Langganan - LanggananKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="syle.css">
</head>
<body>
<main class="container">
    <section class="page-card">
        <a class="brand" href="index.php">Langganan<span>Ku</span></a>
        <h1>Impor langganan</h1>
        <p class="page-description">Impor Excel .xlsx atau file teks .txt/.csv tanpa menghapus data yang sudah ada. Maksimal 500 data dan ukuran file 5 MB. Nama wajib diisi; kolom lainnya boleh dikosongkan.</p>
        <?php if ($error !== ''): ?><div class="error-msg" role="alert"><?= h($error) ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="success-msg" role="status"><?= h($success) ?></div><?php endif; ?>
        <?php $hasPendingImport = isset($_SESSION['pending_import_items']) && is_array($_SESSION['pending_import_items']); ?>
        <?php if ($hasPendingImport): ?>
            <form method="post" class="pending-import-cancel">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="cancel_pending_import">
                <p class="page-description">Ada data impor yang belum selesai. Lanjutkan dari dashboard atau batalkan sisa data yang belum disimpan. Langganan yang sudah tersimpan tidak akan dihapus.</p>
                <div class="page-actions">
                    <a class="btn btn-secondary" href="index.php">Lanjutkan di dashboard</a>
                    <button class="btn btn-secondary" type="submit">Batalkan sisa impor</button>
                </div>
            </form>
        <?php endif; ?>
        <p class="page-description">Gunakan kolom Nama, Harga, Siklus, Tanggal Perpanjangan, Waktu Perpanjangan, Catatan, dan Aktif. File .txt satu nama per baris atau gunakan kolom yang dipisahkan koma, titik koma, tab, atau |. Kolom tambahan yang tidak dikenal akan disimpan di Catatan. Data yang kurang akan diminta untuk dilengkapi setelah impor.</p>
        <?php if (!$hasPendingImport): ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label for="backup">File Excel atau teks (.xlsx, .txt, .csv)</label>
                <input id="backup" name="backup" type="file" accept=".xlsx,.txt,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,text/csv" required>
            </div>
            <div class="page-actions">
                <a class="btn btn-secondary" href="index.php">Kembali</a>
                <a class="btn btn-secondary" href="impor.php?template=1">Unduh template</a>
                <button class="btn btn-primary" type="submit">Impor langganan</button>
            </div>
        </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

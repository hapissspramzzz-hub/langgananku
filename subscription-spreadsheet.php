<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function subscriptionSpreadsheetColumns()
{
    return [
        'name' => 'Nama',
        'price' => 'Harga',
        'cycle' => 'Siklus',
        'renewal_date' => 'Tanggal Perpanjangan',
        'renewal_time' => 'Waktu Perpanjangan',
        'notes' => 'Catatan',
        'active' => 'Aktif',
    ];
}

function downloadSubscriptionSpreadsheet($rows, $filename)
{
    if (!class_exists(\ZipArchive::class)) {
        http_response_code(503);
        exit('Fitur Excel membutuhkan ekstensi PHP zip. Aktifkan extension=zip pada php.ini lalu restart PHP.');
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Langganan');

    $columns = subscriptionSpreadsheetColumns();
    $sheet->fromArray(array_values($columns), null, 'A1');
    $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('5A4FCF');
    $sheet->freezePane('A2');
    $sheet->setAutoFilter('A1:G1');

    $widths = [28, 16, 20, 24, 22, 36, 14];
    foreach ($widths as $index => $width) {
        $sheet->getColumnDimension(chr(65 + $index))->setWidth($width);
    }

    foreach ($rows as $index => $row) {
        foreach (array_values($row) as $column => $value) {
            $address = chr(65 + $column) . ($index + 2);
            if ($value === null || $value === '') {
                $sheet->setCellValue($address, null);
            } elseif (is_int($value) || is_float($value)) {
                $sheet->setCellValue($address, $value);
            } else {
                $sheet->setCellValueExplicit($address, (string) $value, DataType::TYPE_STRING);
            }
        }
    }

    if ($rows !== []) {
        $lastRow = count($rows) + 1;
        $sheet->getStyle('D2:D' . $lastRow)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('E2:E' . $lastRow)->getNumberFormat()->setFormatCode('hh:mm');
        $sheet->getStyle('B2:B' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, must-revalidate');

    (new Xlsx($spreadsheet))->save('php://output');
    $spreadsheet->disconnectWorksheets();
    exit;
}

function downloadSubscriptionSpreadsheetTemplate()
{
    downloadSubscriptionSpreadsheet([], 'template-impor-langganan.xlsx');
}

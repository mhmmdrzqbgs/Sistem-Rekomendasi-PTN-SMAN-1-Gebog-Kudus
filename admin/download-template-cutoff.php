<?php
/**
 * Admin - Download Template Data Acuan (SNBT/SNBP)
 */
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// --- 1. HEADER KOLOM ---
$headers = [
    'A' => 'Nama PTN',
    'B' => 'Nama Program Studi',
    'C' => 'Jalur (SNBT/SNBP)',
    'D' => 'Nilai (Passing Grade / Rata Rapor)',
    'E' => 'Tahun Data'
];

// --- 2. STYLE HEADER ---
$headerStyle = [
    'font' => [
        'bold' => true, 
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID, 
        'startColor' => ['rgb' => '059669'] // Emerald 600
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
    ]
];

// Tulis Header
foreach ($headers as $col => $val) {
    $sheet->setCellValue($col . '1', $val);
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(25);

// --- 3. CONTOH DATA (DUMMY) ---
$dataContoh = [
    ['Universitas Indonesia', 'Pendidikan Dokter', 'SNBT', 750.50, 2024],
    ['Universitas Gadjah Mada', 'Psikologi', 'SNBP', 90.25, 2024],
    ['Institut Teknologi Bandung', 'Sekolah Teknik Elektro & Informatika (STEI)', 'SNBT', 720.00, 2024],
    ['Universitas Diponegoro', 'Hukum', 'SNBP', 88.50, 2024],
    ['Universitas Airlangga', 'Farmasi', 'SNBT', 660.00, 2024],
];

$row = 2;
foreach ($dataContoh as $d) {
    $sheet->setCellValue('A' . $row, $d[0]);
    $sheet->setCellValue('B' . $row, $d[1]);
    $sheet->setCellValue('C' . $row, $d[2]);
    $sheet->setCellValue('D' . $row, $d[3]);
    $sheet->setCellValue('E' . $row, $d[4]);
    $row++;
}

// Tambahkan Note Kecil
$sheet->setCellValue('G2', 'PETUNJUK PENGISIAN:');
$sheet->setCellValue('G3', '1. Nama PTN & Prodi harus sesuai database.');
$sheet->setCellValue('G4', '2. Jalur isi dengan "SNBT" atau "SNBP".');
$sheet->setCellValue('G5', '3. Nilai SNBT skala 0-1000, SNBP skala 0-100.');
$sheet->getStyle('G2')->getFont()->setBold(true);

// --- 4. OUTPUT DOWNLOAD ---
$filename = 'Template_Data_Acuan_SNBT_SNBP.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
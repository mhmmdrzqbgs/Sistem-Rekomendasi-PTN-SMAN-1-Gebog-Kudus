<?php
/**
 * Admin - Download Template Excel
 * Generates dynamic templates based on database structure
 */
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Pastikan path vendor benar

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$db = Database::getInstance();
$type = $_GET['type'] ?? 'siswa';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// --- STYLE HEADER ---
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']], // Indigo Color
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$headers = [];
$filename = "template_data.xlsx";

// --- LOGIKA TEMPLATE ---
switch ($type) {
    case 'siswa':
        $filename = "Template_Data_Siswa.xlsx";
        $headers = ['Nama Lengkap', 'NISN', 'Password', 'Kelas', 'Rumpun', 'Sekolah Asal'];
        // Contoh Data
        $sheet->setCellValue('A2', 'Budi Santoso');
        $sheet->setCellValue('B2', '0012345678');
        $sheet->setCellValue('C2', '123456');
        $sheet->setCellValue('D2', 'X E-1');
        $sheet->setCellValue('E2', 'A'); // Contoh Rumpun A
        $sheet->setCellValue('F2', 'SMP N 1 Kudus');
        
        // Tambahkan Keterangan Rumpun
        $sheet->setCellValue('H2', 'KETERANGAN RUMPUN:');
        $sheet->setCellValue('H3', 'A, B, C, D, E, F, G, I, J');
        break;

    case 'rapor':
        $filename = "Template_Nilai_Rapor.xlsx";
        // Header Wajib
        $headers = ['NISN', 'Semester'];
        
        // Header Dinamis dari Database Master Mapel
        $mapels = $db->query("SELECT nama_mapel FROM master_mapel ORDER BY kelompok DESC, id ASC");
        foreach ($mapels as $m) {
            $headers[] = $m['nama_mapel'];
        }
        
        // Contoh Data
        $sheet->setCellValue('A2', '0012345678');
        $sheet->setCellValue('B2', '1'); // Semester 1
        // Set nilai 0 untuk contoh
        for ($i = 2; $i < count($headers); $i++) {
            $sheet->setCellValueByColumnAndRow($i + 1, 2, 85);
        }
        break;

    case 'tryout':
        $filename = "Template_Nilai_Tryout.xlsx";
        $headers = [
            'NISN', 'Tanggal Tes', 'Penalaran Umum', 'Pengetahuan & Pemahaman Umum', 
            'Pemahaman Bacaan & Menulis', 'Pengetahuan Kuantitatif', 
            'Literasi Bahasa Indonesia', 'Literasi Bahasa Inggris', 'Penalaran Matematika', 'Catatan'
        ];
        
        // Contoh
        $sheet->setCellValue('A2', '0012345678');
        $sheet->setCellValue('B2', date('Y-m-d'));
        $sheet->setCellValue('C2', '650'); // PU
        $sheet->setCellValue('D2', '600'); // PPU
        // ... dst
        $sheet->setCellValue('L2', 'TO Nasional 1');
        break;

    case 'tka':
        $filename = "Template_Nilai_TKA.xlsx";
        $headers = [
            'NISN', 'Matematika', 'B. Indonesia', 'B. Inggris', 
            'Mapel Pilihan 1', 'Nilai Pilihan 1', 
            'Mapel Pilihan 2', 'Nilai Pilihan 2'
        ];
        // Contoh
        $sheet->setCellValue('A2', '0012345678');
        $sheet->setCellValue('B2', '80');
        $sheet->setCellValue('E2', 'Fisika');
        $sheet->setCellValue('F2', '85');
        break;
}

// --- TULIS HEADER ---
$columnIndex = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($columnIndex . '1', $h);
    $sheet->getColumnDimension($columnIndex)->setAutoSize(true);
    $columnIndex++;
}

// Terapkan Style
$sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

// --- OUTPUT DOWNLOAD ---
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
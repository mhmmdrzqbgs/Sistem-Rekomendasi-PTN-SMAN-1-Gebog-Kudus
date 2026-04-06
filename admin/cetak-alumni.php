<?php
/**
 * Admin - Cetak Laporan Alumni (Format Resmi & Konsisten)
 * Fitur: Layout Modern, Tanggal Indo, Margin Aman
 */
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

// --- LOGIC FILTER ---
$yearFilter = get('year');
$search = get('search');

$sql = "SELECT sp.*, u.nama 
        FROM siswa_profile sp 
        JOIN users u ON sp.user_id = u.id 
        WHERE sp.status = 'Lulus'";

$params = [];

if ($yearFilter) {
    $sql .= " AND sp.tahun_lulus = ?";
    $params[] = $yearFilter;
}

if ($search) {
    $sql .= " AND (u.nama LIKE ? OR sp.nisn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY sp.tahun_lulus DESC, LENGTH(sp.kelas) ASC, sp.kelas ASC, u.nama ASC";
$alumniList = $db->query($sql, $params);

// Variabel Judul
$judulLaporan = "DATA ALUMNI / LULUSAN";
$subJudul = "Tahun Lulus: " . ($yearFilter ? $yearFilter : 'SEMUA ANGKATAN');

// --- FUNGSI TANGGAL INDONESIA ---
function getIndoDate() {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return date('d') . ' ' . $bulan[(int)date('m')] . ' ' . date('Y');
}
$tglCetak = getIndoDate();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Data Alumni</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #525659; }
        
        /* === PRINT SETTINGS === */
        @media print {
            @page { size: auto; margin: 20mm; }
            body { background-color: white; margin: 0; }
            .no-print { display: none !important; }
            .sheet { width: 100%; margin: 0; padding: 0 !important; box-shadow: none; border: none; }
            .bg-gray-100 { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }
        }

        /* === SCREEN PREVIEW === */
        .sheet { 
            width: 210mm; min-height: 297mm; 
            margin: 40px auto; background: white; 
            padding: 10mm 20mm; position: relative; 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); 
        }
    </style>
</head>
<body class="text-gray-900">

    <div class="fixed bottom-8 right-8 no-print flex flex-col gap-3 z-50">
        <button onclick="window.print()" class="group flex items-center justify-center w-14 h-14 bg-blue-600 text-white rounded-full shadow-lg hover:bg-blue-700 transition-all focus:outline-none" title="Cetak PDF">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2-4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
        </button>
        <button onclick="window.close()" class="group flex items-center justify-center w-14 h-14 bg-gray-600 text-white rounded-full shadow-lg hover:bg-gray-700 transition-all focus:outline-none" title="Tutup">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <div class="sheet">
        <div class="border-b-4 border-double border-gray-900 pb-4 mb-6 flex items-center gap-4">
            <div class="shrink-0">
                <img src="../assets/img/logo.jpeg" alt="Logo" class="w-20 h-auto object-contain">
            </div>
            <div class="text-center flex-1">
                <h3 class="text-xs font-bold text-gray-600 uppercase tracking-[0.2em]">Pemerintah Provinsi Jawa Tengah</h3>
                <h3 class="text-xs font-bold text-gray-600 uppercase tracking-[0.2em]">Dinas Pendidikan dan Kebudayaan</h3>
                <h1 class="text-2xl font-black text-gray-900 uppercase mt-1 tracking-tight">SMA NEGERI 1 GEBOG KUDUS</h1>
                <p class="text-xs text-gray-700 mt-1 font-medium">Jl. PR. Sukun, Gondosari, Kec. Gebog, Kabupaten Kudus, Jawa Tengah 59333</p>
                <p class="text-[10px] text-gray-600">Email: sma1gebog@yahoo.co.id | Website: sman1gebog.sch.id</p>
            </div>
            <div class="w-20"></div> 
        </div>

        <div class="text-center mb-6">
            <h2 class="text-lg font-bold text-gray-900 uppercase underline decoration-2 underline-offset-4 mb-1"><?= $judulLaporan ?></h2>
            <div class="inline-block bg-gray-100 border border-gray-300 px-3 py-1 rounded-full mt-1">
                <p class="text-xs font-bold text-gray-700 uppercase tracking-wide"><?= $subJudul ?></p>
            </div>
        </div>

        <table class="w-full text-left border-collapse border border-gray-400 text-xs">
            <thead>
                <tr class="bg-gray-100 text-gray-900 uppercase tracking-wider font-bold">
                    <th class="border border-gray-400 px-2 py-2 text-center w-10">No</th>
                    <th class="border border-gray-400 px-3 py-2">Nama Lengkap</th>
                    <th class="border border-gray-400 px-2 py-2 w-28 text-center">NISN</th>
                    <th class="border border-gray-400 px-2 py-2 w-24 text-center">Kelas Akhir</th>
                    <th class="border border-gray-400 px-2 py-2 w-24 text-center">Thn Lulus</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alumniList)): ?>
                    <tr><td colspan="5" class="border border-gray-400 px-4 py-8 text-center italic text-gray-500">Data tidak ditemukan.</td></tr>
                <?php else: ?>
                    <?php $no=1; foreach ($alumniList as $s): ?>
                        <tr class="group">
                            <td class="border border-gray-400 px-2 py-1.5 text-center font-medium"><?= $no++ ?></td>
                            <td class="border border-gray-400 px-3 py-1.5 font-bold uppercase text-gray-800"><?= sanitize($s['nama']) ?></td>
                            <td class="border border-gray-400 px-2 py-1.5 text-center font-mono text-gray-600"><?= $s['nisn'] ?></td>
                            <td class="border border-gray-400 px-2 py-1.5 text-center"><?= $s['kelas'] ?></td>
                            <td class="border border-gray-400 px-2 py-1.5 text-center font-bold text-blue-700"><?= $s['tahun_lulus'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="mt-12 flex justify-end break-inside-avoid">
            <div class="text-center w-64">
                <p class="text-xs text-gray-800 font-medium">Kudus, <?= $tglCetak ?></p>
                <p class="text-xs text-gray-800 mb-20 font-bold">Kepala Sekolah</p>
                
                <p class="text-xs font-bold text-gray-900 border-b border-gray-900 inline-block px-2 min-w-[180px] mb-1">
                    ( ........................................... )
                </p>
                <p class="text-[10px] text-gray-600 font-medium">NIP. ...........................................</p>
            </div>
        </div>
    </div>

    <script>
        // Opsional: Langsung print saat dibuka
        // window.onload = function() { setTimeout(window.print, 500); }
    </script>
</body>
</html>
<?php
/**
 * Admin - Print Laporan Rekomendasi (Unified Logic with Smart Filter)
 * Fitur:
 * 1. Logika sama persis dengan laporan.php (Smart Filter Display).
 * 2. Tampilan Cetak Resmi (Kop Surat, TTD, Margin Aman).
 */
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
$filter = get('filter');

// 1. QUERY UTAMA (SAMA DENGAN LAPORAN.PHP)
$sql = "
    SELECT sp.*, u.nama, u.username,
           -- Default Data (Top 1) - Tetap diambil untuk fallback (No Filter View)
           (SELECT p.nama FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Saintek%' OR p.rumpun LIKE '%Teknik%' OR p.rumpun LIKE '%Sains%') ORDER BY r.ranking ASC LIMIT 1) as saintek_prodi,
           (SELECT pt.singkatan FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id JOIN ptn pt ON p.ptn_id = pt.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Saintek%' OR p.rumpun LIKE '%Teknik%' OR p.rumpun LIKE '%Sains%') ORDER BY r.ranking ASC LIMIT 1) as saintek_ptn,
           (SELECT r.peluang FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Saintek%' OR p.rumpun LIKE '%Teknik%' OR p.rumpun LIKE '%Sains%') ORDER BY r.ranking ASC LIMIT 1) as saintek_peluang,

           (SELECT p.nama FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Soshum%' OR p.rumpun LIKE '%Sosial%' OR p.rumpun LIKE '%Hum%' OR p.nama LIKE '%Manajemen%') ORDER BY r.ranking ASC LIMIT 1) as soshum_prodi,
           (SELECT pt.singkatan FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id JOIN ptn pt ON p.ptn_id = pt.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Soshum%' OR p.rumpun LIKE '%Sosial%' OR p.rumpun LIKE '%Hum%' OR p.nama LIKE '%Manajemen%') ORDER BY r.ranking ASC LIMIT 1) as soshum_ptn,
           (SELECT r.peluang FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Soshum%' OR p.rumpun LIKE '%Sosial%' OR p.rumpun LIKE '%Hum%' OR p.nama LIKE '%Manajemen%') ORDER BY r.ranking ASC LIMIT 1) as soshum_peluang

    FROM siswa_profile sp
    JOIN users u ON sp.user_id = u.id
";

// 2. FILTERING
$params = [];
$judulFilter = "Semua Data Siswa";

if ($filter === 'tinggi') {
    $sql .= " WHERE EXISTS (SELECT 1 FROM rekomendasi r WHERE r.siswa_id = sp.id AND r.peluang = 'Tinggi') ";
    $judulFilter = "Kategori: Peluang Tinggi";
} elseif ($filter === 'sedang') {
    $sql .= " WHERE EXISTS (SELECT 1 FROM rekomendasi r WHERE r.siswa_id = sp.id AND r.peluang = 'Sedang') ";
    $judulFilter = "Kategori: Peluang Sedang";
} elseif ($filter === 'rendah') {
    $sql .= " WHERE EXISTS (SELECT 1 FROM rekomendasi r WHERE r.siswa_id = sp.id AND r.peluang = 'Rendah') ";
    $judulFilter = "Kategori: Perlu Pendampingan";
}

$sql .= " ORDER BY sp.kelas ASC, u.nama ASC";
$siswaList = $db->query($sql, $params);

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
    <title>Cetak Laporan - <?= $judulFilter ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        body { font-family: 'Inter', sans-serif; background-color: #525659; }

        /* Print Settings */
        @media print {
            @page { size: auto; margin: 20mm; }
            body { background-color: white; margin: 0; }
            .no-print { display: none !important; }
            .sheet { width: 100%; margin: 0; padding: 0 !important; box-shadow: none; border: none; }
            .bg-gray-100 { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }
        }

        /* Screen Preview */
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

        <div class="text-center mb-8">
            <h2 class="text-xl font-bold text-gray-900 uppercase underline decoration-2 underline-offset-4 mb-2">Laporan Hasil Analisis Rekomendasi Studi</h2>
            <div class="inline-block bg-gray-100 border border-gray-200 px-4 py-1 rounded-full">
                <p class="text-sm font-semibold text-gray-700">Filter Data: <span class="text-blue-700"><?= $judulFilter ?></span></p>
            </div>
        </div>

        <table class="w-full text-left border-collapse border border-gray-400 text-xs">
            <thead>
                <tr class="bg-gray-100 text-gray-800 uppercase tracking-wider font-bold">
                    <th class="border border-gray-400 px-3 py-3 text-center w-10">No</th>
                    <th class="border border-gray-400 px-3 py-3 w-1/4">Nama Siswa</th>
                    <?php if($filter): ?>
                        <th class="border border-gray-400 px-3 py-3 w-1/3">Rekomendasi Saintek</th>
                        <th class="border border-gray-400 px-3 py-3 w-1/3">Rekomendasi Soshum</th>
                    <?php else: ?>
                        <th class="border border-gray-400 px-3 py-3 w-1/3">Top Saintek</th>
                        <th class="border border-gray-400 px-3 py-3 w-1/3">Top Soshum</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($siswaList)): ?>
                    <tr><td colspan="4" class="border border-gray-400 px-4 py-8 text-center text-gray-500 italic">Tidak ada data siswa untuk kategori ini.</td></tr>
                <?php else: ?>
                    <?php $no=1; foreach ($siswaList as $s): ?>
                        <?php 
                            $saintekContent = [];
                            $soshumContent = [];

                            if ($filter) {
                                $filterCap = ucfirst($filter);
                                $allRekom = $db->query("
                                    SELECT p.nama, p.rumpun, pt.singkatan, r.peluang 
                                    FROM rekomendasi r 
                                    JOIN prodi p ON r.prodi_id = p.id 
                                    JOIN ptn pt ON p.ptn_id = pt.id
                                    WHERE r.siswa_id = ? AND r.peluang = ?
                                    ORDER BY r.ranking ASC
                                ", [$s['id'], $filterCap]);

                                foreach ($allRekom as $r) {
                                    $style = match($r['peluang']) {
                                        'Tinggi' => 'text-emerald-800 bg-emerald-50 border-emerald-200',
                                        'Sedang' => 'text-amber-800 bg-amber-50 border-amber-200',
                                        default => 'text-red-800 bg-red-50 border-red-200'
                                    };
                                    
                                    $item = "<div class='mb-2 pb-2 border-b border-gray-200 last:border-0 last:mb-0 last:pb-0'>
                                                <div class='font-bold text-gray-800 text-[11px]'>{$r['nama']}</div>
                                                <div class='text-[10px] text-gray-600 italic'>{$r['singkatan']}</div>
                                                <span class='inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold border $style'>{$r['peluang']}</span>
                                             </div>";

                                    if (stripos($r['rumpun'], 'Saintek') !== false || stripos($r['rumpun'], 'Teknik') !== false || stripos($r['rumpun'], 'Sains') !== false) {
                                        $saintekContent[] = $item;
                                    } else {
                                        $soshumContent[] = $item;
                                    }
                                }
                            }
                        ?>
                        <tr class="page-break group">
                            <td class="border border-gray-400 px-3 py-2 text-center align-top font-medium text-gray-600"><?= $no++ ?></td>
                            
                            <td class="border border-gray-400 px-3 py-2 align-top">
                                <div class="font-bold text-gray-900 text-sm"><?= sanitize($s['nama']) ?></div>
                                <div class="text-[10px] text-gray-500 font-mono mt-0.5"><?= sanitize($s['username']) ?></div>
                                <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-100 border border-gray-200 mt-1"><?= $s['kelas'] ?? '-' ?></span>
                            </td>

                            <td class="border border-gray-400 px-3 py-2 align-top">
                                <?php if ($filter): ?>
                                    <?= empty($saintekContent) ? '<span class="text-gray-400 text-xs italic">-</span>' : implode('', $saintekContent) ?>
                                <?php else: ?>
                                    <?php if($s['saintek_prodi']): 
                                        $bg = match($s['saintek_peluang']) {
                                            'Tinggi' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'Sedang' => 'bg-amber-50 text-amber-800 border-amber-200',
                                            default => 'bg-red-50 text-red-800 border-red-200'
                                        };
                                    ?>
                                        <div class="font-bold text-gray-800 text-[11px]"><?= $s['saintek_prodi'] ?></div>
                                        <div class="text-[10px] text-gray-600 mt-0.5"><?= $s['saintek_ptn'] ?></div>
                                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[9px] font-bold border <?= $bg ?>"><?= strtoupper($s['saintek_peluang']) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic text-[10px]">-</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td class="border border-gray-400 px-3 py-2 align-top">
                                <?php if ($filter): ?>
                                    <?= empty($soshumContent) ? '<span class="text-gray-400 text-xs italic">-</span>' : implode('', $soshumContent) ?>
                                <?php else: ?>
                                    <?php if($s['soshum_prodi']): 
                                        $bg = match($s['soshum_peluang']) {
                                            'Tinggi' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'Sedang' => 'bg-amber-50 text-amber-800 border-amber-200',
                                            default => 'bg-red-50 text-red-800 border-red-200'
                                        };
                                    ?>
                                        <div class="font-bold text-gray-800 text-[11px]"><?= $s['soshum_prodi'] ?></div>
                                        <div class="text-[10px] text-gray-600 mt-0.5"><?= $s['soshum_ptn'] ?></div>
                                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[9px] font-bold border <?= $bg ?>"><?= strtoupper($s['soshum_peluang']) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic text-[10px]">-</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="mt-12 flex justify-end break-inside-avoid">
            <div class="text-center w-64">
                <p class="text-sm text-gray-800 font-medium">Kudus, <?= $tglCetak ?></p>
                <p class="text-sm text-gray-800 mb-20 font-bold">Guru BK / Koordinator</p>
                
                <p class="text-sm font-bold text-gray-900 border-b border-gray-900 inline-block px-2 min-w-[200px] mb-1">
                    ( ........................................... )
                </p>
                <p class="text-xs text-gray-600 font-medium">NIP. ...........................................</p>
            </div>
        </div>

    </div>

    <script>
        // window.onload = function() { setTimeout(window.print, 500); }
    </script>
</body>
</html>
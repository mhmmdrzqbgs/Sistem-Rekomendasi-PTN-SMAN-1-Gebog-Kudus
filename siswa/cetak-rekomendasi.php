<?php
/**
 * Siswa - Cetak Rekomendasi (Unified Layout with Analisis)
 */
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

// Validasi Akses
if (!isset($_SESSION['user_id'])) die("Akses ditolak");

// Auto-Fix Session
$db = Database::getInstance();
if (!isset($_SESSION['role']) || !isset($_SESSION['siswa_id'])) {
    $userId = $_SESSION['user_id'];
    $user = $db->queryOne("SELECT role FROM users WHERE id = ?", [$userId]);
    $profile = $db->queryOne("SELECT id FROM siswa_profile WHERE user_id = ?", [$userId]);
    if ($user && $profile) {
        $_SESSION['role'] = $user['role'];
        $_SESSION['siswa_id'] = $profile['id'];
    } else {
        die("Data profil tidak ditemukan.");
    }
}

if ($_SESSION['role'] !== 'siswa') die("Akses Ditolak");

$siswaId = $_SESSION['siswa_id'];

// --- FUNGSI TANGGAL INDO ---
function getIndoDate() {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return date('d') . ' ' . $bulan[(int)date('m')] . ' ' . date('Y');
}
$tglCetak = getIndoDate();

// 1. AMBIL DATA
$siswa = $db->queryOne("SELECT sp.*, u.nama FROM siswa_profile sp JOIN users u ON sp.user_id = u.id WHERE sp.id = ?", [$siswaId]);

// 2. AMBIL REKOMENDASI
$rekomendasi = $db->query("
    SELECT r.*, 
           p.nama as prodi_nama, p.rumpun,
           p.daya_tampung_snbp, p.daya_tampung_snbt,
           pt.nama as ptn_nama, pt.singkatan
    FROM rekomendasi r
    JOIN prodi p ON r.prodi_id = p.id
    JOIN ptn pt ON p.ptn_id = pt.id
    WHERE r.siswa_id = ?
    ORDER BY r.jalur, r.ranking ASC
", [$siswaId]);

// FUNGSI GROUPING
function groupData($data, $jalur) {
    $filtered = array_filter($data, fn($r) => $r['jalur'] === $jalur);
    $groups = [
        'minat_saintek' => [], 'minat_soshum' => [],
        'sys_saintek' => [], 'sys_soshum' => []
    ];
    foreach ($filtered as $item) {
        $isMinat = (stripos($item['alasan'], '[Minat]') !== false || stripos($item['alasan'], '[Pilihan Siswa]') !== false);
        $isSoshum = false;
        if (stripos($item['rumpun'], 'Soshum') !== false || stripos($item['rumpun'], 'Sosial') !== false) {
            $isSoshum = true;
        } else {
            $soshumKeywords = ['Hukum', 'Ekonomi', 'Manajemen', 'Akuntansi', 'Sastra', 'Sosial', 'Politik', 'Sejarah', 'Administrasi', 'Seni', 'Pendidikan'];
            foreach ($soshumKeywords as $k) { if (stripos($item['prodi_nama'], $k) !== false) { $isSoshum = true; break; } }
        }
        $key = ($isMinat ? 'minat_' : 'sys_') . ($isSoshum ? 'soshum' : 'saintek');
        $groups[$key][] = $item;
    }
    return $groups;
}

$snbp = groupData($rekomendasi, 'SNBP');
$snbt = groupData($rekomendasi, 'SNBT');

// Helper Render Tabel (Dengan class section-break-avoid)
function renderTableNew($data, $title) {
    if (empty($data)) return;
    ?>
    <div class="mb-4 section-break-avoid"> 
        <h4 class="font-bold text-gray-700 text-[10px] uppercase mb-1 border-b border-gray-300 pb-1"><?= $title ?></h4>
        <table class="w-full text-left border-collapse border border-gray-400 text-[9px]">
            <thead>
                <tr class="bg-gray-100 text-gray-900 uppercase tracking-wider font-bold">
                    <th class="border border-gray-400 px-2 py-1 text-center w-6">No</th>
                    <th class="border border-gray-400 px-2 py-1">Program Studi</th>
                    <th class="border border-gray-400 px-2 py-1 w-1/3">Perguruan Tinggi</th>
                    <th class="border border-gray-400 px-2 py-1 text-center w-8">DT</th>
                    <th class="border border-gray-400 px-2 py-1 text-center w-16">Peluang</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $i => $row): 
                    $dt = ($row['jalur'] == 'SNBP') ? $row['daya_tampung_snbp'] : $row['daya_tampung_snbt'];
                    $bg = 'bg-gray-100 text-gray-800 border-gray-300'; 
                    if ($row['peluang'] == 'Tinggi') $bg = 'bg-emerald-100 text-emerald-800 border-emerald-200 print:border-gray-400 print:text-black';
                    if ($row['peluang'] == 'Sedang') $bg = 'bg-amber-100 text-amber-800 border-amber-200 print:border-gray-400 print:text-black';
                    if ($row['peluang'] == 'Rendah') $bg = 'bg-rose-100 text-rose-800 border-rose-200 print:border-gray-400 print:text-black';
                ?>
                <tr>
                    <td class="border border-gray-400 px-2 py-1 text-center font-medium"><?= $i + 1 ?></td>
                    <td class="border border-gray-400 px-2 py-1 font-bold"><?= $row['prodi_nama'] ?></td>
                    <td class="border border-gray-400 px-2 py-1"><?= $row['ptn_nama'] ?> (<?= $row['singkatan'] ?>)</td>
                    <td class="border border-gray-400 px-2 py-1 text-center"><?= $dt ?></td>
                    <td class="border border-gray-400 px-2 py-1 text-center">
                        <span class="px-1.5 py-0.5 rounded text-[8px] font-bold border <?= $bg ?> inline-block w-full">
                            <?= strtoupper($row['peluang']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Helper Section Wrapper
function renderJalurSection($groupData, $jalurTitle) {
    $isEmpty = empty($groupData['minat_saintek']) && empty($groupData['minat_soshum']) && 
               empty($groupData['sys_saintek']) && empty($groupData['sys_soshum']);
    if ($isEmpty) return;
    ?>
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-2 border-b-2 border-gray-800 pb-1">
            <div class="w-1.5 h-5 bg-gray-800"></div>
            <h3 class="font-bold text-gray-900 uppercase tracking-wide text-xs"><?= $jalurTitle ?></h3>
        </div>
        
        <div class="grid grid-cols-1 gap-2">
            <?php if($groupData['minat_saintek'] || $groupData['minat_soshum']): ?>
                <div class="bg-gray-50 p-2 rounded border border-gray-200 section-break-avoid">
                    <h4 class="font-bold text-gray-800 text-[10px] mb-2 uppercase flex items-center gap-2">
                        <span class="w-2 h-2 bg-gray-800 rounded-full"></span> Pilihan Minat Siswa
                    </h4>
                    <?php 
                    if($groupData['minat_saintek']) renderTableNew($groupData['minat_saintek'], 'Kelompok Saintek');
                    if($groupData['minat_soshum']) renderTableNew($groupData['minat_soshum'], 'Kelompok Soshum');
                    ?>
                </div>
            <?php endif; ?>

            <?php if($groupData['sys_saintek'] || $groupData['sys_soshum']): ?>
                <div class="bg-gray-50 p-2 rounded border border-gray-200 section-break-avoid">
                    <h4 class="font-bold text-gray-800 text-[10px] mb-2 uppercase flex items-center gap-2">
                        <span class="w-2 h-2 bg-gray-400 rounded-full"></span> Rekomendasi Sistem (Alternatif)
                    </h4>
                    <?php 
                    if($groupData['sys_saintek']) renderTableNew($groupData['sys_saintek'], 'Alternatif Saintek');
                    if($groupData['sys_soshum']) renderTableNew($groupData['sys_soshum'], 'Alternatif Soshum');
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rekomendasi - <?= $siswa['nama'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #525659; }
        
        /* === PRINT SETTINGS UTAMA (SAMA DENGAN CETAK ANALISIS) === */
        @media print {
            @page {
                size: auto;   /* Ikuti ukuran kertas di printer (A4/F4) */
                margin: 20mm; /* Margin aman 2cm keliling */
            }
            body { 
                background-color: white; 
                margin: 0; 
            }
            .no-print { display: none !important; }
            
            /* Reset Container untuk Print */
            .sheet { 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                box-shadow: none !important; 
            }
            
            /* Hapus background warna untuk hemat tinta */
            .bg-gray-50, .bg-emerald-50, .bg-rose-50 { 
                background-color: transparent !important; 
                border: 1px solid #e5e7eb !important; 
            }

            /* Mencegah Tabel Terpotong Jelek */
            thead { display: table-header-group; } /* Header tabel ulang di page baru */
            tr { page-break-inside: avoid; } /* Baris tidak boleh terpotong */
            
            /* Mencegah Blok Terpotong */
            .section-break-avoid { page-break-inside: avoid; break-inside: avoid; }
        }
        
        /* Tampilan Layar (Simulasi Kertas) */
        .sheet { 
            width: 210mm; /* Lebar A4 */
            min-height: 297mm; 
            margin: 40px auto; 
            background: white; 
            padding: 0; 
            padding-top: 10mm; padding-left: 10mm; padding-right: 10mm; padding-bottom: 10mm; /* Padding layar */
            position: relative; 
        }
    </style>
</head>
<body class="text-gray-900">

    <div class="fixed bottom-8 right-8 no-print flex flex-col gap-3 z-50">
        <button onclick="window.print()" class="group flex items-center justify-center w-14 h-14 bg-blue-600 text-white rounded-full shadow-lg hover:bg-blue-700 transition-all focus:outline-none" title="Cetak PDF">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2-4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
        </button>
        <a href="rekomendasi.php" class="group flex items-center justify-center w-14 h-14 bg-gray-600 text-white rounded-full shadow-lg hover:bg-gray-700 transition-all focus:outline-none" title="Kembali">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
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
            <h2 class="text-lg font-bold text-gray-900 uppercase underline decoration-2 underline-offset-4 mb-1">HASIL ANALISIS PELUANG SELEKSI PTN</h2>
            <p class="text-xs text-gray-500 italic">Dicetak pada: <?= $tglCetak ?></p>
        </div>

        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 text-xs section-break-avoid">
            <div class="grid grid-cols-[100px_10px_1fr] gap-y-1.5">
                <div class="font-bold text-gray-700">Nama Siswa</div><div>:</div><div class="font-bold text-gray-900 uppercase"><?= sanitize($siswa['nama']) ?></div>
                <div class="font-bold text-gray-700">Kelas / NISN</div><div>:</div><div class="font-bold text-gray-900"><?= $siswa['kelas'] ?? '-' ?> / <span class="font-mono font-normal"><?= $siswa['nisn'] ?? '-' ?></span></div>
                <div class="font-bold text-gray-700">Minat</div><div>:</div>
                <div class="text-gray-900">
                    <?php if($siswa['minat_saintek']): ?><span class="inline-block border border-gray-400 px-1 rounded mr-1">Saintek: <?= $siswa['minat_saintek'] ?></span><?php endif; ?>
                    <?php if($siswa['minat_soshum']): ?><span class="inline-block border border-gray-400 px-1 rounded">Soshum: <?= $siswa['minat_soshum'] ?></span><?php endif; ?>
                </div>
            </div>
        </div>

        <?php 
        renderJalurSection($snbp, 'HASIL ANALISIS JALUR SNBP (RAPOR)');
        renderJalurSection($snbt, 'HASIL ANALISIS JALUR SNBT (TRYOUT)');
        ?>

        <?php if (empty($rekomendasi)): ?>
            <div class="text-center py-8 border-2 border-dashed border-gray-300 rounded-lg">
                <p class="text-gray-500 italic text-xs">Belum ada data rekomendasi yang tersedia.</p>
            </div>
        <?php endif; ?>

        <div class="section-break-avoid">
            <div class="mt-8 p-3 border border-gray-300 bg-gray-50 rounded text-[10px] text-gray-600 text-justify italic leading-relaxed">
                <strong>CATATAN PENTING:</strong> 
                Hasil ini merupakan rekomendasi sistem menggunakan metode Hybrid Rule-Based Heuristic Ranking, bukan berarti benar-benar sesuai dengan kenyataan di lapangan 100%. Dokumen ini hanya sebagai rekomendasi pendukung untuk pertimbangan penentuan Program Studi dan PTN berdasarkan analisis nilai siswa saat ini. Keputusan akhir tetap berada di tangan siswa dan orang tua.
            </div>

            <div class="mt-12 flex justify-end">
                <div class="text-center w-64">
                    <p class="text-xs text-gray-800 font-medium">Kudus, <?= $tglCetak ?></p>
                    <p class="text-xs text-gray-800 mb-20 font-bold">Guru BK / Koordinator</p>
                    
                    <p class="text-xs font-bold text-gray-900 border-b border-gray-900 inline-block px-2 min-w-[180px] mb-1">
                        ( ........................................... )
                    </p>
                    <p class="text-[10px] text-gray-600 font-medium">NIP. ...........................................</p>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
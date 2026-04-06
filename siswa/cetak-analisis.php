<?php
/**
 * Siswa - Cetak Analisis Akademik (Print Optimized)
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
function getIndoDate($dateString = null) {
    $date = $dateString ? strtotime($dateString) : time();
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return date('d', $date) . ' ' . $bulan[(int)date('m', $date)] . ' ' . date('Y', $date);
}
$tglCetak = getIndoDate();

// --- AMBIL DATA ---
$siswa = $db->queryOne("SELECT sp.*, u.nama FROM siswa_profile sp JOIN users u ON sp.user_id = u.id WHERE sp.id = ?", [$siswaId]);
$nilaiRapor = $db->query("SELECT * FROM nilai_rapor WHERE siswa_id = ? ORDER BY semester ASC", [$siswaId]);
$mapelStats = $db->query("
    SELECT mm.nama_mapel, AVG(nrd.nilai) as avg_nilai
    FROM nilai_rapor nr
    JOIN nilai_rapor_detail nrd ON nr.id = nrd.nilai_rapor_id
    JOIN master_mapel mm ON nrd.master_mapel_id = mm.id
    WHERE nr.siswa_id = ? AND nrd.nilai > 0
    GROUP BY mm.id, mm.nama_mapel
    ORDER BY avg_nilai DESC
", [$siswaId]);
$tryout = $db->queryOne("SELECT * FROM nilai_tryout WHERE siswa_id = ? ORDER BY tanggal_tes DESC LIMIT 1", [$siswaId]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Analisis - <?= $siswa['nama'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #525659; }
        
        /* === PRINT SETTINGS UTAMA === */
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
                border: 1px solid #e5e7eb !important; /* Ganti bg dengan border tipis */
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
            padding: 0; /* Padding dihandle oleh @page saat print */
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
            <h2 class="text-lg font-bold text-gray-900 uppercase underline decoration-2 underline-offset-4 mb-1">LAPORAN ANALISIS PERFORMA AKADEMIK</h2>
            <p class="text-xs text-gray-500 italic">Dicetak pada: <?= $tglCetak ?></p>
        </div>

        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 text-xs section-break-avoid">
            <div class="grid grid-cols-[100px_10px_1fr] gap-y-1.5">
                <div class="font-bold text-gray-700">Nama Siswa</div><div>:</div><div class="font-bold text-gray-900 uppercase"><?= htmlspecialchars($siswa['nama']) ?></div>
                <div class="font-bold text-gray-700">Kelas / NISN</div><div>:</div><div class="font-bold text-gray-900"><?= $siswa['kelas'] ?? '-' ?> / <span class="font-mono font-normal"><?= $siswa['nisn'] ?? '-' ?></span></div>
            </div>
        </div>

        <div class="mb-6 section-break-avoid">
            <h3 class="font-bold text-gray-800 text-xs mb-2 uppercase border-b border-gray-300 pb-1">A. Perkembangan Rata-rata Rapor</h3>
            <table class="w-full text-left border-collapse border border-gray-400 text-xs">
                <thead>
                    <tr class="bg-gray-100 text-gray-900 uppercase tracking-wider font-bold">
                        <th class="border border-gray-400 px-3 py-2 text-center w-24">Semester</th>
                        <th class="border border-gray-400 px-3 py-2 text-center w-32">Rata-rata Nilai</th>
                        <th class="border border-gray-400 px-3 py-2 text-center">Predikat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($nilaiRapor)): ?>
                        <tr><td colspan="3" class="border border-gray-400 px-3 py-4 text-center italic text-gray-500">Belum ada data rapor.</td></tr>
                    <?php else: ?>
                        <?php foreach ($nilaiRapor as $nr): ?>
                            <tr>
                                <td class="border border-gray-400 px-3 py-2 text-center font-medium">Semester <?= $nr['semester'] ?></td>
                                <td class="border border-gray-400 px-3 py-2 text-center font-bold text-gray-800"><?= number_format($nr['rata_rata'], 2) ?></td>
                                <td class="border border-gray-400 px-3 py-2 text-center">
                                    <?php 
                                        if($nr['rata_rata'] >= 85) echo '<span class="text-emerald-700 font-bold">Sangat Baik</span>';
                                        elseif($nr['rata_rata'] >= 75) echo '<span class="text-blue-700 font-bold">Baik</span>';
                                        else echo '<span class="text-amber-700 font-bold">Cukup</span>';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mb-6 section-break-avoid">
            <h3 class="font-bold text-gray-800 text-xs mb-3 uppercase border-b border-gray-300 pb-1">B. Analisis Mata Pelajaran</h3>
            <div class="grid grid-cols-2 gap-6">
                <div class="border border-emerald-200 bg-emerald-50/50 rounded-lg p-3">
                    <h4 class="text-[11px] font-bold text-emerald-800 mb-2 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 bg-emerald-600 rounded-full"></span> 3 Mata Pelajaran Unggulan
                    </h4>
                    <?php if (!empty($mapelStats)): ?>
                        <ul class="text-xs space-y-1.5">
                            <?php foreach (array_slice($mapelStats, 0, 3) as $m): ?>
                                <li class="flex justify-between items-center bg-white px-2 py-1 rounded border border-emerald-100 shadow-sm">
                                    <span class="text-gray-700"><?= $m['nama_mapel'] ?></span>
                                    <span class="font-bold text-emerald-700"><?= number_format($m['avg_nilai'], 1) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-xs text-gray-400 italic">Data belum tersedia.</p>
                    <?php endif; ?>
                </div>

                <div class="border border-rose-200 bg-rose-50/50 rounded-lg p-3">
                    <h4 class="text-[11px] font-bold text-rose-800 mb-2 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 bg-rose-600 rounded-full"></span> 3 Mata Pelajaran Perlu Perbaikan
                    </h4>
                    <?php if (!empty($mapelStats) && count($mapelStats) > 3): ?>
                        <ul class="text-xs space-y-1.5">
                            <?php foreach (array_slice(array_reverse($mapelStats), 0, 3) as $m): ?>
                                <li class="flex justify-between items-center bg-white px-2 py-1 rounded border border-rose-100 shadow-sm">
                                    <span class="text-gray-700"><?= $m['nama_mapel'] ?></span>
                                    <span class="font-bold text-rose-700"><?= number_format($m['avg_nilai'], 1) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-xs text-gray-400 italic">Data belum cukup untuk analisis.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($tryout): ?>
        <div class="mb-6 section-break-avoid">
            <h3 class="font-bold text-gray-800 text-xs mb-2 uppercase border-b border-gray-300 pb-1">
                C. Hasil Tryout UTBK Terakhir <span class="normal-case text-gray-500 font-normal">(<?= getIndoDate($tryout['tanggal_tes']) ?>)</span>
            </h3>
            <table class="w-full text-center border-collapse border border-gray-400 text-xs">
                <thead>
                    <tr class="bg-gray-100 text-gray-900 font-bold text-[10px]">
                        <th class="border border-gray-400 px-1 py-2">PU</th>
                        <th class="border border-gray-400 px-1 py-2">PPU</th>
                        <th class="border border-gray-400 px-1 py-2">PBM</th>
                        <th class="border border-gray-400 px-1 py-2">PK</th>
                        <th class="border border-gray-400 px-1 py-2">Lit.Indo</th>
                        <th class="border border-gray-400 px-1 py-2">Lit.Ing</th>
                        <th class="border border-gray-400 px-1 py-2">PM</th>
                        <th class="border border-gray-400 px-1 py-2 bg-gray-200">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border border-gray-400 px-1 py-2"><?= $tryout['pu'] ?></td>
                        <td class="border border-gray-400 px-1 py-2"><?= $tryout['ppu'] ?></td>
                        <td class="border border-gray-400 px-1 py-2"><?= $tryout['pbm'] ?></td>
                        <td class="border border-gray-400 px-1 py-2"><?= $tryout['pk'] ?></td>
                        <td class="border border-gray-400 px-1 py-2"><?= $tryout['lit_indo'] ?></td>
                        <td class="border border-gray-400 px-1 py-2"><?= $tryout['lit_ing'] ?></td>
                        <td class="border border-gray-400 px-1 py-2"><?= $tryout['pm'] ?></td>
                        <td class="border border-gray-400 px-1 py-2 font-bold bg-gray-50 text-indigo-700"><?= $tryout['skor_total'] ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="section-break-avoid">
            <div class="mt-8 p-3 border border-gray-300 bg-gray-50 rounded text-[10px] text-gray-600 text-justify italic leading-relaxed">
                <strong>CATATAN SISTEM:</strong> 
                Analisis ini dihasilkan secara otomatis berdasarkan data nilai rapor semester 1-5 dan hasil tryout UTBK yang telah diinputkan ke dalam sistem. Laporan ini bertujuan untuk memberikan gambaran obyektif mengenai perkembangan akademik siswa sebagai bahan evaluasi dan pertimbangan dalam menyusun strategi belajar selanjutnya.
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
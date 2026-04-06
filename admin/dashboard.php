<?php
/**
 * Admin - Dashboard Utama
 * Updated: Fix Logika Tampilan Mapel Pilihan (Gabungan Rumpun + Manual)
 */
$pageTitle = 'Dashboard Admin';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
require_once __DIR__ . '/../templates/header-admin.php';

// --- 1. STATISTIK UTAMA ---
$totalSiswa = $db->count('siswa_profile');
$totalPTN = $db->count('ptn');
$totalProdi = $db->count('prodi');
$siswaDianalisis = $db->queryOne("SELECT COUNT(DISTINCT siswa_id) as total FROM rekomendasi")['total'];

// --- 2. CHART: MAPEL PILIHAN TERFAVORIT (QUERY TETAP SAMA) ---
// Note: Ini hanya menghitung yang ada di tabel mapel pilihan manual + rumpun
// Agar akurat, kita hitung dari paket rumpun juga
$mapelFavorit = $db->query("
    SELECT mm.nama_mapel, COUNT(*) as jumlah
    FROM (
        -- 1. Dari Pilihan Manual
        SELECT master_mapel_id FROM siswa_mapel_pilihan
        UNION ALL
        -- 2. Dari Paket Rumpun Siswa
        SELECT pr.master_mapel_id 
        FROM siswa_profile sp
        JOIN paket_rumpun pr ON sp.kode_rumpun = pr.kode_rumpun
        WHERE sp.kode_rumpun IS NOT NULL AND sp.kode_rumpun != ''
    ) as gabungan
    JOIN master_mapel mm ON gabungan.master_mapel_id = mm.id
    WHERE mm.kelompok = 'Pilihan' OR mm.status_fase_f = 'Pilihan'
    GROUP BY mm.id, mm.nama_mapel
    ORDER BY jumlah DESC
    LIMIT 5
");

// --- 3. SISWA TERBARU (LOGIC BARU) ---
// Ambil data siswa dulu
$siswaTerbaru = $db->query("
    SELECT sp.id, u.nama, sp.kelas, sp.asal_sekolah, sp.kode_rumpun, u.created_at
    FROM siswa_profile sp
    JOIN users u ON sp.user_id = u.id
    ORDER BY u.created_at DESC
    LIMIT 5
");

// Siapkan Kamus Rumpun -> Mapel untuk efisiensi
$rumpunRaw = $db->query("
    SELECT pr.kode_rumpun, mm.nama_mapel 
    FROM paket_rumpun pr 
    JOIN master_mapel mm ON pr.master_mapel_id = mm.id
    ORDER BY mm.nama_mapel ASC
");
$rumpunMap = [];
foreach ($rumpunRaw as $r) {
    $rumpunMap[$r['kode_rumpun']][] = $r['nama_mapel'];
}

// Proses Inject Mapel ke setiap siswa
foreach ($siswaTerbaru as &$s) {
    // 1. Mapel dari Rumpun
    $mapels = isset($rumpunMap[$s['kode_rumpun']]) ? $rumpunMap[$s['kode_rumpun']] : [];
    
    // 2. Mapel dari Manual (Query kecil per siswa, oke untuk limit 5)
    $manual = $db->query("
        SELECT mm.nama_mapel 
        FROM siswa_mapel_pilihan smp 
        JOIN master_mapel mm ON smp.master_mapel_id = mm.id 
        WHERE smp.siswa_id = ?
    ", [$s['id']]);
    
    foreach($manual as $m) {
        $mapels[] = $m['nama_mapel'];
    }
    
    // Gabung, Unik, Sort
    $mapels = array_unique($mapels);
    sort($mapels);
    
    // Simpan sebagai string comma-separated
    $s['mapel_display_array'] = $mapels; // Simpan array untuk loop di view
}
unset($s); // Break reference

// --- 4. SISWA PERLU PENDAMPINGAN ---
$siswaBerisiko = $db->query("
    SELECT sp.id, u.nama, sp.kelas, nr.rata_rata, nr.semester
    FROM siswa_profile sp
    JOIN users u ON sp.user_id = u.id
    JOIN nilai_rapor nr ON sp.id = nr.siswa_id
    WHERE nr.rata_rata > 0 AND nr.rata_rata < 75
    ORDER BY nr.semester DESC, nr.rata_rata ASC
    LIMIT 5
");
?>

<div class="space-y-6">
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-transform hover:scale-[1.02]">
            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-bold uppercase">Total Siswa</p>
                <h3 class="text-2xl font-bold text-slate-800 dark:text-white"><?= $totalSiswa ?></h3>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-transform hover:scale-[1.02]">
            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-bold uppercase">Siswa Dianalisis</p>
                <h3 class="text-2xl font-bold text-slate-800 dark:text-white"><?= $siswaDianalisis ?></h3>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-transform hover:scale-[1.02]">
            <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fas fa-university"></i>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-bold uppercase">Data PTN</p>
                <h3 class="text-2xl font-bold text-slate-800 dark:text-white"><?= $totalPTN ?></h3>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-transform hover:scale-[1.02]">
            <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                <i class="fas fa-book-open"></i>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-bold uppercase">Program Studi</p>
                <h3 class="text-2xl font-bold text-slate-800 dark:text-white"><?= $totalProdi ?></h3>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-1 space-y-6">
            
            <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-xl p-6 text-white shadow-lg relative overflow-hidden">
                <div class="absolute right-0 top-0 p-4 opacity-10 transform translate-x-4 -translate-y-4">
                    <i class="fas fa-bolt text-9xl"></i>
                </div>
                <h3 class="text-lg font-bold mb-4 relative z-10">Menu Cepat</h3>
                <div class="flex flex-col gap-3 relative z-10">
                    <a href="siswa.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-lg text-sm font-medium transition flex items-center gap-2">
                        <i class="fas fa-users w-5"></i> Kelola Data Siswa
                    </a>
                    <a href="nilai-rapor.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-lg text-sm font-medium transition flex items-center gap-2">
                        <i class="fas fa-edit w-5"></i> Input Nilai Rapor
                    </a>
                    <a href="laporan.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-lg text-sm font-medium transition flex items-center gap-2">
                        <i class="fas fa-print w-5"></i> Cetak Laporan
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="font-bold text-slate-800 dark:text-white mb-4 text-sm flex items-center gap-2">
                    <i class="fas fa-fire text-rose-500"></i> Mapel Pilihan Terpopuler
                </h3>
                <div class="space-y-4">
                    <?php if(empty($mapelFavorit)): ?>
                        <div class="text-center py-8 text-slate-400">
                            <i class="fas fa-chart-bar text-3xl mb-2 opacity-30"></i>
                            <p class="text-xs">Belum ada data pemilihan mapel.</p>
                        </div>
                    <?php else:
                        $maxVal = $mapelFavorit[0]['jumlah']; 
                        foreach($mapelFavorit as $mf): 
                            $width = ($mf['jumlah'] / $maxVal) * 100;
                    ?>
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-medium text-slate-700 dark:text-slate-300"><?= $mf['nama_mapel'] ?></span>
                                <span class="text-slate-500 font-bold"><?= $mf['jumlah'] ?> Siswa</span>
                            </div>
                            <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2">
                                <div class="bg-indigo-500 h-2 rounded-full transition-all duration-1000" style="width: <?= $width ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        </div>

        <div class="lg:col-span-2 space-y-6">
            
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-700/30">
                    <h3 class="font-bold text-sm text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-clock text-blue-500"></i> Siswa Terbaru
                    </h3>
                    <a href="siswa.php" class="text-xs text-blue-600 hover:text-blue-700 font-medium">Lihat Semua</a>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if(empty($siswaTerbaru)): ?>
                        <div class="p-8 text-center text-xs text-slate-400">Belum ada data siswa.</div>
                    <?php else: ?>
                        <?php foreach($siswaTerbaru as $s): ?>
                            <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-600 flex items-center justify-center text-xs font-bold text-slate-500 dark:text-slate-300">
                                            <?= substr($s['nama'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-800 dark:text-white"><?= sanitize($s['nama']) ?></p>
                                            <p class="text-[10px] text-slate-400">
                                                <?= sanitize($s['asal_sekolah'] ?? 'Sekolah Umum') ?> • <?= $s['kelas'] ?? '-' ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-slate-400 whitespace-nowrap">
                                        <?= timeAgo($s['created_at']) ?>
                                    </span>
                                </div>
                                
                                <div class="pl-11">
                                    <?php if (!empty($s['mapel_display_array'])): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php 
                                            // Tampilkan max 3 mapel
                                            $showMapels = array_slice($s['mapel_display_array'], 0, 3);
                                            foreach($showMapels as $m): 
                                            ?>
                                                <span class="inline-block px-2 py-0.5 text-[10px] font-medium rounded bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-100 dark:border-blue-800">
                                                    <?= trim($m) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            
                                            <?php if(count($s['mapel_display_array']) > 3): ?>
                                                <span class="inline-block px-2 py-0.5 text-[10px] text-slate-400">
                                                    +<?= count($s['mapel_display_array']) - 3 ?> lainnya
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-[10px] text-slate-400 italic">Belum memilih mata pelajaran.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-rose-50 dark:bg-rose-900/20">
                    <h3 class="font-bold text-sm text-rose-700 dark:text-rose-300 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i> Perlu Pendampingan
                    </h3>
                    <span class="text-xs bg-white/50 text-rose-700 px-2 py-0.5 rounded-full border border-rose-200"><?= count($siswaBerisiko) ?> Siswa</span>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if(empty($siswaBerisiko)): ?>
                        <div class="p-6 text-center">
                            <i class="fas fa-check-circle text-emerald-500 text-2xl mb-2"></i>
                            <p class="text-xs text-slate-500">Tidak ada siswa dengan nilai di bawah standar (75).</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($siswaBerisiko as $sb): ?>
                            <div class="p-4 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center text-xs font-bold">!</div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-800 dark:text-white"><?= sanitize($sb['nama']) ?></p>
                                        <p class="text-xs text-slate-500">Kelas <?= $sb['kelas'] ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-bold text-rose-600"><?= number_format($sb['rata_rata'], 2) ?></span>
                                    <p class="text-[10px] text-slate-400">Rata-rata Smt <?= $sb['semester'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if(!empty($siswaBerisiko)): ?>
                    <div class="bg-rose-50/50 dark:bg-rose-900/10 p-2 text-center">
                        <a href="nilai-rapor.php" class="text-xs text-rose-600 hover:text-rose-700 font-medium transition">
                            Lihat Detail & Input Nilai <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
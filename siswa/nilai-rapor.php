<?php
/**
 * Siswa - Lihat Nilai Rapor (Read Only)
 * Updated: Logic Match with Admin (Wajib + Rumpun + Manual)
 */
$pageTitle = 'Nilai Rapor';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
require_once __DIR__ . '/../templates/header-siswa.php';

$siswaId = $_SESSION['siswa_id'];
$semester = get('semester') ?: 1;

// 1. DATA SISWA & RUMPUN
$siswa = $db->queryOne("SELECT * FROM siswa_profile WHERE id = ?", [$siswaId]);
$raporCurrent = $db->queryOne("SELECT id, rata_rata, kode_rumpun FROM nilai_rapor WHERE siswa_id = ? AND semester = ?", [$siswaId, $semester]);
$kodeRumpunAktif = $raporCurrent['kode_rumpun'] ?? $siswa['kode_rumpun'];

// 2. LOGIKA MAPEL (Sama Persis dengan Admin)
$listWajib = [];
$listPeminatan = [];
$existingNilai = [];

// A. Identifikasi ID Mapel Peminatan (Rumpun + Manual)
$rumpunIds = [];
if (!empty($kodeRumpunAktif)) {
    $rumpunRaw = $db->query("SELECT master_mapel_id FROM paket_rumpun WHERE kode_rumpun = ?", [$kodeRumpunAktif]);
    $rumpunIds = array_column($rumpunRaw, 'master_mapel_id');
}
$manualRaw = $db->query("SELECT master_mapel_id FROM siswa_mapel_pilihan WHERE siswa_id = ?", [$siswaId]);
$manualIds = array_column($manualRaw, 'master_mapel_id');
$allPeminatanIds = array_unique(array_merge($rumpunIds, $manualIds));

// B. Ambil Semua Mapel & Filter Sesuai Semester
$allMaster = $db->query("SELECT * FROM master_mapel ORDER BY kelompok DESC, id ASC");

foreach ($allMaster as $m) {
    $nama = $m['nama_mapel'];
    $isSejarah = stripos($nama, 'Sejarah') !== false;
    $isInformatika = stripos($nama, 'Informatika') !== false;
    $isIPA = ($nama == 'IPA' || $nama == 'Ilmu Pengetahuan Alam');
    $isIPS = ($nama == 'IPS' || $nama == 'Ilmu Pengetahuan Sosial');

    // Rule Semester 1-2
    if ($semester <= 2) {
        if ($isSejarah) continue; 
        if ($isInformatika) { $listWajib[] = $m; continue; }
    }

    // Rule Semester 3-6
    if ($semester > 2) {
        if ($isIPA || $isIPS) continue; 
    }

    // Klasifikasi Wajib vs Peminatan
    if (in_array($m['id'], $allPeminatanIds)) {
        // Mapel ini masuk kategori Peminatan bagi siswa ini
        $listPeminatan[] = $m;
    } else if ($m['kelompok'] == 'Wajib' || $m['kelompok'] == 'Muatan Lokal') {
        // Mapel Wajib Umum
        $listWajib[] = $m;
    }
}

// 3. AMBIL NILAI (Jika Ada)
if ($raporCurrent) {
    $details = $db->query("SELECT master_mapel_id, nilai FROM nilai_rapor_detail WHERE nilai_rapor_id = ?", [$raporCurrent['id']]);
    foreach ($details as $d) {
        $existingNilai[$d['master_mapel_id']] = $d['nilai'];
    }
}

// 4. Hitung Total Rata-rata Semua Semester
$avgTotalData = $db->queryOne("SELECT AVG(rata_rata) as total_avg FROM nilai_rapor WHERE siswa_id = ?", [$siswaId]);
$avgTotal = $avgTotalData['total_avg'] ?? 0;
?>

<div class="max-w-6xl mx-auto space-y-6">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shadow-sm">
                    <i class="fas fa-file-alt"></i>
                </div>
                Data Nilai Rapor
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 ml-14">Rekapitulasi nilai akademik per semester.</p>
        </div>
        
        <div class="flex gap-4">
            <div class="text-right">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Rata-rata Sem <?= $semester ?></span>
                <div class="text-2xl font-bold text-slate-800 dark:text-white">
                    <?= $raporCurrent ? number_format($raporCurrent['rata_rata'], 2) : '-' ?>
                </div>
            </div>
            <div class="w-px bg-slate-200 dark:bg-slate-700 h-12"></div>
            <div class="text-right">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Total Rata-rata</span>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                    <?= ($avgTotal > 0) ? number_format($avgTotal, 2) : '-' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
        <?php for($i=1; $i<=6; $i++): 
            $isActive = $semester == $i;
            $btnClass = $isActive 
                ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30 ring-2 ring-blue-600 ring-offset-2 ring-offset-slate-50 dark:ring-offset-slate-900' 
                : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700';
        ?>
            <a href="?semester=<?= $i ?>" onclick="showLoader()" 
               class="px-5 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap <?= $btnClass ?>">
                Semester <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>

    <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-xl p-4 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-3">
             <i class="fas fa-layer-group text-indigo-600 dark:text-indigo-400 mt-0.5 text-lg shrink-0"></i>
             <div class="text-sm text-indigo-800 dark:text-indigo-200">
                 Paket Rumpun Semester Ini: <strong><?= $kodeRumpunAktif ?: '-' ?></strong>
             </div>
        </div>
        <?php if ($semester > 2): ?>
             <span class="text-[10px] bg-white/50 px-2 py-1 rounded text-indigo-600 border border-indigo-200">Fase F</span>
        <?php endif; ?>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 md:p-8 space-y-10">
            
            <div>
                <div class="flex items-center gap-3 mb-6 pb-2 border-b border-slate-100 dark:border-slate-700">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i class="fas fa-book text-sm"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Mata Pelajaran Umum</h3>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach($listWajib as $m): 
                        $nilai = $existingNilai[$m['id']] ?? null;
                    ?>
                        <div class="relative group bg-slate-50 dark:bg-slate-700/30 p-4 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-indigo-200 dark:hover:border-indigo-700 hover:shadow-md transition-all duration-200">
                            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider truncate" title="<?= $m['nama_mapel'] ?>">
                                <?= $m['nama_mapel'] ?>
                            </label>
                            <div class="flex items-end justify-between mt-2">
                                <div class="text-2xl font-black <?= $nilai ? 'text-slate-800 dark:text-white' : 'text-slate-300 dark:text-slate-600 text-lg font-normal italic' ?>">
                                    <?= $nilai ? number_format($nilai, 0) : '-' ?>
                                </div>
                                <?php if($nilai): ?>
                                    <div class="text-[10px] font-bold px-2 py-0.5 rounded bg-white dark:bg-slate-600 text-slate-600 dark:text-slate-300 shadow-sm">
                                        <?= ($nilai >= 90) ? 'A' : (($nilai >= 80) ? 'B' : 'C') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-3 mb-6 pb-2 border-b border-slate-100 dark:border-slate-700">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <i class="fas fa-star text-sm"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Mata Pelajaran Peminatan</h3>
                </div>

                <?php if($semester <= 2): ?>
                    <div class="p-6 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-slate-100 dark:border-slate-600 text-center">
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Semester 1 & 2 (Fase E) belum ada mata pelajaran peminatan khusus.</p>
                    </div>
                <?php else: ?>
                    <?php if(empty($listPeminatan)): ?>
                        <div class="p-6 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-slate-100 dark:border-slate-600 text-center">
                            <p class="text-sm text-slate-500 dark:text-slate-400 font-medium italic">Belum ada mapel peminatan yang diatur.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <?php foreach($listPeminatan as $m): 
                                $nilai = $existingNilai[$m['id']] ?? null;
                            ?>
                                <div class="relative group bg-amber-50/50 dark:bg-amber-900/10 p-4 rounded-xl border border-amber-100 dark:border-amber-800 hover:border-amber-300 dark:hover:border-amber-600 hover:shadow-md transition-all duration-200">
                                    <label class="block text-xs font-bold text-amber-700/70 dark:text-amber-400/70 mb-1 uppercase tracking-wider truncate" title="<?= $m['nama_mapel'] ?>">
                                        <?= $m['nama_mapel'] ?>
                                    </label>
                                    <div class="flex items-end justify-between mt-2">
                                        <div class="text-2xl font-black <?= $nilai ? 'text-slate-800 dark:text-white' : 'text-slate-300 dark:text-slate-600 text-lg font-normal italic' ?>">
                                            <?= $nilai ? number_format($nilai, 0) : '-' ?>
                                        </div>
                                        <?php if($nilai): ?>
                                            <div class="text-[10px] font-bold px-2 py-0.5 rounded bg-white dark:bg-slate-600 text-slate-600 dark:text-slate-300 shadow-sm">
                                                <?= ($nilai >= 90) ? 'A' : (($nilai >= 80) ? 'B' : 'C') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
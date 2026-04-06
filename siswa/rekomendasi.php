<?php
/**
 * Siswa - Lihat Rekomendasi (Final Layout Split)
 * Fitur: Memisahkan tampilan Saintek vs Soshum di Minat & Rekomendasi
 */
$pageTitle = 'Rekomendasi Prodi';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/RecommendationEngineV2.php';

$db = Database::getInstance();
$engine = new RecommendationEngineV2();

require_once __DIR__ . '/../templates/header-siswa.php';

$siswaId = $_SESSION['siswa_id'];

// --- 1. CEK KELENGKAPAN NILAI ---
$raporCheck = $db->queryOne("SELECT AVG(rata_rata) as val FROM nilai_rapor WHERE siswa_id = ? AND semester BETWEEN 1 AND 5", [$siswaId]);
$hasRapor = ($raporCheck['val'] ?? 0) > 0;

$toCheck = $db->queryOne("SELECT skor_total FROM nilai_tryout WHERE siswa_id = ? ORDER BY tanggal_tes DESC LIMIT 1", [$siswaId]);
$hasTO = ($toCheck['skor_total'] ?? 0) > 0;

// --- 2. GENERATE ULANG ---
if (isset($_GET['refresh'])) {
    if (!$hasRapor && !$hasTO) {
        setFlash('message', 'Belum ada data nilai Rapor atau Try Out.', 'error');
    } else {
        $count = $engine->generateForStudent($siswaId);
        setFlash('message', "Analisis selesai! Data diperbarui.", 'success');
    }
    redirect('rekomendasi.php');
}

$jalurFilter = isset($_GET['jalur']) ? $_GET['jalur'] : '';

// --- 3. AMBIL DATA ---
$sql = "
    SELECT r.*, 
           p.nama as prodi_nama, p.rumpun, 
           p.daya_tampung_snbp, p.daya_tampung_snbt,
           p.passing_grade,
           pt.nama as ptn_nama, pt.singkatan, pt.kota
    FROM rekomendasi r
    JOIN prodi p ON r.prodi_id = p.id
    JOIN ptn pt ON p.ptn_id = pt.id
    WHERE r.siswa_id = ?
";
$params = [$siswaId];
if ($jalurFilter) {
    $sql .= " AND r.jalur = ?";
    $params[] = $jalurFilter;
}
$sql .= " ORDER BY r.ranking ASC";

$rekomendasi = $db->query($sql, $params);

// --- 4. FUNGSI PEMISAH KELOMPOK (SAINTEK/SOSHUM) ---
function groupData($allData, $jalur) {
    $filtered = array_filter($allData, fn($r) => $r['jalur'] === $jalur);
    
    $groups = [
        'minat_saintek' => [],
        'minat_soshum' => [],
        'sys_saintek' => [],
        'sys_soshum' => []
    ];

    foreach ($filtered as $item) {
        // Cek Sumber: Minat atau Sistem
        // Kita cek string [Minat] atau [Pilihan Siswa] di alasan
        $isMinat = (stripos($item['alasan'], '[Minat]') !== false || stripos($item['alasan'], '[Pilihan Siswa]') !== false);
        
        // Cek Rumpun: Saintek atau Soshum (Dari Database)
        $isSoshum = (stripos($item['rumpun'], 'Soshum') !== false || stripos($item['rumpun'], 'Sosial') !== false);
        $rumpunKey = $isSoshum ? 'soshum' : 'saintek';

        if ($isMinat) {
            $groups["minat_{$rumpunKey}"][] = $item;
        } else {
            $groups["sys_{$rumpunKey}"][] = $item;
        }
    }
    return $groups;
}

$snbp = groupData($rekomendasi, 'SNBP');
$snbt = groupData($rekomendasi, 'SNBT');

// Helper untuk mengecek apakah array kosong semua
function isEmptyGroup($g) {
    return empty($g['minat_saintek']) && empty($g['minat_soshum']) && empty($g['sys_saintek']) && empty($g['sys_soshum']);
}
?>

<div class="max-w-7xl mx-auto space-y-8 print:hidden">

    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex gap-2">
            <a href="?jalur=" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= !$jalurFilter ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' ?>">Semua</a>
            <a href="?jalur=SNBP" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $jalurFilter === 'SNBP' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' ?>">SNBP</a>
            <a href="?jalur=SNBT" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $jalurFilter === 'SNBT' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' ?>">SNBT</a>
        </div>
        
        <div class="flex gap-2">
            <a href="cetak-rekomendasi.php" target="_blank" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium shadow-md transition-colors flex items-center gap-2">
                <i class="fas fa-print"></i> Cetak PDF
            </a>
            <a href="?refresh=1" onclick="return confirm('Analisis ulang?')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-sm font-medium shadow-md transition-colors flex items-center gap-2">
                <i class="fas fa-sync-alt"></i> Analisis Ulang
            </a>
        </div>
    </div>

    <?php if (!$hasRapor): ?>
        <div class="bg-rose-50 text-rose-700 px-4 py-3 rounded-xl border border-rose-200">
            <i class="fas fa-exclamation-circle mr-2"></i> Data Rapor belum lengkap untuk analisis SNBP.
        </div>
    <?php endif; ?>
    <?php if (!$hasTO): ?>
        <div class="bg-amber-50 text-amber-700 px-4 py-3 rounded-xl border border-amber-200">
            <i class="fas fa-exclamation-triangle mr-2"></i> Data Try Out belum ada untuk analisis SNBT.
        </div>
    <?php endif; ?>

    <?php if (empty($rekomendasi)): ?>
        <div class="text-center py-16 bg-white rounded-xl border border-slate-200">
            <i class="fas fa-robot text-4xl text-slate-300 mb-2"></i>
            <p class="text-slate-500">Belum ada hasil analisis. Klik tombol <b>Analisis Ulang</b>.</p>
        </div>
    <?php else: ?>

        <?php if ((!$jalurFilter || $jalurFilter === 'SNBP') && !isEmptyGroup($snbp)): ?>
            <div class="space-y-4">
                <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <span class="w-1.5 h-6 bg-emerald-500 rounded-full"></span> Hasil Analisis SNBP (Rapor)
                </h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-emerald-50/50 dark:bg-emerald-900/10 rounded-xl border border-emerald-100 dark:border-emerald-800 p-5">
                        <h3 class="font-bold text-emerald-800 mb-4 flex items-center gap-2 text-sm uppercase tracking-wide">
                            <i class="fas fa-heart"></i> Pilihan Minat Kamu
                        </h3>

                        <?php if($snbp['minat_saintek']): ?>
                            <div class="mb-4">
                                <h4 class="text-xs font-bold text-emerald-600 uppercase mb-2 border-b border-emerald-200 pb-1">Kelompok Saintek</h4>
                                <div class="space-y-3">
                                    <?php foreach ($snbp['minat_saintek'] as $rekom) include 'partials/card-rekomendasi-simple.php'; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if($snbp['minat_soshum']): ?>
                            <div class="mb-2">
                                <h4 class="text-xs font-bold text-emerald-600 uppercase mb-2 border-b border-emerald-200 pb-1">Kelompok Soshum</h4>
                                <div class="space-y-3">
                                    <?php foreach ($snbp['minat_soshum'] as $rekom) include 'partials/card-rekomendasi-simple.php'; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if(empty($snbp['minat_saintek']) && empty($snbp['minat_soshum'])): ?>
                            <div class="text-center py-6 text-emerald-600/50 italic text-sm border-2 border-dashed border-emerald-100 rounded-lg">
                                Minatmu belum masuk kriteria aman.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                        <h3 class="font-bold text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2 text-sm uppercase tracking-wide">
                            <i class="fas fa-lightbulb text-amber-500"></i> Rekomendasi Sistem
                        </h3>

                        <?php if($snbp['sys_saintek']): ?>
                            <div class="mb-4">
                                <h4 class="text-xs font-bold text-slate-500 uppercase mb-2 border-b border-slate-200 pb-1">Alternatif Saintek</h4>
                                <div class="space-y-3">
                                    <?php foreach ($snbp['sys_saintek'] as $rekom) include 'partials/card-rekomendasi-simple.php'; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if($snbp['sys_soshum']): ?>
                            <div class="mb-2">
                                <h4 class="text-xs font-bold text-slate-500 uppercase mb-2 border-b border-slate-200 pb-1">Alternatif Soshum</h4>
                                <div class="space-y-3">
                                    <?php foreach ($snbp['sys_soshum'] as $rekom) include 'partials/card-rekomendasi-simple.php'; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(empty($snbp['sys_saintek']) && empty($snbp['sys_soshum'])): ?>
                            <p class="text-sm text-slate-500 italic">Belum ada rekomendasi tambahan.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ((!$jalurFilter || $jalurFilter === 'SNBT') && !isEmptyGroup($snbt)): ?>
            <div class="space-y-4 pt-8 border-t border-slate-200 dark:border-slate-700">
                <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <span class="w-1.5 h-6 bg-indigo-500 rounded-full"></span> Hasil Analisis SNBT (Try Out)
                </h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-indigo-50/50 dark:bg-indigo-900/10 rounded-xl border border-indigo-100 dark:border-indigo-800 p-5">
                        <h3 class="font-bold text-indigo-800 mb-4 flex items-center gap-2 text-sm uppercase tracking-wide">
                            <i class="fas fa-heart"></i> Pilihan Minat Kamu
                        </h3>

                        <?php if($snbt['minat_saintek']): ?>
                            <div class="mb-4">
                                <h4 class="text-xs font-bold text-indigo-600 uppercase mb-2 border-b border-indigo-200 pb-1">Kelompok Saintek</h4>
                                <div class="space-y-3">
                                    <?php foreach ($snbt['minat_saintek'] as $rekom) include 'partials/card-rekomendasi-simple.php'; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if($snbt['minat_soshum']): ?>
                            <div class="mb-2">
                                <h4 class="text-xs font-bold text-indigo-600 uppercase mb-2 border-b border-indigo-200 pb-1">Kelompok Soshum</h4>
                                <div class="space-y-3">
                                    <?php foreach ($snbt['minat_soshum'] as $rekom) include 'partials/card-rekomendasi-simple.php'; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if(empty($snbt['minat_saintek']) && empty($snbt['minat_soshum'])): ?>
                            <div class="text-center py-6 text-indigo-600/50 italic text-sm border-2 border-dashed border-indigo-100 rounded-lg">
                                Minatmu belum masuk kriteria aman.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                        <h3 class="font-bold text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2 text-sm uppercase tracking-wide">
                            <i class="fas fa-lightbulb text-amber-500"></i> Rekomendasi Sistem
                        </h3>

                        <?php if($snbt['sys_saintek']): ?>
                            <div class="mb-4">
                                <h4 class="text-xs font-bold text-slate-500 uppercase mb-2 border-b border-slate-200 pb-1">Alternatif Saintek</h4>
                                <div class="space-y-3">
                                    <?php foreach ($snbt['sys_saintek'] as $rekom) include 'partials/card-rekomendasi-simple.php'; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if($snbt['sys_soshum']): ?>
                            <div class="mb-2">
                                <h4 class="text-xs font-bold text-slate-500 uppercase mb-2 border-b border-slate-200 pb-1">Alternatif Soshum</h4>
                                <div class="space-y-3">
                                    <?php foreach ($snbt['sys_soshum'] as $rekom) include 'partials/card-rekomendasi-simple.php'; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if(empty($snbt['sys_saintek']) && empty($snbt['sys_soshum'])): ?>
                            <p class="text-sm text-slate-500 italic">Belum ada rekomendasi tambahan.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
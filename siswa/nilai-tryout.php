<?php
/**
 * Siswa - Input Nilai Try Out (SNBT)
 * Updated: Logic Verified, 7 Subtes, Auto-Calculation
 */
$pageTitle = 'Nilai Try Out';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
require_once __DIR__ . '/../templates/header-siswa.php';

$siswaId = $_SESSION['siswa_id'];

// Handle Form Submission
if (isPost()) {
    $tanggal = post('tanggal_tes');
    $catatan = post('catatan');
    $jenis = 'SNBT'; 

    // Ambil nilai 7 subtes (Default 0 jika kosong)
    $pu = floatval(post('pu') ?: 0);
    $ppu = floatval(post('ppu') ?: 0);
    $pbm = floatval(post('pbm') ?: 0);
    $pk = floatval(post('pk') ?: 0);
    $lit_indo = floatval(post('lit_indo') ?: 0);
    $lit_ing = floatval(post('lit_ing') ?: 0);
    $pm = floatval(post('pm') ?: 0);

    // Hitung Skor Total (Rata-rata 7 Subtes)
    // SNBT biasanya rata-rata dari semua subtes
    $skorTotal = ($pu + $ppu + $pbm + $pk + $lit_indo + $lit_ing + $pm) / 7;

    try {
        // Insert Data
        $db->execute(
            "INSERT INTO nilai_tryout (siswa_id, tryout_ke, jenis, tanggal_tes, pu, ppu, pbm, pk, lit_indo, lit_ing, pm, skor_total, catatan)
             VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$siswaId, $jenis, $tanggal, $pu, $ppu, $pbm, $pk, $lit_indo, $lit_ing, $pm, $skorTotal, $catatan]
        );

        // Update Rekomendasi (Otomatis generate ulang)
        require_once __DIR__ . '/../includes/RecommendationEngineV2.php';
        $engine = new RecommendationEngineV2();
        $engine->generateForStudent($siswaId);

        setFlash('message', 'Nilai Try Out berhasil disimpan!', 'success');
        redirect('nilai-tryout.php');

    } catch (Exception $e) {
        setFlash('message', 'Gagal menyimpan: ' . $e->getMessage(), 'error');
    }
}

// Get History
$tryoutList = $db->query(
    "SELECT * FROM nilai_tryout WHERE siswa_id = ? ORDER BY tanggal_tes DESC",
    [$siswaId]
);

// Helper function untuk format angka (biar tabel rapi)
if (!function_exists('formatNumber')) {
    function formatNumber($num) {
        return ($num == 0) ? '-' : number_format($num, 0);
    }
}

$inputClass = "block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm py-2 px-3 transition-shadow";
$labelClass = "block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider";
?>

<div class="max-w-5xl mx-auto space-y-8">

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
            <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-edit text-blue-500"></i> Input Nilai Try Out (SNBT)
            </h3>
        </div>
        
        <div class="p-6">
            <form method="POST">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tanggal Tes</label>
                        <input type="date" name="tanggal_tes" class="<?= $inputClass ?>" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Catatan / Nama TO</label>
                        <input type="text" name="catatan" class="<?= $inputClass ?>" placeholder="Contoh: TO 1 Ruangguru">
                    </div>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-700 my-6"></div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 pb-2 border-b border-blue-100 dark:border-blue-900 mb-3">
                            <i class="fas fa-brain text-blue-500"></i>
                            <h4 class="text-sm font-bold text-blue-700 dark:text-blue-300">Tes Potensi Skolastik (TPS)</h4>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="<?= $labelClass ?>">Penalaran Umum</label>
                                <input type="number" step="0.01" name="pu" class="<?= $inputClass ?>" placeholder="0-1000" required>
                            </div>
                            <div>
                                <label class="<?= $labelClass ?>">Pengetahuan (PPU)</label>
                                <input type="number" step="0.01" name="ppu" class="<?= $inputClass ?>" placeholder="0-1000" required>
                            </div>
                            <div>
                                <label class="<?= $labelClass ?>">Bacaan (PBM)</label>
                                <input type="number" step="0.01" name="pbm" class="<?= $inputClass ?>" placeholder="0-1000" required>
                            </div>
                            <div>
                                <label class="<?= $labelClass ?>">Kuantitatif (PK)</label>
                                <input type="number" step="0.01" name="pk" class="<?= $inputClass ?>" placeholder="0-1000" required>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center gap-2 pb-2 border-b border-emerald-100 dark:border-emerald-900 mb-3">
                            <i class="fas fa-book-reader text-emerald-500"></i>
                            <h4 class="text-sm font-bold text-emerald-700 dark:text-emerald-300">Literasi & Penalaran</h4>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="<?= $labelClass ?>">Literasi Bahasa Indonesia</label>
                                <input type="number" step="0.01" name="lit_indo" class="<?= $inputClass ?>" placeholder="0-1000" required>
                            </div>
                            <div>
                                <label class="<?= $labelClass ?>">Literasi Inggris</label>
                                <input type="number" step="0.01" name="lit_ing" class="<?= $inputClass ?>" placeholder="0-1000" required>
                            </div>
                            <div>
                                <label class="<?= $labelClass ?>">Penalaran Mat (PM)</label>
                                <input type="number" step="0.01" name="pm" class="<?= $inputClass ?>" placeholder="0-1000" required>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="mt-8 pt-4 border-t border-slate-100 dark:border-slate-700 flex justify-end">
                    <button type="submit" onclick="showLoader()" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-lg shadow-blue-500/30 transition-all flex items-center gap-2">
                        <i class="fas fa-save"></i> Simpan Nilai
                    </button>
                </div>

            </form>
        </div>
    </div>

    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl p-5 flex gap-4 items-start text-sm text-blue-800 dark:text-blue-300 shadow-sm">
        <i class="fas fa-info-circle mt-0.5 text-lg shrink-0"></i>
        <div>
            <strong class="block mb-1">Informasi Penilaian SNBT</strong>
            <ul class="list-disc pl-4 space-y-1 opacity-90">
                <li>Skor SNBT menggunakan skala <strong>0 - 1000</strong> (IRT Scoring).</li>
                <li>Terdiri dari 7 Subtes utama yang terbagi menjadi TPS, Literasi Bahasa, dan Penalaran Matematika.</li>
                <li>Sistem akan otomatis menghitung rata-rata skor total Anda.</li>
            </ul>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
            <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-history text-slate-500"></i> Riwayat Try Out
            </h3>
        </div>

        <div class="overflow-x-auto">
            <?php if (empty($tryoutList)): ?>
                <div class="p-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-700 mb-4">
                        <i class="fas fa-clipboard-list text-2xl text-slate-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-slate-900 dark:text-white">Belum Ada Data</h3>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Input nilai Try Out pertamamu di atas untuk melihat progres.</p>
                </div>
            <?php else: ?>
                <table class="w-full text-left text-xs md:text-sm border-collapse">
                    <thead class="bg-slate-100 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 font-bold uppercase">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-2 py-3 text-center">PU</th>
                            <th class="px-2 py-3 text-center">PPU</th>
                            <th class="px-2 py-3 text-center">PBM</th>
                            <th class="px-2 py-3 text-center">PK</th>
                            <th class="px-2 py-3 text-center">Indo</th>
                            <th class="px-2 py-3 text-center">Ing</th>
                            <th class="px-2 py-3 text-center">PM</th>
                            <th class="px-4 py-3 text-center bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300">Total</th>
                            <th class="px-4 py-3 text-left">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-slate-700 dark:text-slate-300">
                        <?php foreach ($tryoutList as $to): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap font-medium">
                                    <?= date('d M Y', strtotime($to['tanggal_tes'])) ?>
                                </td>
                                <td class="px-2 py-3 text-center"><?= formatNumber($to['pu']) ?></td>
                                <td class="px-2 py-3 text-center"><?= formatNumber($to['ppu']) ?></td>
                                <td class="px-2 py-3 text-center"><?= formatNumber($to['pbm']) ?></td>
                                <td class="px-2 py-3 text-center"><?= formatNumber($to['pk']) ?></td>
                                <td class="px-2 py-3 text-center"><?= formatNumber($to['lit_indo']) ?></td>
                                <td class="px-2 py-3 text-center"><?= formatNumber($to['lit_ing']) ?></td>
                                <td class="px-2 py-3 text-center"><?= formatNumber($to['pm']) ?></td>
                                <td class="px-4 py-3 text-center font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-900/10">
                                    <?= number_format($to['skor_total'], 2) ?>
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs truncate max-w-[150px]">
                                    <?= sanitize($to['catatan'] ?: '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
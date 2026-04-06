<?php

/**
 * Siswa - Lihat Nilai TKA (Read Only)
 * Updated: View Only Mode, Info Panel, Modern UI
 */
$pageTitle = 'Nilai TKA';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
require_once __DIR__ . '/../templates/header-siswa.php';

$siswaId = $_SESSION['siswa_id'];

// Ambil Data Nilai TKA Siswa Ini
$dataTKA = $db->queryOne("SELECT * FROM nilai_tka WHERE siswa_id = ?", [$siswaId]);

?>

<div class="max-w-5xl mx-auto space-y-6">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-graduation-cap text-indigo-500"></i> Hasil Nilai TKA
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Tes Kompetensi Akademik (Pengganti UN)
            </p>
        </div>

        <?php if ($dataTKA): ?>
            <div class="flex items-center gap-3">
                <div class="px-4 py-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-center border border-indigo-100 dark:border-indigo-800">
                    <span class="block text-xs text-indigo-600 dark:text-indigo-400 uppercase font-bold tracking-wider">Rata-rata TKA</span>
                    <span class="text-2xl font-bold text-indigo-700 dark:text-indigo-300"><?= number_format($dataTKA['rata_rata_tka'], 2) ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white dark:bg-slate-800 border-l-4 border-blue-500 shadow-sm rounded-r-lg p-4 flex items-start gap-3 border-y border-r border-slate-200 dark:border-slate-700">
        <i class="fas fa-info-circle text-xl text-blue-500 mt-0.5 shrink-0"></i>
        <div class="text-sm text-slate-600 dark:text-slate-300">
            <strong class="text-slate-800 dark:text-white block mb-0.5">INFORMASI</strong>
            Nilai Tes Kompetensi Akademik diinput dan dikelola oleh Admin Sekolah.
        </div>
    </div>

    <?php if (!$dataTKA): ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-700 mb-4">
                <i class="fas fa-clipboard-list text-2xl text-slate-400"></i>
            </div>
            <h3 class="text-lg font-medium text-slate-900 dark:text-white">Nilai Belum Tersedia</h3>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Nilai TKA Anda belum diinput oleh Admin Sekolah.</p>
        </div>
    <?php else: ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-book text-slate-500"></i> Mapel Wajib TKA
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-700/30 rounded-lg">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Matematika</span>
                        <span class="text-lg font-bold text-slate-800 dark:text-white"><?= formatNumber($dataTKA['nilai_mtk']) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-700/30 rounded-lg">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Bahasa Indonesia</span>
                        <span class="text-lg font-bold text-slate-800 dark:text-white"><?= formatNumber($dataTKA['nilai_indo']) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-700/30 rounded-lg">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Bahasa Inggris</span>
                        <span class="text-lg font-bold text-slate-800 dark:text-white"><?= formatNumber($dataTKA['nilai_inggris']) ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-star text-amber-500"></i> Mapel Pilihan TKA
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="p-3 bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-800/30 rounded-lg">
                        <div class="text-xs text-amber-600 dark:text-amber-400 uppercase font-bold mb-1">Pilihan 1</div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                <?= $dataTKA['mapel_pilihan_1'] ?: '<span class="italic text-slate-400">Tidak ada</span>' ?>
                            </span>
                            <span class="text-lg font-bold text-slate-800 dark:text-white"><?= formatNumber($dataTKA['nilai_pilihan_1']) ?></span>
                        </div>
                    </div>

                    <div class="p-3 bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-800/30 rounded-lg">
                        <div class="text-xs text-amber-600 dark:text-amber-400 uppercase font-bold mb-1">Pilihan 2</div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                <?= $dataTKA['mapel_pilihan_2'] ?: '<span class="italic text-slate-400">Tidak ada</span>' ?>
                            </span>
                            <span class="text-lg font-bold text-slate-800 dark:text-white"><?= formatNumber($dataTKA['nilai_pilihan_2']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
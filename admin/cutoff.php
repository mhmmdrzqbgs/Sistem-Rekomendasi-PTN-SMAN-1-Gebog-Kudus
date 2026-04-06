<?php
/**
 * Admin - Data Acuan (Single Passing Grade)
 * Updated: Fix Column Not Found (level_ptn) & Table Structure
 */
$pageTitle = 'Data Acuan SNBP/SNBT';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

// Handle Reset Action (Set Passing Grade ke 0)
if (isset($_GET['action']) && $_GET['action'] === 'reset' && isset($_GET['id'])) {
    $prodiId = $_GET['id'];
    $updated = $db->execute("UPDATE prodi SET passing_grade = 0, daya_tampung_snbp = 0, daya_tampung_snbt = 0 WHERE id = ?", [$prodiId]);
    if ($updated) {
        setFlash('message', 'Data acuan berhasil direset (Nol).', 'success');
    } else {
        setFlash('message', 'Gagal reset data.', 'error');
    }
    redirect('cutoff.php');
}

require_once __DIR__ . '/../templates/header-admin.php';

// Filter Logic
$ptnFilter = $_GET['ptn'] ?? '';
$ptnList = $db->query("SELECT * FROM ptn ORDER BY nama");

$where = $ptnFilter ? "WHERE p.ptn_id = ?" : "";
$params = $ptnFilter ? [$ptnFilter] : [];

// Query Data (FIXED: Direct from PRODI table, removed level_ptn)
$cutoffData = $db->query("
    SELECT p.id as prodi_id, p.nama as prodi_nama, p.rumpun,
           p.passing_grade as pg_snbt, 
           p.daya_tampung_snbp, 
           p.daya_tampung_snbt,
           pt.nama as ptn_nama, pt.singkatan
    FROM prodi p
    JOIN ptn pt ON p.ptn_id = pt.id
    $where
    ORDER BY pt.nama, p.nama
", $params);
?>

<div class="space-y-6">

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-slate-500 dark:text-slate-400 mr-2">Filter PTN:</span>
            
            <a href="cutoff.php" 
               class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors <?= !$ptnFilter ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600' ?>">
               Semua
            </a>

            <?php foreach ($ptnList as $ptn): ?>
                <a href="?ptn=<?= $ptn['id'] ?>"
                   class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors <?= $ptnFilter == $ptn['id'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600' ?>">
                    <?= $ptn['singkatan'] ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-emerald-100 dark:border-emerald-900/50 shadow-sm p-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-emerald-50 dark:bg-emerald-900/20 rounded-bl-full -mr-2 -mt-2"></div>
            <h4 class="text-base font-bold text-slate-800 dark:text-white mb-2 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Daya Tampung SNBP
            </h4>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Jumlah kursi yang tersedia untuk jalur prestasi (Rapor).
            </p>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-indigo-100 dark:border-indigo-900/50 shadow-sm p-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-indigo-50 dark:bg-indigo-900/20 rounded-bl-full -mr-2 -mt-2"></div>
            <h4 class="text-base font-bold text-slate-800 dark:text-white mb-2 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span> Passing Grade SNBT
            </h4>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Acuan: <strong>Skor UTBK</strong> (Skala 0-1000). Target aman untuk lolos.
            </p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
            <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-database text-blue-500"></i>
                Data Acuan Prodi <span class="text-xs bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-full ml-2"><?= count($cutoffData) ?></span>
            </h3>
            <a href="import-data.php" class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 transition-colors">
                <i class="fas fa-upload mr-1"></i> Update Data (Excel)
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-100 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase text-slate-500 dark:text-slate-400 font-bold">
                        <th class="px-6 py-4">Program Studi</th>
                        <th class="px-6 py-4">PTN</th>
                        <th class="px-6 py-4 text-center">DT SNBP</th>
                        <th class="px-6 py-4 text-center">DT SNBT</th>
                        <th class="px-6 py-4 text-center">Pass. Grade</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if (empty($cutoffData)): ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">Belum ada data prodi.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($cutoffData as $c): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
                            
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-900 dark:text-white"><?= sanitize($c['prodi_nama']) ?></div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium mt-1
                                    <?= $c['rumpun'] === 'Saintek' ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' ?>">
                                    <?= $c['rumpun'] ?>
                                </span>
                            </td>
                            
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                    <?= $c['singkatan'] ?>
                                </span>
                            </td>
                            
                            <td class="px-6 py-4 text-center font-mono text-sm text-slate-600 dark:text-slate-300">
                                <?= $c['daya_tampung_snbp'] > 0 ? $c['daya_tampung_snbp'] : '-' ?>
                            </td>

                            <td class="px-6 py-4 text-center font-mono text-sm text-slate-600 dark:text-slate-300">
                                <?= $c['daya_tampung_snbt'] > 0 ? $c['daya_tampung_snbt'] : '-' ?>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <?php if ($c['pg_snbt'] > 0): ?>
                                    <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/10 px-3 py-1 rounded-md border border-indigo-100 dark:border-indigo-800">
                                        <?= formatNumber($c['pg_snbt'], 0) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <?php if($c['pg_snbt'] > 0 || $c['daya_tampung_snbp'] > 0): ?>
                                    <a href="?action=reset&id=<?= $c['prodi_id'] ?>" 
                                       onclick="return confirm('Yakin ingin mereset data nilai untuk prodi ini?')"
                                       class="text-slate-400 hover:text-red-500 transition-colors p-2 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg" 
                                       title="Reset Data Nilai">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
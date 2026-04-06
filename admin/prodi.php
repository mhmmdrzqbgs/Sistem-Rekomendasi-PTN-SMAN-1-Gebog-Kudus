<?php
/**
 * Admin - Lihat Daftar Prodi & PTN
 * Updated: Support New Columns (Jenjang, Passing Grade, Daya Tampung SNBP/SNBT)
 */
$pageTitle = 'Daftar Prodi & PTN';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

require_once __DIR__ . '/../templates/header-admin.php';

// Get PTN list (Untuk Kartu Atas)
$ptnList = $db->query("SELECT * FROM ptn ORDER BY nama");

// Get Prodi list (Untuk Tabel Bawah)
$ptnFilter = isset($_GET['ptn']) ? $_GET['ptn'] : '';
$rumpunFilter = isset($_GET['rumpun']) ? $_GET['rumpun'] : '';

$where = [];
$params = [];

if ($ptnFilter) {
    $where[] = "p.ptn_id = ?";
    $params[] = $ptnFilter;
}
if ($rumpunFilter) {
    $where[] = "p.rumpun = ?";
    $params[] = $rumpunFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Query JOIN Updated
$prodiList = $db->query("
    SELECT p.*, 
           pt.nama as ptn_nama, 
           pt.singkatan, 
           pt.kota
    FROM prodi p
    JOIN ptn pt ON p.ptn_id = pt.id
    $whereClause
    ORDER BY pt.nama, p.nama
", $params);
?>

<div class="space-y-6">

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
            <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-university text-blue-500"></i>
                Perguruan Tinggi Negeri <span class="text-xs bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-full ml-2"><?= count($ptnList) ?></span>
            </h3>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <?php foreach ($ptnList as $ptn): ?>
                    <?php 
                        $isActive = $ptnFilter == $ptn['id'];
                        $cardClass = $isActive 
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 ring-2 ring-blue-200 dark:ring-blue-800' 
                            : 'border-slate-200 dark:border-slate-600 hover:border-blue-300 dark:hover:border-blue-500 hover:shadow-md bg-white dark:bg-slate-700';
                    ?>
                    <a href="?ptn=<?= $ptn['id'] ?>" class="block p-4 rounded-xl border transition-all duration-200 text-center group <?= $cardClass ?>">
                        <div class="font-bold text-slate-800 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 mb-1 truncate" title="<?= $ptn['nama'] ?>">
                            <?= $ptn['singkatan'] ?: $ptn['nama'] ?>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                            <?= $ptn['kota'] ?? '-' ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-4 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-slate-500 dark:text-slate-400 mr-2">Filter:</span>
        
        <a href="prodi.php" class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors <?= !$ptnFilter && !$rumpunFilter ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300' ?>">
            Semua
        </a>
        
        <a href="?rumpun=Saintek" class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors <?= $rumpunFilter === 'Saintek' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300' ?>">
            Saintek
        </a>
        
        <a href="?rumpun=Soshum" class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors <?= $rumpunFilter === 'Soshum' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300' ?>">
            Soshum
        </a>

        <?php if ($ptnFilter): ?>
            <div class="h-6 w-px bg-slate-300 dark:bg-slate-600 mx-2"></div>
            <a href="prodi.php" class="px-3 py-1.5 text-sm font-medium rounded-lg bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 transition-colors flex items-center gap-1">
                <i class="fas fa-times"></i> Reset PTN
            </a>
        <?php endif; ?>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
            <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-graduation-cap text-emerald-500"></i>
                Daftar Program Studi <span class="text-xs bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-full ml-2"><?= count($prodiList) ?></span>
            </h3>
            <a href="kelola-prodi.php" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                <i class="fas fa-plus"></i> <span class="hidden sm:inline">Tambah Prodi</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase text-slate-500 dark:text-slate-400 font-semibold">
                        <th class="px-6 py-4">Program Studi</th>
                        <th class="px-6 py-4">PTN</th>
                        <th class="px-6 py-4 text-center">Rumpun</th>
                        <th class="px-6 py-4 text-center">Pass. Grade</th>
                        <th class="px-6 py-4 text-center">Daya Tampung</th>
                        <th class="px-6 py-4 text-center">Bobot Mapel</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if (empty($prodiList)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <i class="fas fa-search text-3xl mb-3 opacity-30"></i>
                                <p>Tidak ada data program studi yang ditemukan.</p>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($prodiList as $p): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900 dark:text-white"><?= sanitize($p['nama']) ?></div>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 dark:bg-slate-600 dark:text-slate-300 mt-1">
                                    <?= $p['jenjang'] ?? 'S1' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-700 dark:text-slate-300">
                                    <?= $p['singkatan'] ?: $p['ptn_nama'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $p['rumpun'] === 'Saintek' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' ?>">
                                    <?= $p['rumpun'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold text-slate-600 dark:text-slate-300">
                                <?= $p['passing_grade'] > 0 ? number_format($p['passing_grade']) : '-' ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex flex-col gap-1 text-xs">
                                    <div class="flex justify-between w-24 mx-auto">
                                        <span class="text-slate-400">SNBP:</span>
                                        <span class="font-bold text-slate-700 dark:text-white"><?= $p['daya_tampung_snbp'] ?></span>
                                    </div>
                                    <div class="flex justify-between w-24 mx-auto border-t border-slate-100 dark:border-slate-700 pt-1">
                                        <span class="text-slate-400">SNBT:</span>
                                        <span class="font-bold text-slate-700 dark:text-white"><?= $p['daya_tampung_snbt'] ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php
                                    $bobotCount = $db->count('bobot_mapel', 'prodi_id = ?', [$p['id']]);
                                    $btnClass = $bobotCount > 0 
                                        ? 'text-blue-600 bg-blue-50 hover:bg-blue-100 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800' 
                                        : 'text-slate-500 bg-white hover:bg-slate-50 border-slate-200 dark:bg-transparent dark:text-slate-400 dark:border-slate-600';
                                    $icon = $bobotCount > 0 ? 'fa-check-circle' : 'fa-cog';
                                ?>
                                <a href="kelola-bobot.php?prodi_id=<?= $p['id'] ?>" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors <?= $btnClass ?>"
                                   title="Konfigurasi Bobot Mapel">
                                    <i class="fas <?= $icon ?>"></i>
                                    <?= $bobotCount > 0 ? "$bobotCount Mapel" : "Atur" ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="kelola-prodi.php?action=edit&id=<?= $p['id'] ?>" 
                                       class="p-2 text-amber-500 hover:bg-amber-50 rounded-lg transition-colors dark:hover:bg-amber-900/20" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="kelola-prodi.php?action=delete&id=<?= $p['id'] ?>" 
                                       class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors dark:hover:bg-red-900/20" 
                                       onclick="return confirm('Hapus prodi ini beserta datanya?')" 
                                       title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
<?php
/**
 * Admin - Kelola Siswa
 * Updated: Filter Siswa Aktif Only & Natural Sort Class
 */
$pageTitle = 'Kelola Siswa';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

// ==========================================
// 1. LOGIC ACTION (HAPUS SISWA)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $siswaId = $_GET['id'];
    $siswa = $db->queryOne("SELECT user_id FROM siswa_profile WHERE id = ?", [$siswaId]);

    if ($siswa) {
        $userId = $siswa['user_id'];
        $deleted = $db->execute("DELETE FROM users WHERE id = ?", [$userId]);
        if ($deleted) {
            setFlash('message', 'Data siswa berhasil dihapus.', 'success');
        } else {
            setFlash('message', 'Gagal menghapus data siswa.', 'error');
        }
    } else {
        setFlash('message', 'Siswa tidak ditemukan.', 'error');
    }
    
    $redirectUrl = 'siswa.php';
    if(isset($_GET['kelas'])) $redirectUrl .= '?kelas=' . urlencode($_GET['kelas']);
    redirect($redirectUrl);
}

require_once __DIR__ . '/../templates/header-admin.php';

// --- A. AMBIL DATA PENDUKUNG (PRE-FETCH) ---

// 1. Daftar Kelas (NATURAL SORT FIX & FILTER AKTIF)
// Hanya ambil kelas yang punya siswa AKTIF (bukan alumni)
$listKelas = $db->query("
    SELECT DISTINCT kelas 
    FROM siswa_profile 
    WHERE kelas IS NOT NULL AND kelas != '' AND (status = 'Aktif' OR status IS NULL)
    ORDER BY LENGTH(kelas) ASC, kelas ASC
");

// 2. KAMUS RUMPUN
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

// --- B. FILTER LOGIC ---
$search = get('search');
$activeKelas = get('kelas'); 

// Filter Dasar: Hanya Siswa Aktif
$where = ["(sp.status = 'Aktif' OR sp.status IS NULL)"];
$params = [];

if ($search) {
    $where[] = "(u.nama LIKE ? OR sp.asal_sekolah LIKE ? OR sp.nisn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($activeKelas) {
    $where[] = "sp.kelas = ?";
    $params[] = $activeKelas;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// --- C. QUERY UTAMA ---
$query = "
    SELECT sp.*, u.nama, u.username,
           (SELECT COUNT(*) FROM nilai_rapor WHERE siswa_id = sp.id) as total_rapor,
           (SELECT COUNT(*) FROM nilai_tryout WHERE siswa_id = sp.id) as total_tryout,
           (SELECT COUNT(*) FROM nilai_tka WHERE siswa_id = sp.id) as total_tka,
           (
               SELECT GROUP_CONCAT(DISTINCT mm.nama_mapel SEPARATOR ', ')
               FROM siswa_mapel_pilihan smp
               JOIN master_mapel mm ON smp.master_mapel_id = mm.id
               WHERE smp.siswa_id = sp.id
           ) as mapel_manual
    FROM siswa_profile sp
    JOIN users u ON sp.user_id = u.id
    $whereClause
    ORDER BY LENGTH(sp.kelas) ASC, sp.kelas ASC, u.nama ASC
";

$siswaList = $db->query($query, $params);
?>

<div class="space-y-6">

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-4">
        <form method="GET" class="flex flex-col md:flex-row gap-4 items-center justify-between">
            <?php if($activeKelas): ?>
                <input type="hidden" name="kelas" value="<?= htmlspecialchars($activeKelas) ?>">
            <?php endif; ?>

            <div class="relative w-full md:flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-slate-400"></i>
                </div>
                <input type="text" name="search" value="<?= sanitize($search) ?>"
                    class="pl-10 block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm transition-shadow"
                    placeholder="Cari siswa berdasarkan Nama atau NISN...">
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <button type="submit" onclick="showLoader()" class="flex-1 md:flex-none px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center justify-center gap-2">
                    <i class="fas fa-search"></i> Cari
                </button>
                <?php if ($search || $activeKelas): ?>
                    <a href="siswa.php" onclick="showLoader()" class="flex-1 md:flex-none px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-sync"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="border-b border-slate-200 dark:border-slate-700">
        <nav class="-mb-px flex space-x-2 overflow-x-auto pb-1 scrollbar-hide" aria-label="Tabs">
            <a href="siswa.php" onclick="showLoader()"
               class="<?= !$activeKelas ? 'border-blue-500 text-blue-600 bg-blue-50 dark:bg-blue-900/20' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?> 
                      whitespace-nowrap py-2 px-4 border-b-2 font-medium text-sm rounded-t-lg transition-colors flex-shrink-0">
               Semua Siswa
            </a>

            <?php foreach ($listKelas as $k): 
                $isActive = ($activeKelas === $k['kelas']);
                $tabClass = $isActive 
                    ? 'border-blue-500 text-blue-600 bg-blue-50 dark:bg-blue-900/20' 
                    : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300';
            ?>
                <a href="?kelas=<?= urlencode($k['kelas']) ?>" onclick="showLoader()"
                   class="<?= $tabClass ?> whitespace-nowrap py-2 px-4 border-b-2 font-medium text-sm rounded-t-lg transition-colors flex-shrink-0">
                   <?= $k['kelas'] ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-700/30">
            <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-user-graduate text-emerald-500"></i>
                Data Siswa 
                <?php if($activeKelas): ?>
                    <span class="text-sm font-normal text-slate-500">- Kelas <?= $activeKelas ?></span>
                <?php endif; ?>
                <span class="ml-2 px-2 py-0.5 rounded-full bg-slate-200 dark:bg-slate-600 text-xs text-slate-600 dark:text-slate-300">
                    <?= count($siswaList) ?>
                </span>
            </h3>
        </div>

        <div class="overflow-x-auto">
            <?php if (empty($siswaList)): ?>
                <div class="p-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-700 mb-4">
                        <i class="fas fa-inbox text-2xl text-slate-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-slate-900 dark:text-white">Data kosong</h3>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">
                        <?php if($activeKelas): ?>
                            Tidak ada siswa aktif di kelas <strong><?= htmlspecialchars($activeKelas) ?></strong>.
                        <?php else: ?>
                            Belum ada data siswa aktif yang terdaftar.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase text-slate-500 dark:text-slate-400 font-semibold">
                            <th class="px-6 py-4 w-12 text-center">No</th>
                            <th class="px-6 py-4">Nama Siswa</th>
                            <th class="px-6 py-4 text-center">Kelas</th>
                            <th class="px-6 py-4 w-1/3">Mapel Pilihan (Fase F)</th>
                            <th class="px-6 py-4 text-center">Status Data</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <?php $no = 1; foreach ($siswaList as $s): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
                                
                                <td class="px-6 py-4 text-center text-xs text-slate-400"><?= $no++ ?></td>
                                
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900 dark:text-white"><?= sanitize($s['nama']) ?></div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        NISN: <?= sanitize($s['username']) ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <span class="inline-block px-2.5 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        <?= $s['kelas'] ?? '-' ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <?php 
                                    $paketMapels = isset($rumpunMap[$s['kode_rumpun']]) ? $rumpunMap[$s['kode_rumpun']] : [];
                                    $manualMapels = !empty($s['mapel_manual']) ? explode(', ', $s['mapel_manual']) : [];
                                    $allMapels = array_unique(array_merge($paketMapels, $manualMapels));
                                    sort($allMapels);
                                    ?>

                                    <?php if (!empty($allMapels)): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php 
                                            $maxShow = 4;
                                            $showMapels = array_slice($allMapels, 0, $maxShow);
                                            foreach($showMapels as $m): ?>
                                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800 whitespace-nowrap">
                                                    <?= trim($m) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if(count($allMapels) > $maxShow): ?>
                                                <span class="inline-block px-2 py-0.5 rounded text-[10px] bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400 whitespace-nowrap" title="<?= implode(', ', array_slice($allMapels, $maxShow)) ?>">
                                                    +<?= count($allMapels) - $maxShow ?> lainnya
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400 italic">- Belum ada -</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <?php $raporOk = $s['total_rapor'] >= 5; ?>
                                        <div class="group/icon relative">
                                            <span class="w-8 h-8 flex items-center justify-center rounded-lg text-xs border transition-colors <?= $raporOk ? 'bg-emerald-50 border-emerald-200 text-emerald-600' : 'bg-slate-50 border-slate-200 text-slate-300' ?>">
                                                <i class="fas fa-book"></i>
                                            </span>
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover/icon:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                                                Rapor: <?= $s['total_rapor'] ?>/5 Smt
                                            </div>
                                        </div>
                                        
                                        <?php $tkaOk = $s['total_tka'] > 0; ?>
                                        <div class="group/icon relative">
                                            <span class="w-8 h-8 flex items-center justify-center rounded-lg text-xs border transition-colors <?= $tkaOk ? 'bg-blue-50 border-blue-200 text-blue-600' : 'bg-slate-50 border-slate-200 text-slate-300' ?>">
                                                <i class="fas fa-graduation-cap"></i>
                                            </span>
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover/icon:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                                                Nilai TKA: <?= $tkaOk ? 'Ada' : 'Kosong' ?>
                                            </div>
                                        </div>

                                        <?php $toOk = $s['total_tryout'] > 0; ?>
                                        <div class="group/icon relative">
                                            <span class="w-8 h-8 flex items-center justify-center rounded-lg text-xs border transition-colors <?= $toOk ? 'bg-amber-50 border-amber-200 text-amber-600' : 'bg-slate-50 border-slate-200 text-slate-300' ?>">
                                                <i class="fas fa-pencil-alt"></i>
                                            </span>
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover/icon:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                                                Tryout: <?= $s['total_tryout'] ?>x
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="detail-siswa.php?id=<?= $s['id'] ?>" onclick="showLoader()"
                                           class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-600 hover:bg-blue-100 hover:text-blue-600 transition-colors"
                                           title="Detail Siswa">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>

                                        <a href="nilai-rapor.php?siswa=<?= $s['id'] ?>" onclick="showLoader()"
                                           class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-600 hover:bg-emerald-100 hover:text-emerald-600 transition-colors"
                                           title="Input Nilai">
                                            <i class="fas fa-edit text-xs"></i>
                                        </a>

                                        <a href="?action=delete&id=<?= $s['id'] ?>&kelas=<?= urlencode($activeKelas) ?>"
                                           onclick="return confirm('Hapus siswa <?= $s['nama'] ?>? \n⚠️ Semua data nilai akan ikut terhapus permanen!')"
                                           class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-600 hover:bg-rose-100 hover:text-rose-600 transition-colors"
                                           title="Hapus Data">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </a>
                                    </div>
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
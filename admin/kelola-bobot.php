<?php
/**
 * Admin - Kelola Bobot Mapel per Prodi
 * Updated: Tailwind CSS & Dark Mode Support
 */
$pageTitle = 'Kelola Bobot Mapel';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

$prodiId = $_GET['prodi_id'] ?? null;

if (!$prodiId) {
    header('Location: prodi.php');
    exit;
}

// Ambil data prodi
$prodi = $db->queryOne("SELECT p.*, pt.nama as ptn_nama FROM prodi p JOIN ptn pt ON p.ptn_id = pt.id WHERE p.id = ?", [$prodiId]);

// 1. Handle Tambah Bobot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_bobot'])) {
    $mapelId = $_POST['master_mapel_id']; // ID dari tabel master_mapel
    $bobot = $_POST['bobot'];

    // Cek duplikasi
    $cek = $db->queryOne("SELECT id FROM bobot_mapel WHERE prodi_id = ? AND master_mapel_id = ?", [$prodiId, $mapelId]);
    
    if (!$cek) {
        $db->execute("INSERT INTO bobot_mapel (prodi_id, master_mapel_id, bobot) VALUES (?, ?, ?)", [$prodiId, $mapelId, $bobot]);
        setFlash('message', 'Bobot berhasil ditambahkan', 'success');
    } else {
        setFlash('message', 'Mata pelajaran ini sudah ada bobotnya', 'warning');
    }
    // Refresh page to prevent resubmission
    header("Location: kelola-bobot.php?prodi_id=" . $prodiId);
    exit;
}

// 2. Handle Hapus Bobot
if (isset($_GET['hapus_id'])) {
    $db->execute("DELETE FROM bobot_mapel WHERE id = ?", [$_GET['hapus_id']]);
    setFlash('message', 'Bobot berhasil dihapus', 'success');
    header("Location: kelola-bobot.php?prodi_id=" . $prodiId);
    exit;
}

// Ambil daftar bobot yang sudah ada
$listBobot = $db->query("
    SELECT b.*, m.nama_mapel 
    FROM bobot_mapel b 
    JOIN master_mapel m ON b.master_mapel_id = m.id 
    WHERE b.prodi_id = ?", 
    [$prodiId]
);

// Ambil master mapel untuk dropdown
$masterMapel = $db->query("SELECT * FROM master_mapel ORDER BY nama_mapel");

require_once __DIR__ . '/../templates/header-admin.php';
?>

<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($prodi['nama']) ?></h2>
            <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars($prodi['ptn_nama']) ?></p>
        </div>
        <a href="prodi.php" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden sticky top-6">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
                    <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-plus-circle text-emerald-500"></i> Tambah Bobot
                    </h3>
                </div>
                <div class="p-6">
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Mata Pelajaran</label>
                            <select name="master_mapel_id" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm" required>
                                <option value="">-- Pilih Mapel --</option>
                                <?php foreach($masterMapel as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= $m['nama_mapel'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nilai Bobot (x)</label>
                            <input type="number" step="0.1" name="bobot" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm" 
                                   value="1.5" required placeholder="Contoh: 1.5">
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Bobot > 1.0 berarti mapel ini lebih diprioritaskan.</p>
                        </div>

                        <button type="submit" name="tambah_bobot" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors shadow-sm flex items-center justify-center gap-2">
                            <i class="fas fa-plus"></i> Tambah
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
                    <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-list-ul text-blue-500"></i> Daftar Bobot Mapel
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase text-slate-500 dark:text-slate-400 font-semibold">
                                <th class="px-6 py-4">Mata Pelajaran</th>
                                <th class="px-6 py-4 text-center">Bobot</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <?php if(empty($listBobot)): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                        <i class="fas fa-balance-scale text-3xl mb-3 opacity-30"></i>
                                        <p>Belum ada konfigurasi bobot khusus.</p>
                                        <p class="text-xs mt-1">Semua mata pelajaran dianggap memiliki bobot standar (1.0x)</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($listBobot as $b): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($b['nama_mapel']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300">
                                                <?= $b['bobot'] ?>x
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <a href="?prodi_id=<?= $prodiId ?>&hapus_id=<?= $b['id'] ?>" 
                                               class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors dark:hover:bg-red-900/20" 
                                               onclick="return confirm('Hapus bobot ini?')" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
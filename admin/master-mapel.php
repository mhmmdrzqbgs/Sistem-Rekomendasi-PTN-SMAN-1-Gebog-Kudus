<?php
/**
 * Admin - Master Mapel (RESPONSIVE LAYOUT FIXED)
 * Fitur: Pengaturan Status per Fase (Semester 1-2 vs 3-6)
 * Updated: Responsive Grid & Better Input Visibility
 */
$pageTitle = 'Master Mata Pelajaran';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
$action = get('action');
$id = get('id');

// --- HANDLE DELETE ---
if ($action === 'delete' && $id) {
    $cekNilai = $db->queryOne("SELECT COUNT(*) as cnt FROM nilai_rapor_detail WHERE master_mapel_id = ?", [$id]);
    $cekBobot = $db->queryOne("SELECT COUNT(*) as cnt FROM bobot_mapel WHERE master_mapel_id = ?", [$id]);

    if ($cekNilai['cnt'] > 0 || $cekBobot['cnt'] > 0) {
        $db->execute("UPDATE master_mapel SET is_active = 0 WHERE id = ?", [$id]);
        setFlash('message', 'Mapel dinonaktifkan (Data aman karena sudah berelasi).', 'warning');
    } else {
        $db->execute("DELETE FROM master_mapel WHERE id = ?", [$id]);
        setFlash('message', 'Mapel dihapus permanen.', 'success');
    }
    redirect('master-mapel.php');
}

// --- HANDLE SAVE ---
if (isPost()) {
    $nama = post('nama_mapel');
    $faseE = post('status_fase_e');
    $faseF = post('status_fase_f');
    $kelompokUtama = ($faseF == 'Pilihan') ? 'Pilihan' : 'Wajib';
    $isActive = post('is_active') ? 1 : 0;
    $editId = post('edit_id');

    if ($editId) {
        $db->execute(
            "UPDATE master_mapel SET nama_mapel=?, kelompok=?, status_fase_e=?, status_fase_f=?, is_active=? WHERE id=?", 
            [$nama, $kelompokUtama, $faseE, $faseF, $isActive, $editId]
        );
        setFlash('message', 'Pengaturan mapel diperbarui.', 'success');
    } else {
        $db->execute(
            "INSERT INTO master_mapel (nama_mapel, kelompok, status_fase_e, status_fase_f, is_active) VALUES (?, ?, ?, ?, ?)", 
            [$nama, $kelompokUtama, $faseE, $faseF, $isActive]
        );
        setFlash('message', 'Mapel baru ditambahkan.', 'success');
    }
    redirect('master-mapel.php');
}

$editData = null;
if ($action === 'edit' && $id) {
    $editData = $db->queryOne("SELECT * FROM master_mapel WHERE id = ?", [$id]);
}

require_once __DIR__ . '/../templates/header-admin.php';
$mapelList = $db->query("SELECT * FROM master_mapel ORDER BY kelompok, nama_mapel");
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    
    <div class="lg:col-span-4 xl:col-span-3">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm sticky top-24">
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-<?= $editData ? 'edit' : 'plus-circle' ?> text-blue-600"></i> 
                    <?= $editData ? 'Edit Mapel' : 'Tambah Mapel' ?>
                </h3>
            </div>
            
            <div class="p-5">
                <form method="POST">
                    <?php if ($editData): ?>
                        <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                    <?php endif; ?>

                    <div class="space-y-5">
                        
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-slate-300">Nama Mata Pelajaran</label>
                            <input type="text" name="nama_mapel" required value="<?= sanitize($editData['nama_mapel'] ?? '') ?>" 
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder-slate-400 shadow-sm"
                                placeholder="Contoh: Matematika Lanjut">
                        </div>

                        <div class="p-4 bg-slate-50 dark:bg-slate-700/30 rounded-xl border border-slate-200 dark:border-slate-600 space-y-4">
                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200 dark:border-slate-600 pb-2 mb-2">Konfigurasi Kurikulum</h4>
                            
                            <div>
                                <label class="block text-xs font-bold text-blue-600 dark:text-blue-400 mb-1">Fase E (Kelas 10)</label>
                                <div class="relative">
                                    <select name="status_fase_e" class="w-full pl-3 pr-8 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none shadow-sm cursor-pointer">
                                        <option value="Wajib" <?= ($editData['status_fase_e'] ?? 'Wajib') == 'Wajib' ? 'selected' : '' ?>>Wajib (Umum)</option>
                                        <option value="Pilihan" <?= ($editData['status_fase_e'] ?? '') == 'Pilihan' ? 'selected' : '' ?>>Pilihan / Peminatan</option>
                                        <option value="Tidak Ada" <?= ($editData['status_fase_e'] ?? '') == 'Tidak Ada' ? 'selected' : '' ?>>Tidak Ada</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                        <i class="fas fa-chevron-down text-xs text-slate-400"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-indigo-600 dark:text-indigo-400 mb-1">Fase F (Kelas 11-12)</label>
                                <div class="relative">
                                    <select name="status_fase_f" class="w-full pl-3 pr-8 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none shadow-sm cursor-pointer">
                                        <option value="Wajib" <?= ($editData['status_fase_f'] ?? 'Wajib') == 'Wajib' ? 'selected' : '' ?>>Wajib (Umum)</option>
                                        <option value="Pilihan" <?= ($editData['status_fase_f'] ?? '') == 'Pilihan' ? 'selected' : '' ?>>Pilihan / Peminatan</option>
                                        <option value="Tidak Ada" <?= ($editData['status_fase_f'] ?? '') == 'Tidak Ada' ? 'selected' : '' ?>>Tidak Ada</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                        <i class="fas fa-chevron-down text-xs text-slate-400"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800">
                            <div class="relative flex items-center">
                                <input type="checkbox" name="is_active" id="is_active" value="1" <?= ($editData['is_active'] ?? 1) ? 'checked' : '' ?> 
                                    class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-300 transition-all checked:border-blue-500 checked:bg-blue-500 focus:ring-2 focus:ring-blue-200">
                                <i class="fas fa-check text-white text-[10px] absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 opacity-0 peer-checked:opacity-100 pointer-events-none"></i>
                            </div>
                            <label for="is_active" class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer select-none">Status Aktif</label>
                        </div>

                        <div class="pt-2 flex gap-2">
                            <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 font-bold text-sm flex justify-center items-center gap-2">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                            <?php if ($editData): ?>
                                <a href="master-mapel.php" class="px-4 py-2.5 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 font-bold text-sm transition">Batal</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-8 xl:col-span-9">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
                <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-list-ul text-slate-500"></i> Daftar Mata Pelajaran
                </h3>
                <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2.5 py-0.5 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                    Total: <?= count($mapelList) ?>
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-6 py-4 font-bold">Mata Pelajaran</th>
                            <th class="px-4 py-4 text-center font-bold">Fase E (Kls 10)</th>
                            <th class="px-4 py-4 text-center font-bold">Fase F (Kls 11-12)</th>
                            <th class="px-4 py-4 text-center font-bold w-32">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                        <?php if(empty($mapelList)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-500 italic">Belum ada data mata pelajaran.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mapelList as $m): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition group">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-900 dark:text-white"><?= sanitize($m['nama_mapel']) ?></div>
                                        <?php if(!$m['is_active']): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] text-red-600 font-bold bg-red-50 px-2 py-0.5 rounded border border-red-100 mt-1">
                                                <i class="fas fa-ban text-[9px]"></i> Non-Aktif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="px-4 py-4 text-center align-middle">
                                        <?php
                                            $clsE = match($m['status_fase_e']) {
                                                'Wajib' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                'Pilihan' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                default => 'bg-slate-100 text-slate-400 border-slate-200 opacity-60'
                                            };
                                        ?>
                                        <span class="px-2.5 py-1 rounded-md text-xs font-semibold border <?= $clsE ?>">
                                            <?= $m['status_fase_e'] ?>
                                        </span>
                                    </td>
                                    
                                    <td class="px-4 py-4 text-center align-middle">
                                        <?php
                                            $clsF = match($m['status_fase_f']) {
                                                'Wajib' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                'Pilihan' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                default => 'bg-slate-100 text-slate-400 border-slate-200 opacity-60'
                                            };
                                        ?>
                                        <span class="px-2.5 py-1 rounded-md text-xs font-semibold border <?= $clsF ?>">
                                            <?= $m['status_fase_f'] ?>
                                        </span>
                                    </td>
                                    
                                    <td class="px-4 py-4 text-center">
                                        <div class="flex justify-center gap-2 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                            <a href="?action=edit&id=<?= $m['id'] ?>" class="w-8 h-8 flex items-center justify-center rounded-full bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition shadow-sm" title="Edit">
                                                <i class="fas fa-pencil-alt text-xs"></i>
                                            </a>
                                            <a href="?action=delete&id=<?= $m['id'] ?>" onclick="return confirm('Hapus mapel ini?')" class="w-8 h-8 flex items-center justify-center rounded-full bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition shadow-sm" title="Hapus">
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </a>
                                        </div>
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

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
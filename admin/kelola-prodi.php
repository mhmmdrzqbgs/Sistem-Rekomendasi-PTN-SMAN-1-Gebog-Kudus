<?php
/**
 * Admin - Kelola Data Prodi (Create/Edit)
 * Updated: Add Jenjang, Passing Grade, Daya Tampung SNBP/SNBT
 */
$pageTitle = 'Form Kelola Prodi';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

$action = get('action');
$id = get('id');

// 1. HANDLE DELETE
if ($action === 'delete' && $id) {
    // Hapus data terkait dulu agar bersih
    $db->execute("DELETE FROM bobot_mapel WHERE prodi_id = ?", [$id]);
    $db->execute("DELETE FROM rekomendasi WHERE prodi_id = ?", [$id]);
    $db->execute("DELETE FROM prodi WHERE id = ?", [$id]);

    setFlash('message', 'Prodi berhasil dihapus', 'success');
    redirect('prodi.php');
}

// 2. HANDLE SUBMIT FORM
if (isPost()) {
    $ptn_id = post('ptn_id');
    $nama = post('nama');
    $jenjang = post('jenjang'); // New
    $fakultas = post('fakultas');
    $rumpun = post('rumpun');
    
    // New Columns
    $pg = post('passing_grade') ?: 0;
    $dtSnbp = post('daya_tampung_snbp') ?: 0;
    $dtSnbt = post('daya_tampung_snbt') ?: 0;
    
    // Old Column (Optional / Total)
    $daya_tampung = $dtSnbp + $dtSnbt; 

    $akreditasi = post('akreditasi');
    $prospek_kerja = post('prospek_kerja');
    $editId = post('edit_id');

    if (empty($nama) || empty($ptn_id)) {
        setFlash('message', 'Nama Prodi dan PTN wajib diisi', 'error');
    } else {
        $db->beginTransaction();
        try {
            if ($editId) {
                // UPDATE
                $db->execute(
                    "UPDATE prodi SET ptn_id=?, nama=?, jenjang=?, fakultas=?, rumpun=?, passing_grade=?, daya_tampung_snbp=?, daya_tampung_snbt=?, akreditasi=?, prospek_kerja=? WHERE id=?",
                    [$ptn_id, $nama, $jenjang, $fakultas, $rumpun, $pg, $dtSnbp, $dtSnbt, $akreditasi, $prospek_kerja, $editId]
                );
            } else {
                // INSERT
                $db->execute(
                    "INSERT INTO prodi (ptn_id, nama, jenjang, fakultas, rumpun, passing_grade, daya_tampung_snbp, daya_tampung_snbt, akreditasi, prospek_kerja) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$ptn_id, $nama, $jenjang, $fakultas, $rumpun, $pg, $dtSnbp, $dtSnbt, $akreditasi, $prospek_kerja]
                );
            }

            $db->commit();
            setFlash('message', 'Data Prodi berhasil disimpan', 'success');
            redirect('prodi.php');
        } catch (Exception $e) {
            $db->rollback();
            setFlash('message', 'Gagal menyimpan: ' . $e->getMessage(), 'error');
        }
    }
}

// 3. AMBIL DATA EDIT
$editData = null;
if ($action === 'edit' && $id) {
    $editData = $db->queryOne("SELECT * FROM prodi WHERE id = ?", [$id]);
}

$ptnList = $db->query("SELECT id, nama, singkatan FROM ptn ORDER BY nama ASC");

require_once __DIR__ . '/../templates/header-admin.php';
?>

<div class="max-w-5xl mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
            <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-<?= $editData ? 'edit' : 'plus-circle' ?> text-<?= $editData ? 'amber-500' : 'emerald-500' ?>"></i>
                <?= $editData ? 'Edit Program Studi' : 'Tambah Program Studi Baru' ?>
            </h3>
        </div>
        
        <div class="p-6">
            <form method="POST">
                <?php if ($editData): ?>
                    <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    <div class="space-y-4">
                        <div class="pb-2 border-b border-slate-200 dark:border-slate-700 mb-4">
                            <h4 class="text-sm font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wide">Identitas Prodi</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Pilih PTN <span class="text-red-500">*</span></label>
                            <select name="ptn_id" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 sm:text-sm" required>
                                <option value="">-- Pilih PTN --</option>
                                <?php foreach ($ptnList as $ptn): ?>
                                    <option value="<?= $ptn['id'] ?>" <?= ($editData['ptn_id'] ?? '') == $ptn['id'] ? 'selected' : '' ?>>
                                        <?= $ptn['nama'] ?> (<?= $ptn['singkatan'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nama Prodi <span class="text-red-500">*</span></label>
                                <input type="text" name="nama" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 sm:text-sm" 
                                       required value="<?= sanitize($editData['nama'] ?? '') ?>" placeholder="Teknik Informatika">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Jenjang</label>
                                <select name="jenjang" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 sm:text-sm">
                                    <?php foreach(['S1', 'D4', 'D3'] as $j): ?>
                                        <option value="<?= $j ?>" <?= ($editData['jenjang'] ?? 'S1') == $j ? 'selected' : '' ?>><?= $j ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fakultas</label>
                                <input type="text" name="fakultas" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 sm:text-sm" 
                                       value="<?= sanitize($editData['fakultas'] ?? '') ?>" placeholder="F. Teknik">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Rumpun</label>
                                <select name="rumpun" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 sm:text-sm">
                                    <option value="Saintek" <?= ($editData['rumpun'] ?? '') == 'Saintek' ? 'selected' : '' ?>>Saintek</option>
                                    <option value="Soshum" <?= ($editData['rumpun'] ?? '') == 'Soshum' ? 'selected' : '' ?>>Soshum</option>
                                </select>
                            </div>
                        </div>
                        
                         <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Akreditasi</label>
                             <input type="text" name="akreditasi" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 sm:text-sm" 
                                       value="<?= sanitize($editData['akreditasi'] ?? '') ?>" placeholder="Unggul / A / B">
                        </div>

                    </div>

                    <div class="space-y-6">
                        <div class="space-y-4">
                            <div class="pb-2 border-b border-slate-200 dark:border-slate-700 mb-4">
                                <h4 class="text-sm font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">Data Statistik</h4>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Passing Grade (Skor UTBK)</label>
                                <div class="relative">
                                    <input type="number" step="0.01" name="passing_grade" class="block w-full pl-3 pr-12 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 sm:text-sm font-mono" 
                                           value="<?= $editData['passing_grade'] ?? '' ?>" placeholder="0.00">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-slate-400 text-xs">Poin</span>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-500 mt-1">Estimasi nilai aman untuk lolos.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Daya Tampung SNBP</label>
                                    <input type="number" name="daya_tampung_snbp" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 sm:text-sm" 
                                           value="<?= $editData['daya_tampung_snbp'] ?? 0 ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Daya Tampung SNBT</label>
                                    <input type="number" name="daya_tampung_snbt" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 sm:text-sm" 
                                           value="<?= $editData['daya_tampung_snbt'] ?? 0 ?>">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="pb-2 border-b border-slate-200 dark:border-slate-700 mb-4">
                                <h4 class="text-sm font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wide">Info Tambahan</h4>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Prospek Kerja</label>
                                <textarea name="prospek_kerja" rows="4" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500 sm:text-sm" 
                                          placeholder="Contoh: Software Engineer, Data Analyst..."><?= sanitize($editData['prospek_kerja'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 pt-4 border-t border-slate-200 dark:border-slate-700 flex gap-3 justify-end">
                    <a href="prodi.php" class="px-6 py-2.5 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-medium rounded-lg transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors shadow-lg shadow-blue-500/30">
                        <i class="fas fa-save mr-2"></i> <?= $editData ? 'Update Data' : 'Simpan Data' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
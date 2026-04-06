<?php
/**
 * Admin - Kelola PTN (RESPONSIVE GRID LAYOUT)
 * Fitur: CRUD PTN dengan Layout Sticky Form
 * Updated: Grid System 12 Columns & Compact Form
 */
$pageTitle = 'Kelola PTN';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

// Handle actions
$action = get('action');
$id = get('id');

// Delete PTN
if ($action === 'delete' && $id) {
    $prodiCount = $db->count('prodi', 'ptn_id = ?', [$id]);
    if ($prodiCount > 0) {
        setFlash('message', 'Tidak bisa menghapus PTN yang masih memiliki program studi', 'error');
    } else {
        $db->execute("DELETE FROM ptn WHERE id = ?", [$id]);
        setFlash('message', 'PTN berhasil dihapus', 'success');
    }
    redirect('kelola-ptn.php');
}

// Handle form submission
if (isPost()) {
    $nama = post('nama');
    $singkatan = post('singkatan');
    
    // Optional Fields (Default '-')
    $kota = post('kota') ?: '-';
    $provinsi = post('provinsi') ?: '-';
    $alamat = post('alamat') ?: '-';
    $website = post('website') ?: '-';
    $jenis = post('jenis') ?: 'Negeri'; 
    
    $editId = post('edit_id');

    if (empty($nama) || empty($singkatan)) {
        setFlash('message', 'Nama dan singkatan wajib diisi', 'error');
    } else {
        if ($editId) {
            // UPDATE
            $db->execute(
                "UPDATE ptn SET nama = ?, singkatan = ?, kota = ?, provinsi = ?, alamat = ?, website = ?, jenis = ? WHERE id = ?",
                [$nama, $singkatan, $kota, $provinsi, $alamat, $website, $jenis, $editId]
            );
            setFlash('message', 'PTN berhasil diperbarui', 'success');
        } else {
            // INSERT
            $db->execute(
                "INSERT INTO ptn (nama, singkatan, kota, provinsi, alamat, website, jenis) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$nama, $singkatan, $kota, $provinsi, $alamat, $website, $jenis]
            );
            setFlash('message', 'PTN berhasil ditambahkan', 'success');
        }
        redirect('kelola-ptn.php');
    }
}

// Get edit data
$editData = null;
if ($action === 'edit' && $id) {
    $editData = $db->queryOne("SELECT * FROM ptn WHERE id = ?", [$id]);
}

require_once __DIR__ . '/../templates/header-admin.php';

// Get PTN list
$ptnList = $db->query("
    SELECT p.*, (SELECT COUNT(*) FROM prodi WHERE ptn_id = p.id) as total_prodi
    FROM ptn p
    ORDER BY p.nama ASC
");

$inputClass = "w-full rounded-lg border-slate-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all placeholder-slate-400";
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    
    <div class="lg:col-span-4 xl:col-span-3">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm sticky top-24">
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-<?= $editData ? 'edit' : 'plus-circle' ?> text-blue-600"></i>
                    <?= $editData ? 'Edit PTN' : 'Tambah PTN' ?>
                </h3>
            </div>
            
            <div class="p-5">
                <form method="POST">
                    <?php if ($editData): ?>
                        <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                    <?php endif; ?>

                    <div class="space-y-4">
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nama PTN <span class="text-red-500">*</span></label>
                            <input type="text" name="nama" class="<?= $inputClass ?>" required value="<?= sanitize($editData['nama'] ?? '') ?>" placeholder="Universitas Diponegoro">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Singkatan <span class="text-red-500">*</span></label>
                                <input type="text" name="singkatan" class="<?= $inputClass ?>" required value="<?= sanitize($editData['singkatan'] ?? '') ?>" placeholder="UNDIP">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Jenis</label>
                                <select name="jenis" class="<?= $inputClass ?>">
                                    <option value="Negeri" <?= ($editData['jenis'] ?? '') == 'Negeri' ? 'selected' : '' ?>>Negeri</option>
                                    <option value="Swasta" <?= ($editData['jenis'] ?? '') == 'Swasta' ? 'selected' : '' ?>>Swasta</option>
                                    <option value="Kedinasan" <?= ($editData['jenis'] ?? '') == 'Kedinasan' ? 'selected' : '' ?>>Kedinasan</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Kota</label>
                                <input type="text" name="kota" class="<?= $inputClass ?>" value="<?= sanitize($editData['kota'] ?? '') ?>" placeholder="Semarang">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Provinsi</label>
                                <input type="text" name="provinsi" class="<?= $inputClass ?>" value="<?= sanitize($editData['provinsi'] ?? '') ?>" placeholder="Jawa Tengah">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Alamat Lengkap</label>
                            <textarea name="alamat" rows="2" class="<?= $inputClass ?>" placeholder="Jl. Prof. Sudarto..."><?= sanitize($editData['alamat'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Website</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400"><i class="fas fa-globe text-xs"></i></span>
                                <input type="url" name="website" class="<?= $inputClass ?> pl-8" value="<?= sanitize($editData['website'] ?? '') ?>" placeholder="https://undip.ac.id">
                            </div>
                        </div>

                        <div class="pt-2 flex gap-2">
                            <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition shadow-lg shadow-blue-500/30 font-bold text-sm flex justify-center items-center gap-2">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                            <?php if ($editData): ?>
                                <a href="kelola-ptn.php" class="px-4 py-2.5 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 font-bold text-sm transition">Batal</a>
                            <?php endif; ?>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-8 xl:col-span-9">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-700/30">
                <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-university text-slate-500"></i>
                    Daftar PTN 
                </h3>
                <span class="text-xs font-bold bg-blue-100 text-blue-700 px-2.5 py-0.5 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                    <?= count($ptnList) ?> Kampus
                </span>
            </div>

            <div class="overflow-x-auto">
                <?php if (empty($ptnList)): ?>
                    <div class="p-12 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-700 mb-4">
                            <i class="fas fa-university text-2xl text-slate-400"></i>
                        </div>
                        <h3 class="text-lg font-medium text-slate-900 dark:text-white">Belum ada data PTN</h3>
                        <p class="text-slate-500 dark:text-slate-400 mt-1">Tambahkan data melalui form di sebelah kiri.</p>
                    </div>
                <?php else: ?>
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase text-slate-500 dark:text-slate-400 font-bold">
                            <tr>
                                <th class="px-6 py-4">Perguruan Tinggi</th>
                                <th class="px-6 py-4">Lokasi</th>
                                <th class="px-6 py-4 text-center">Prodi Terdaftar</th>
                                <th class="px-6 py-4 text-center w-32">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                            <?php foreach ($ptnList as $ptn): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition group">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-900 dark:text-white"><?= sanitize($ptn['nama']) ?></div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800">
                                                <?= $ptn['singkatan'] ?>
                                            </span>
                                            <span class="text-xs text-slate-400 font-medium tracking-wide uppercase"><?= $ptn['jenis'] ?></span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <div class="text-slate-700 dark:text-slate-300 font-medium">
                                            <?= sanitize($ptn['kota']) != '-' ? sanitize($ptn['kota']) : '<span class="text-slate-400">-</span>' ?>
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            <?= sanitize($ptn['provinsi']) != '-' ? sanitize($ptn['provinsi']) : '' ?>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-center">
                                        <a href="prodi.php?ptn=<?= $ptn['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition border border-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800">
                                            <?= $ptn['total_prodi'] ?> Prodi <i class="fas fa-arrow-right text-[10px]"></i>
                                        </a>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center gap-2 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                            <?php if ($ptn['website'] && $ptn['website'] != '-'): ?>
                                                <a href="<?= $ptn['website'] ?>" target="_blank" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-blue-500 hover:text-white transition" title="Website">
                                                    <i class="fas fa-globe text-xs"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="?action=edit&id=<?= $ptn['id'] ?>" class="w-8 h-8 flex items-center justify-center rounded-full bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition" title="Edit">
                                                <i class="fas fa-pencil-alt text-xs"></i>
                                            </a>

                                            <?php if ($ptn['total_prodi'] == 0): ?>
                                                <a href="?action=delete&id=<?= $ptn['id'] ?>" onclick="return confirm('Hapus PTN ini?')" class="w-8 h-8 flex items-center justify-center rounded-full bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition" title="Hapus">
                                                    <i class="fas fa-trash-alt text-xs"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-300 cursor-not-allowed" title="Masih ada prodi">
                                                    <i class="fas fa-lock text-xs"></i>
                                                </span>
                                            <?php endif; ?>
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
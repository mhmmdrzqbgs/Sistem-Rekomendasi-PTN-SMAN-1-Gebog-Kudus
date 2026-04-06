<?php
/**
 * Admin - Kelola Paket Rumpun (RESPONSIVE LAYOUT)
 * Fitur: Membuat Paket Rumpun (A-F) dan memilih Mata Pelajaran di dalamnya.
 * Updated: Grid Layout & Better UI for Checkboxes
 */
$pageTitle = 'Kelola Paket Rumpun';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

$action = get('action');
$kode = get('kode');

// --- HANDLE DELETE ---
if ($action === 'delete' && $kode) {
    $db->execute("DELETE FROM paket_rumpun WHERE kode_rumpun = ?", [$kode]);
    setFlash('message', "Rumpun $kode berhasil dihapus.", 'success');
    redirect('kelola-rumpun.php');
}

// --- HANDLE SAVE (CREATE/UPDATE) ---
if (isPost()) {
    $kodeRumpun = strtoupper(trim(post('kode_rumpun'))); 
    $mapelIds = $_POST['mapel_ids'] ?? [];
    $isEdit = post('is_edit');
    $oldKode = post('old_kode');

    if (empty($kodeRumpun)) {
        setFlash('message', 'Nama/Kode Rumpun wajib diisi.', 'error');
    } elseif (empty($mapelIds)) {
        setFlash('message', 'Pilih minimal satu mata pelajaran.', 'error');
    } else {
        $db->execute("START TRANSACTION");
        try {
            if ($isEdit && $oldKode) {
                $db->execute("DELETE FROM paket_rumpun WHERE kode_rumpun = ?", [$oldKode]);
            } else {
                $cek = $db->queryOne("SELECT kode_rumpun FROM paket_rumpun WHERE kode_rumpun = ?", [$kodeRumpun]);
                if ($cek) {
                    throw new Exception("Rumpun dengan kode '$kodeRumpun' sudah ada.");
                }
            }

            foreach ($mapelIds as $mid) {
                $db->execute("INSERT INTO paket_rumpun (kode_rumpun, master_mapel_id) VALUES (?, ?)", [$kodeRumpun, $mid]);
            }

            $db->execute("COMMIT");
            setFlash('message', "Paket Rumpun $kodeRumpun berhasil disimpan.", 'success');
            redirect('kelola-rumpun.php');

        } catch (Exception $e) {
            $db->execute("ROLLBACK");
            setFlash('message', $e->getMessage(), 'error');
        }
    }
}

// --- PREPARE DATA FOR EDIT ---
$editKode = '';
$selectedMapel = [];
if ($action === 'edit' && $kode) {
    $editKode = $kode;
    $dataRumpun = $db->query("SELECT master_mapel_id FROM paket_rumpun WHERE kode_rumpun = ?", [$kode]);
    $selectedMapel = array_column($dataRumpun, 'master_mapel_id');
}

require_once __DIR__ . '/../templates/header-admin.php';

// Ambil Semua Mapel Aktif
$allMapel = $db->query("SELECT * FROM master_mapel WHERE is_active = 1 ORDER BY kelompok, nama_mapel");

// Ambil Daftar Rumpun
$listRumpun = $db->query("
    SELECT pr.kode_rumpun, 
           GROUP_CONCAT(mm.nama_mapel ORDER BY mm.kelompok, mm.nama_mapel SEPARATOR ', ') as daftar_mapel,
           COUNT(pr.id) as jumlah_mapel
    FROM paket_rumpun pr
    JOIN master_mapel mm ON pr.master_mapel_id = mm.id
    GROUP BY pr.kode_rumpun
    ORDER BY pr.kode_rumpun ASC
");
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    
    <div class="lg:col-span-4 xl:col-span-3">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm sticky top-24">
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-layer-group text-blue-600"></i> 
                    <?= $editKode ? 'Edit Rumpun' : 'Buat Rumpun Baru' ?>
                </h3>
            </div>
            
            <div class="p-5">
                <form method="POST">
                    <input type="hidden" name="is_edit" value="<?= $editKode ? 1 : 0 ?>">
                    <input type="hidden" name="old_kode" value="<?= $editKode ?>">

                    <div class="space-y-5">
                        
                        <div>
                            <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-slate-300">Nama / Kode Rumpun</label>
                            <input type="text" name="kode_rumpun" required value="<?= sanitize($editKode) ?>" 
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder-slate-400 shadow-sm uppercase font-bold"
                                placeholder="CONTOH: A">
                            <p class="text-[10px] text-slate-500 mt-1">Gunakan huruf tunggal (A, B, C) atau kode singkat.</p>
                        </div>

                        <div class="border border-slate-200 dark:border-slate-600 rounded-xl overflow-hidden">
                            <div class="bg-slate-50 dark:bg-slate-700/30 px-3 py-2 border-b border-slate-200 dark:border-slate-600">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pilih Mata Pelajaran</label>
                            </div>
                            
                            <div class="h-80 overflow-y-auto p-2 space-y-1 bg-white dark:bg-slate-800 scrollbar-thin scrollbar-thumb-slate-200 dark:scrollbar-thumb-slate-600">
                                <?php 
                                $currentGroup = '';
                                foreach ($allMapel as $m): 
                                    if ($currentGroup != $m['kelompok']) {
                                        $currentGroup = $m['kelompok'];
                                        echo "<div class='text-[10px] font-bold text-blue-500 uppercase tracking-wider mt-3 mb-1 px-2'>Kelompok $currentGroup</div>";
                                    }
                                    $isChecked = in_array($m['id'], $selectedMapel) ? 'checked' : '';
                                ?>
                                    <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition cursor-pointer select-none">
                                        <div class="relative flex items-center">
                                            <input type="checkbox" name="mapel_ids[]" value="<?= $m['id'] ?>" <?= $isChecked ?>
                                                class="peer h-4 w-4 cursor-pointer appearance-none rounded border border-slate-300 transition-all checked:border-blue-500 checked:bg-blue-500 focus:ring-2 focus:ring-blue-200">
                                            <i class="fas fa-check text-white text-[8px] absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 opacity-0 peer-checked:opacity-100 pointer-events-none"></i>
                                        </div>
                                        <span class="text-sm text-slate-700 dark:text-slate-200 leading-tight"><?= $m['nama_mapel'] ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="pt-2 flex gap-2">
                            <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 font-bold text-sm flex justify-center items-center gap-2">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                            <?php if ($editKode): ?>
                                <a href="kelola-rumpun.php" class="px-4 py-2.5 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 font-bold text-sm transition text-center">Batal</a>
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
                    <i class="fas fa-list-ul text-slate-500"></i> Daftar Paket Rumpun
                </h3>
                <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2.5 py-0.5 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                    Total: <?= count($listRumpun) ?>
                </span>
            </div>
            
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                <?php if (empty($listRumpun)): ?>
                    <div class="p-12 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700 mb-3">
                            <i class="fas fa-layer-group text-slate-400"></i>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm">Belum ada paket rumpun dibuat.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($listRumpun as $r): ?>
                        <div class="p-5 hover:bg-slate-50 dark:hover:bg-slate-700/20 transition group">
                            <div class="flex flex-col md:flex-row gap-4 justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                                            <?= $r['kode_rumpun'] ?>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-slate-900 dark:text-white text-base">Paket Rumpun <?= $r['kode_rumpun'] ?></h4>
                                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded">
                                                <?= $r['jumlah_mapel'] ?> Mata Pelajaran
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 flex flex-wrap gap-1.5 pl-[3.25rem]">
                                        <?php 
                                            $mapels = explode(', ', $r['daftar_mapel']);
                                            foreach($mapels as $mpl):
                                        ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-white border border-slate-200 text-slate-600 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300 shadow-sm">
                                                <?= $mpl ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0 mt-2 md:mt-0 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity self-start md:self-center">
                                    <a href="?action=edit&kode=<?= $r['kode_rumpun'] ?>" class="p-2 text-amber-500 hover:bg-amber-50 rounded-lg transition dark:hover:bg-amber-900/20 border border-transparent hover:border-amber-200" title="Edit">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <a href="?action=delete&kode=<?= $r['kode_rumpun'] ?>" onclick="return confirm('Hapus Paket Rumpun <?= $r['kode_rumpun'] ?>? Data mapel di dalamnya tidak akan terhapus, hanya paketnya saja.')" 
                                       class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition dark:hover:bg-red-900/20 border border-transparent hover:border-red-200" title="Hapus">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
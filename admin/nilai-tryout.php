<?php
/**
 * Admin - Input Nilai Tryout SNBT
 * Updated: Mobile Friendly (Sidebar Hidden/Toggle)
 */
$pageTitle = 'Input Nilai Tryout SNBT';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

$siswaId = get('siswa') ?: post('siswa_id');
$action = get('action');
$searchSiswa = get('search_siswa');
$sortBy = get('sort') ?: 'rank_desc';

// 1. HANDLE DELETE
if ($action === 'delete' && isset($_GET['id'])) {
    $db->execute("DELETE FROM nilai_tryout WHERE id = ?", [$_GET['id']]);
    setFlash('message', 'Data tryout berhasil dihapus', 'success');
    redirect("nilai-tryout.php?siswa=$siswaId");
}

// 2. HANDLE SAVE
if (isPost() && $siswaId) {
    $pu = floatval(post('pu'));
    $ppu = floatval(post('ppu'));
    $pbm = floatval(post('pbm'));
    $pk = floatval(post('pk'));
    $lit_indo = floatval(post('lit_indo'));
    $lit_ing = floatval(post('lit_ing'));
    $pm = floatval(post('pm'));
    
    $catatan = post('catatan');
    $tanggal = post('tanggal_tes') ?: date('Y-m-d');
    
    // Hitung rata-rata skor total (Pembagi 7)
    $skorTotal = ($pu + $ppu + $pbm + $pk + $lit_indo + $lit_ing + $pm) / 7;
    $editId = post('edit_id');

    if ($editId) {
        $db->execute("UPDATE nilai_tryout SET pu=?, ppu=?, pbm=?, pk=?, lit_indo=?, lit_ing=?, pm=?, skor_total=?, catatan=?, tanggal_tes=? WHERE id=?", 
        [$pu, $ppu, $pbm, $pk, $lit_indo, $lit_ing, $pm, $skorTotal, $catatan, $tanggal, $editId]);
        setFlash('message', "Data diperbarui! Skor: " . number_format($skorTotal, 2), 'success');
    } else {
        $db->execute("INSERT INTO nilai_tryout (siswa_id, tryout_ke, jenis, tanggal_tes, pu, ppu, pbm, pk, lit_indo, lit_ing, pm, skor_total, catatan) VALUES (?, 1, 'SNBT', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
        [$siswaId, $tanggal, $pu, $ppu, $pbm, $pk, $lit_indo, $lit_ing, $pm, $skorTotal, $catatan]);
        setFlash('message', "Data disimpan! Skor: " . number_format($skorTotal, 2), 'success');
    }
    redirect("nilai-tryout.php?siswa=$siswaId");
}

require_once __DIR__ . '/../templates/header-admin.php';

// STYLE INPUT
$inputClass = "block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-shadow duration-200";

// --- DATA UTAMA (SIDEBAR) ---
$queryList = "SELECT sp.id, u.nama, sp.kelas FROM siswa_profile sp JOIN users u ON sp.user_id = u.id";
$paramsList = [];
if ($searchSiswa) {
    $queryList .= " WHERE u.nama LIKE ? OR sp.nisn LIKE ?";
    $paramsList = ["%$searchSiswa%", "%$searchSiswa%"];
}
$queryList .= " ORDER BY u.nama ASC";
$siswaList = $db->query($queryList, $paramsList);

// Init Data
$currentSiswa = null;
$history = [];
$editData = null;
$overviewData = [];
$stats = [
    'count' => 0, 'avg_total' => 0, 'highest' => 0, 'lowest' => 0,
    'avg_pu' => 0, 'avg_ppu' => 0, 'avg_pbm' => 0, 'avg_pk' => 0,
    'avg_indo' => 0, 'avg_ing' => 0, 'avg_pm' => 0
];

if ($siswaId) {
    // MODE DETAIL
    $currentSiswa = $db->queryOne("SELECT sp.*, u.nama FROM siswa_profile sp JOIN users u ON sp.user_id = u.id WHERE sp.id = ?", [$siswaId]);
    $history = $db->query("SELECT * FROM nilai_tryout WHERE siswa_id = ? ORDER BY tanggal_tes DESC", [$siswaId]);
    if (get('action') === 'edit' && get('id')) $editData = $db->queryOne("SELECT * FROM nilai_tryout WHERE id = ?", [get('id')]);
} else {
    // MODE OVERVIEW & SORTING
    $orderBy = "nt.skor_total DESC"; // Default Rank Tertinggi
    if ($sortBy === 'rank_asc') $orderBy = "nt.skor_total ASC";
    if ($sortBy === 'nama_asc') $orderBy = "u.nama ASC";
    if ($sortBy === 'pu_desc') $orderBy = "nt.pu DESC";
    if ($sortBy === 'pk_desc') $orderBy = "nt.pk DESC";

    $overviewData = $db->query("
        SELECT sp.id, u.nama, sp.kelas, 
               nt.skor_total, nt.pu, nt.ppu, nt.pbm, nt.pk, nt.lit_indo, nt.lit_ing, nt.pm,
               CASE WHEN nt.id IS NOT NULL THEN 1 ELSE 0 END as is_filled
        FROM siswa_profile sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN (
            SELECT siswa_id, MAX(skor_total) as skor_total, MAX(pu) as pu, MAX(ppu) as ppu, 
                   MAX(pbm) as pbm, MAX(pk) as pk, MAX(lit_indo) as lit_indo, 
                   MAX(lit_ing) as lit_ing, MAX(pm) as pm, MAX(id) as id
            FROM nilai_tryout
            GROUP BY siswa_id
        ) nt ON sp.id = nt.siswa_id
        ORDER BY is_filled DESC, $orderBy
    ");

    // Hitung Statistik Global
    $sum = ['total' => 0, 'pu' => 0, 'ppu' => 0, 'pbm' => 0, 'pk' => 0, 'indo' => 0, 'ing' => 0, 'pm' => 0];
    $highest = 0; 
    $lowest = 1000; 
    $countFilled = 0;

    foreach ($overviewData as $d) {
        if ($d['is_filled']) {
            $sum['total'] += $d['skor_total'];
            $sum['pu'] += $d['pu']; $sum['ppu'] += $d['ppu']; $sum['pbm'] += $d['pbm'];
            $sum['pk'] += $d['pk']; $sum['indo'] += $d['lit_indo']; $sum['ing'] += $d['lit_ing']; $sum['pm'] += $d['pm'];
            
            if ($d['skor_total'] > $highest) $highest = $d['skor_total'];
            if ($d['skor_total'] < $lowest) $lowest = $d['skor_total'];
            $countFilled++;
        }
    }

    if ($countFilled > 0) {
        $stats['count'] = $countFilled;
        $stats['highest'] = $highest;
        $stats['lowest'] = $lowest == 1000 ? 0 : $lowest;
        $stats['avg_total'] = $sum['total'] / $countFilled;
        $stats['avg_pu'] = $sum['pu'] / $countFilled;
        $stats['avg_ppu'] = $sum['ppu'] / $countFilled;
        $stats['avg_pbm'] = $sum['pbm'] / $countFilled;
        $stats['avg_pk'] = $sum['pk'] / $countFilled;
        $stats['avg_indo'] = $sum['indo'] / $countFilled;
        $stats['avg_ing'] = $sum['ing'] / $countFilled;
        $stats['avg_pm'] = $sum['pm'] / $countFilled;
    }
}
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    
    <div class="lg:hidden mb-2">
        <button onclick="toggleListSiswa()" class="w-full flex justify-between items-center px-4 py-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm">
            <span class="font-bold text-slate-700 dark:text-white">
                <i class="fas fa-users text-indigo-500 mr-2"></i> 
                <?= $siswaId ? 'Ganti Peserta' : 'Pilih Peserta' ?>
            </span>
            <i id="chevronIcon" class="fas fa-chevron-down text-slate-400 transition-transform"></i>
        </button>
    </div>

    <div id="listSiswaContainer" class="hidden lg:block lg:col-span-1 space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden lg:sticky lg:top-24">
            <div class="px-4 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <form method="GET" action="nilai-tryout.php" class="relative">
                    <?php if ($siswaId): ?><input type="hidden" name="siswa" value="<?= $siswaId ?>"><?php endif; ?>
                    <input type="text" name="search_siswa" value="<?= sanitize($searchSiswa) ?>" placeholder="Cari nama..." class="<?= $inputClass ?> pl-10 py-2">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                </form>
            </div>
            <div class="max-h-[60vh] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700">
                <?php if (empty($siswaList)): ?>
                    <div class="p-6 text-center text-slate-400 text-xs">Tidak ada siswa.</div>
                <?php else: ?>
                    <?php foreach ($siswaList as $s): 
                        $isActive = $siswaId == $s['id'];
                        $bgClass = $isActive ? 'bg-indigo-50 dark:bg-indigo-900/20 border-l-4 border-indigo-500' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50 border-l-4 border-transparent';
                    ?>
                        <a href="nilai-tryout.php?siswa=<?= $s['id'] ?>" onclick="showLoader()" class="block px-4 py-3 transition-colors <?= $bgClass ?>">
                            <div class="flex justify-between items-center">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-white truncate"><?= sanitize($s['nama']) ?></p>
                                    <div class="flex gap-2 mt-1">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"><?= $s['kelas'] ?? '-' ?></span>
                                    </div>
                                </div>
                                <?php if ($isActive): ?><i class="fas fa-chevron-right text-xs text-indigo-500 ml-2"></i><?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="lg:col-span-3 space-y-6">
        
        <?php if (!$siswaId): ?>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mb-2"><i class="fas fa-users"></i></div>
                    <p class="text-[10px] text-slate-500 font-bold uppercase">Peserta</p>
                    <p class="text-lg font-bold text-slate-800 dark:text-white"><?= $stats['count'] ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mb-2"><i class="fas fa-chart-line"></i></div>
                    <p class="text-[10px] text-slate-500 font-bold uppercase">Rata-Rata</p>
                    <p class="text-lg font-bold text-slate-800 dark:text-white"><?= number_format($stats['avg_total'], 0) ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2"><i class="fas fa-trophy"></i></div>
                    <p class="text-[10px] text-slate-500 font-bold uppercase">Tertinggi</p>
                    <p class="text-lg font-bold text-slate-800 dark:text-white"><?= number_format($stats['highest'], 0) ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mb-2"><i class="fas fa-arrow-down"></i></div>
                    <p class="text-[10px] text-slate-500 font-bold uppercase">Terendah</p>
                    <p class="text-lg font-bold text-slate-800 dark:text-white"><?= number_format($stats['lowest'], 0) ?></p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3">
                <?php 
                $subtests = [
                    'PU' => $stats['avg_pu'], 'PPU' => $stats['avg_ppu'], 'PBM' => $stats['avg_pbm'], 
                    'PK' => $stats['avg_pk'], 'Indo' => $stats['avg_indo'], 'Ing' => $stats['avg_ing'], 'PM' => $stats['avg_pm']
                ];
                foreach ($subtests as $label => $val):
                ?>
                <div class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-3 rounded-xl text-center">
                    <div class="text-[10px] font-bold text-slate-400 uppercase"><?= $label ?></div>
                    <div class="text-base font-bold text-slate-700 dark:text-slate-200"><?= number_format($val, 0) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-list text-indigo-500"></i> Rekapitulasi
                    </h3>
                    <form method="GET" class="flex items-center gap-2">
                        <select name="sort" class="text-xs bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-white rounded-lg py-1.5 px-3" onchange="showLoader(); this.form.submit()">
                            <option value="rank_desc" <?= $sortBy == 'rank_desc' ? 'selected' : '' ?>>Skor Tertinggi</option>
                            <option value="nama_asc" <?= $sortBy == 'nama_asc' ? 'selected' : '' ?>>Nama (A-Z)</option>
                        </select>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead class="bg-slate-100 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 font-semibold uppercase text-xs">
                            <tr>
                                <th class="px-6 py-4 w-10">#</th>
                                <th class="px-6 py-4">Nama Siswa</th>
                                <th class="px-3 py-4 text-center">PU</th>
                                <th class="px-3 py-4 text-center">PK</th>
                                <th class="px-6 py-4 text-center bg-indigo-50/50 dark:bg-indigo-900/10">Total</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <?php $no=1; foreach ($overviewData as $ov): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                    <td class="px-6 py-4 text-center text-xs text-slate-400"><?= $no++ ?></td>
                                    <td class="px-6 py-4 font-medium text-slate-800 dark:text-white">
                                        <?= sanitize($ov['nama']) ?>
                                        <div class="text-[10px] text-slate-500 mt-0.5"><?= $ov['kelas'] ?></div>
                                    </td>
                                    <td class="px-3 py-4 text-center text-slate-600 dark:text-slate-300"><?= $ov['is_filled'] ? formatNumber($ov['pu'], 0) : '-' ?></td>
                                    <td class="px-3 py-4 text-center text-slate-600 dark:text-slate-300"><?= $ov['is_filled'] ? formatNumber($ov['pk'], 0) : '-' ?></td>
                                    
                                    <td class="px-6 py-4 text-center font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50/20 dark:bg-indigo-900/5">
                                        <?= $ov['is_filled'] ? number_format($ov['skor_total'], 2) : '<span class="text-slate-400 text-xs font-normal">Belum</span>' ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="?siswa=<?= $ov['id'] ?>" onclick="showLoader()" class="inline-flex items-center justify-center px-3 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg text-xs font-medium transition-colors">
                                            <?= $ov['is_filled'] ? 'Edit' : 'Input' ?> <i class="fas fa-pen ml-1"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800 dark:text-white"><?= sanitize($currentSiswa['nama']) ?></h2>
                        <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Kelas <?= $currentSiswa['kelas'] ?? '-' ?> • NISN: <?= $currentSiswa['nisn'] ?></p>
                    </div>
                    <a href="nilai-tryout.php" onclick="showLoader()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-edit text-indigo-500"></i> Form Nilai Tryout
                    </h3>
                    <?php if ($editData): ?>
                        <a href="nilai-tryout.php?siswa=<?= $siswaId ?>" class="text-xs text-rose-500 font-bold hover:underline">Batal Edit</a>
                    <?php endif; ?>
                </div>
                
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="siswa_id" value="<?= $siswaId ?>">
                        <?php if ($editData): ?><input type="hidden" name="edit_id" value="<?= $editData['id'] ?>"><?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">Tanggal Tes</label>
                                    <input type="date" name="tanggal_tes" class="<?= $inputClass ?>" value="<?= $editData['tanggal_tes'] ?? date('Y-m-d') ?>" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">Nama Tryout</label>
                                    <input type="text" name="catatan" class="<?= $inputClass ?>" value="<?= $editData['catatan'] ?? '' ?>" placeholder="Contoh: TO 1 Ruangguru">
                                </div>
                            </div>

                            <div class="space-y-4 p-5 bg-blue-50/50 dark:bg-blue-900/10 rounded-xl border border-blue-100 dark:border-blue-800/30">
                                <h4 class="text-sm font-bold text-blue-700 dark:text-blue-300 border-b border-blue-200 dark:border-blue-800 pb-2 mb-3">Tes Potensi Skolastik (TPS)</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div><label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">PU</label><input type="number" step="0.01" name="pu" class="<?= $inputClass ?>" value="<?= $editData['pu'] ?? '' ?>" placeholder="0" required></div>
                                    <div><label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">PPU</label><input type="number" step="0.01" name="ppu" class="<?= $inputClass ?>" value="<?= $editData['ppu'] ?? '' ?>" placeholder="0" required></div>
                                    <div><label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">PBM</label><input type="number" step="0.01" name="pbm" class="<?= $inputClass ?>" value="<?= $editData['pbm'] ?? '' ?>" placeholder="0" required></div>
                                    <div><label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">PK</label><input type="number" step="0.01" name="pk" class="<?= $inputClass ?>" value="<?= $editData['pk'] ?? '' ?>" placeholder="0" required></div>
                                </div>
                            </div>

                            <div class="space-y-4 p-5 bg-emerald-50/50 dark:bg-emerald-900/10 rounded-xl border border-emerald-100 dark:border-emerald-800/30">
                                <h4 class="text-sm font-bold text-emerald-700 dark:text-emerald-300 border-b border-emerald-200 dark:border-emerald-800 pb-2 mb-3">Literasi & Penalaran</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2"><label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">Literasi Bhs Indonesia</label><input type="number" step="0.01" name="lit_indo" class="<?= $inputClass ?>" value="<?= $editData['lit_indo'] ?? '' ?>" placeholder="0" required></div>
                                    <div><label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">Literasi Bhs Inggris</label><input type="number" step="0.01" name="lit_ing" class="<?= $inputClass ?>" value="<?= $editData['lit_ing'] ?? '' ?>" placeholder="0" required></div>
                                    <div><label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">Penalaran Mat (PM)</label><input type="number" step="0.01" name="pm" class="<?= $inputClass ?>" value="<?= $editData['pm'] ?? '' ?>" placeholder="0" required></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                            <button type="submit" onclick="showLoader()" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold shadow-lg shadow-indigo-500/30 transition flex items-center gap-2">
                                <i class="fas fa-save"></i> Simpan Skor
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($history)): ?>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mt-6">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-history text-slate-400"></i> Riwayat Tryout
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs md:text-sm border-collapse">
                        <thead class="bg-slate-100 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 font-semibold uppercase">
                            <tr>
                                <th class="px-4 py-3 border-b dark:border-slate-700">Tanggal</th>
                                <th class="px-2 py-3 border-b dark:border-slate-700 text-center">PU</th>
                                <th class="px-2 py-3 border-b dark:border-slate-700 text-center">PPU</th>
                                <th class="px-2 py-3 border-b dark:border-slate-700 text-center">PBM</th>
                                <th class="px-2 py-3 border-b dark:border-slate-700 text-center">PK</th>
                                <th class="px-2 py-3 border-b dark:border-slate-700 text-center">Indo</th>
                                <th class="px-2 py-3 border-b dark:border-slate-700 text-center">Ing</th>
                                <th class="px-2 py-3 border-b dark:border-slate-700 text-center">PM</th>
                                <th class="px-4 py-3 border-b dark:border-slate-700 text-center bg-indigo-50/50 dark:bg-indigo-900/10">Total</th>
                                <th class="px-4 py-3 border-b dark:border-slate-700 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <?php foreach ($history as $h): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap text-slate-700 dark:text-slate-300">
                                    <div class="font-medium"><?= date('d/m/y', strtotime($h['tanggal_tes'])) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= sanitize($h['catatan']) ?></div>
                                </td>
                                <td class="px-2 py-3 text-center text-slate-600 dark:text-slate-400"><?= $h['pu'] ?></td>
                                <td class="px-2 py-3 text-center text-slate-600 dark:text-slate-400"><?= $h['ppu'] ?></td>
                                <td class="px-2 py-3 text-center text-slate-600 dark:text-slate-400"><?= $h['pbm'] ?></td>
                                <td class="px-2 py-3 text-center text-slate-600 dark:text-slate-400"><?= $h['pk'] ?></td>
                                <td class="px-2 py-3 text-center text-slate-600 dark:text-slate-400"><?= $h['lit_indo'] ?></td>
                                <td class="px-2 py-3 text-center text-slate-600 dark:text-slate-400"><?= $h['lit_ing'] ?></td>
                                <td class="px-2 py-3 text-center text-slate-600 dark:text-slate-400"><?= $h['pm'] ?></td>
                                <td class="px-4 py-3 text-center font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50/20 dark:bg-indigo-900/5">
                                    <?= number_format($h['skor_total'], 2) ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="?siswa=<?= $siswaId ?>&action=edit&id=<?= $h['id'] ?>" onclick="showLoader()" class="text-amber-500 hover:text-amber-600" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?siswa=<?= $siswaId ?>&action=delete&id=<?= $h['id'] ?>" class="text-rose-500 hover:text-rose-600" 
                                           onclick="return confirm('Hapus data tryout ini?')" title="Hapus">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
    function toggleListSiswa() {
        const container = document.getElementById('listSiswaContainer');
        const icon = document.getElementById('chevronIcon');
        container.classList.toggle('hidden');
        if (container.classList.contains('hidden')) {
            icon.classList.remove('rotate-180');
        } else {
            icon.classList.add('rotate-180');
        }
    }
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
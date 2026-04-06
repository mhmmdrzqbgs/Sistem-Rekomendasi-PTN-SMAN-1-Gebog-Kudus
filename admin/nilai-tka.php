<?php
/**
 * Admin - Input Nilai TKA (Tes Kemampuan Akademik)
 * Updated: Mobile Friendly (Sidebar Hidden/Toggle)
 */
$pageTitle = 'Input Nilai TKA';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

$siswaId = get('siswa') ?: post('siswa_id');
$action = get('action');
$searchSiswa = get('search_siswa');
$sortBy = get('sort') ?: 'nama_asc';

// 1. HANDLE DELETE
if ($action === 'delete' && isset($_GET['id'])) {
    $db->execute("DELETE FROM nilai_tka WHERE id = ?", [$_GET['id']]);
    setFlash('message', 'Data TKA berhasil dihapus', 'success');
    redirect("nilai-tka.php?siswa=$siswaId");
}

// 2. HANDLE SAVE
if (isPost() && $siswaId) {
    // Mapel Wajib TKA
    $mtk = floatval(post('nilai_mtk'));
    $indo = floatval(post('nilai_indo'));
    $ing = floatval(post('nilai_inggris'));
    
    // Mapel Pilihan
    $mapel1 = post('mapel_pilihan_1');
    $nilai1 = floatval(post('nilai_pilihan_1'));
    $mapel2 = post('mapel_pilihan_2');
    $nilai2 = floatval(post('nilai_pilihan_2'));

    // Hitung Rata-rata (Pembagi dinamis berdasarkan mapel yang diisi)
    $total = $mtk + $indo + $ing + $nilai1 + $nilai2;
    $pembagi = 3 + ($nilai1 > 0 ? 1 : 0) + ($nilai2 > 0 ? 1 : 0);
    $rata_rata = $pembagi > 0 ? $total / $pembagi : 0;

    $existing = $db->queryOne("SELECT id FROM nilai_tka WHERE siswa_id = ?", [$siswaId]);

    if ($existing) {
        $db->execute("UPDATE nilai_tka SET nilai_mtk=?, nilai_indo=?, nilai_inggris=?, mapel_pilihan_1=?, nilai_pilihan_1=?, mapel_pilihan_2=?, nilai_pilihan_2=?, rata_rata_tka=? WHERE id=?", 
        [$mtk, $indo, $ing, $mapel1, $nilai1, $mapel2, $nilai2, $rata_rata, $existing['id']]);
        setFlash('message', "Nilai TKA diperbarui! Rata-rata: " . number_format($rata_rata, 2), 'success');
    } else {
        $db->execute("INSERT INTO nilai_tka (siswa_id, nilai_mtk, nilai_indo, nilai_inggris, mapel_pilihan_1, nilai_pilihan_1, mapel_pilihan_2, nilai_pilihan_2, rata_rata_tka) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", 
        [$siswaId, $mtk, $indo, $ing, $mapel1, $nilai1, $mapel2, $nilai2, $rata_rata]);
        setFlash('message', "Nilai TKA disimpan! Rata-rata: " . number_format($rata_rata, 2), 'success');
    }
    
    redirect("nilai-tka.php?siswa=$siswaId");
}

require_once __DIR__ . '/../templates/header-admin.php';

// STYLE INPUT
$inputClass = "block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-shadow duration-200";

// --- DATA SIDEBAR ---
$queryList = "SELECT sp.id, u.nama, sp.kelas FROM siswa_profile sp JOIN users u ON sp.user_id = u.id";
$paramsList = [];
if ($searchSiswa) {
    $queryList .= " WHERE u.nama LIKE ? OR sp.nisn LIKE ?";
    $paramsList = ["%$searchSiswa%", "%$searchSiswa%"];
}
$queryList .= " ORDER BY u.nama ASC";
$siswaList = $db->query($queryList, $paramsList);

// Init Variables
$currentSiswa = null;
$dataTKA = null;
$overviewData = [];
$mapelStats = ['mtk' => 0, 'indo' => 0, 'ing' => 0, 'count' => 0];

if ($siswaId) {
    $currentSiswa = $db->queryOne("SELECT sp.*, u.nama FROM siswa_profile sp JOIN users u ON sp.user_id = u.id WHERE sp.id = ?", [$siswaId]);
    $dataTKA = $db->queryOne("SELECT * FROM nilai_tka WHERE siswa_id = ?", [$siswaId]);
} else {
    // Logic Sorting & Overview
    $orderBy = "u.nama ASC";
    if ($sortBy === 'nama_desc') $orderBy = "u.nama DESC";
    if ($sortBy === 'rank_desc') $orderBy = "nt.rata_rata_tka DESC";
    if ($sortBy === 'rank_asc') $orderBy = "nt.rata_rata_tka ASC";
    
    $overviewData = $db->query("
        SELECT sp.id, u.nama, sp.kelas, 
               nt.rata_rata_tka, nt.nilai_mtk, nt.nilai_indo, nt.nilai_inggris,
               nt.mapel_pilihan_1, nt.nilai_pilihan_1,
               nt.mapel_pilihan_2, nt.nilai_pilihan_2,
               CASE WHEN nt.id IS NOT NULL THEN 1 ELSE 0 END as is_filled
        FROM siswa_profile sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN nilai_tka nt ON sp.id = nt.siswa_id
        ORDER BY is_filled DESC, $orderBy
    ");

    // Hitung Stats
    $totalMtk = 0; $totalIndo = 0; $totalIng = 0; $countFilled = 0;
    foreach ($overviewData as $d) {
        if ($d['is_filled']) {
            $totalMtk += $d['nilai_mtk'];
            $totalIndo += $d['nilai_indo'];
            $totalIng += $d['nilai_inggris'];
            $countFilled++;
        }
    }
    if ($countFilled > 0) {
        $mapelStats['mtk'] = $totalMtk / $countFilled;
        $mapelStats['indo'] = $totalIndo / $countFilled;
        $mapelStats['ing'] = $totalIng / $countFilled;
        $mapelStats['count'] = $countFilled;
    }
}

// Get All Elective Subjects
$mapelPilihanList = $db->query("SELECT nama_mapel FROM master_mapel WHERE kelompok = 'Pilihan' ORDER BY nama_mapel ASC");
$pilihanMapel = array_column($mapelPilihanList, 'nama_mapel');
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
                <form method="GET" action="nilai-tka.php" class="relative">
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
                        <a href="nilai-tka.php?siswa=<?= $s['id'] ?>" onclick="showLoader()" class="block px-4 py-3 transition-colors <?= $bgClass ?>">
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
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mb-2"><i class="fas fa-calculator"></i></div>
                    <p class="text-[10px] text-slate-500 font-bold uppercase">Rata MTK</p>
                    <p class="text-lg font-bold text-slate-800 dark:text-white"><?= number_format($mapelStats['mtk'], 1) ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2"><i class="fas fa-book"></i></div>
                    <p class="text-[10px] text-slate-500 font-bold uppercase">Rata Indo</p>
                    <p class="text-lg font-bold text-slate-800 dark:text-white"><?= number_format($mapelStats['indo'], 1) ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="w-10 h-10 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mb-2"><i class="fas fa-globe"></i></div>
                    <p class="text-[10px] text-slate-500 font-bold uppercase">Rata Ing</p>
                    <p class="text-lg font-bold text-slate-800 dark:text-white"><?= number_format($mapelStats['ing'], 1) ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="w-10 h-10 rounded-full bg-slate-50 text-slate-600 flex items-center justify-center mb-2"><i class="fas fa-user-check"></i></div>
                    <p class="text-[10px] text-slate-500 font-bold uppercase">Data Masuk</p>
                    <p class="text-lg font-bold text-slate-800 dark:text-white"><?= $mapelStats['count'] ?> Siswa</p>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-chart-bar text-indigo-500"></i> Rekapitulasi
                    </h3>
                    <form method="GET" class="flex items-center gap-2">
                        <select name="sort" class="text-xs bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-white rounded-lg py-1.5 px-3" onchange="showLoader(); this.form.submit()">
                            <option value="nama_asc" <?= $sortBy == 'nama_asc' ? 'selected' : '' ?>>Nama (A-Z)</option>
                            <option value="rank_desc" <?= $sortBy == 'rank_desc' ? 'selected' : '' ?>>Rata Tertinggi</option>
                        </select>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead class="bg-slate-100 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 font-semibold uppercase text-xs">
                            <tr>
                                <th class="px-6 py-4 w-10">#</th>
                                <th class="px-6 py-4">Nama Siswa</th>
                                <th class="px-3 py-4 text-center">MTK</th>
                                <th class="px-3 py-4 text-center">Indo</th>
                                <th class="px-3 py-4 text-center">Ing</th>
                                <th class="px-4 py-4 text-center bg-blue-50/50">Pilihan 1</th>
                                <th class="px-4 py-4 text-center bg-blue-50/50">Pilihan 2</th>
                                <th class="px-6 py-4 text-center bg-indigo-50/50">Rata2</th>
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
                                    <td class="px-3 py-4 text-center text-slate-600 dark:text-slate-300"><?= $ov['is_filled'] ? formatNumber($ov['nilai_mtk']) : '-' ?></td>
                                    <td class="px-3 py-4 text-center text-slate-600 dark:text-slate-300"><?= $ov['is_filled'] ? formatNumber($ov['nilai_indo']) : '-' ?></td>
                                    <td class="px-3 py-4 text-center text-slate-600 dark:text-slate-300"><?= $ov['is_filled'] ? formatNumber($ov['nilai_inggris']) : '-' ?></td>
                                    
                                    <td class="px-4 py-4 text-center bg-blue-50/30">
                                        <?php if ($ov['is_filled'] && $ov['mapel_pilihan_1']): ?>
                                            <div class="text-[10px] uppercase font-bold text-blue-600 truncate max-w-[80px] mx-auto"><?= $ov['mapel_pilihan_1'] ?></div>
                                            <div class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= formatNumber($ov['nilai_pilihan_1']) ?></div>
                                        <?php else: ?> - <?php endif; ?>
                                    </td>

                                    <td class="px-4 py-4 text-center bg-blue-50/30">
                                        <?php if ($ov['is_filled'] && $ov['mapel_pilihan_2']): ?>
                                            <div class="text-[10px] uppercase font-bold text-blue-600 truncate max-w-[80px] mx-auto"><?= $ov['mapel_pilihan_2'] ?></div>
                                            <div class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= formatNumber($ov['nilai_pilihan_2']) ?></div>
                                        <?php else: ?> - <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-4 text-center font-bold text-indigo-600 bg-indigo-50/20">
                                        <?= $ov['is_filled'] ? number_format($ov['rata_rata_tka'], 2) : '<span class="text-slate-400 text-xs font-normal">Belum</span>' ?>
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
                    <a href="nilai-tka.php" onclick="showLoader()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-file-contract text-indigo-500"></i> Form Nilai TKA
                    </h3>
                    <?php if ($dataTKA): ?>
                        <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-xs font-bold rounded-full">Rata-rata: <?= number_format($dataTKA['rata_rata_tka'], 2) ?></span>
                    <?php endif; ?>
                </div>

                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="siswa_id" value="<?= $siswaId ?>">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-4">
                                <h4 class="text-sm font-bold text-slate-500 uppercase border-b pb-2">Mapel Wajib TKA</h4>
                                <div><label class="block text-sm font-medium mb-1">Matematika</label><input type="number" step="0.01" name="nilai_mtk" class="<?= $inputClass ?>" value="<?= $dataTKA['nilai_mtk'] ?? '' ?>" required></div>
                                <div><label class="block text-sm font-medium mb-1">B. Indonesia</label><input type="number" step="0.01" name="nilai_indo" class="<?= $inputClass ?>" value="<?= $dataTKA['nilai_indo'] ?? '' ?>" required></div>
                                <div><label class="block text-sm font-medium mb-1">B. Inggris</label><input type="number" step="0.01" name="nilai_inggris" class="<?= $inputClass ?>" value="<?= $dataTKA['nilai_inggris'] ?? '' ?>" required></div>
                            </div>

                            <div class="space-y-4">
                                <h4 class="text-sm font-bold text-slate-500 uppercase border-b pb-2">Mapel Pilihan (TKA)</h4>
                                <div class="grid grid-cols-3 gap-2 items-end">
                                    <div class="col-span-2"><label class="block text-xs text-slate-500 mb-1">Mapel 1</label>
                                        <select name="mapel_pilihan_1" class="<?= $inputClass ?>">
                                            <option value="">Pilih...</option>
                                            <?php foreach ($pilihanMapel as $pm): ?><option value="<?= $pm ?>" <?= ($dataTKA['mapel_pilihan_1'] ?? '') == $pm ? 'selected' : '' ?>><?= $pm ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-span-1"><label class="block text-xs text-slate-500 mb-1">Nilai</label><input type="number" step="0.01" name="nilai_pilihan_1" class="<?= $inputClass ?>" value="<?= $dataTKA['nilai_pilihan_1'] ?? '' ?>" required></div>
                                </div>
                                <div class="grid grid-cols-3 gap-2 items-end">
                                    <div class="col-span-2"><label class="block text-xs text-slate-500 mb-1">Mapel 2</label>
                                        <select name="mapel_pilihan_2" class="<?= $inputClass ?>">
                                            <option value="">Pilih...</option>
                                            <?php foreach ($pilihanMapel as $pm): ?><option value="<?= $pm ?>" <?= ($dataTKA['mapel_pilihan_2'] ?? '') == $pm ? 'selected' : '' ?>><?= $pm ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-span-1"><label class="block text-xs text-slate-500 mb-1">Nilai</label><input type="number" step="0.01" name="nilai_pilihan_2" class="<?= $inputClass ?>" value="<?= $dataTKA['nilai_pilihan_2'] ?? '' ?>" required></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 pt-4 border-t flex justify-end gap-3">
                            <?php if ($dataTKA): ?>
                                <a href="?siswa=<?= $siswaId ?>&action=delete&id=<?= $dataTKA['id'] ?>" class="px-4 py-2 bg-rose-100 text-rose-600 rounded-lg text-sm font-bold flex items-center gap-2" onclick="return confirm('Hapus data?')"><i class="fas fa-trash-alt"></i> Hapus</a>
                            <?php endif; ?>
                            <button type="submit" onclick="showLoader()" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-bold flex items-center gap-2"><i class="fas fa-save"></i> Simpan</button>
                        </div>
                    </form>
                </div>
            </div>

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
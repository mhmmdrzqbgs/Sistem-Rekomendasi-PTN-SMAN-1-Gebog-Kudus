<?php
/**
 * Admin - Input & Monitoring Nilai Rapor (MOBILE FRIENDLY LIKE TKA)
 * Fitur: Sidebar Toggle, Layout Responsif, Input Nilai
 */
$pageTitle = 'Input & Monitoring Nilai';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

// --- AMBIL PARAMETER ---
$siswaId = get('siswa') ?: post('siswa_id');
$semester = get('semester') ?: post('semester') ?: 1;
$searchSiswa = get('search_siswa'); 

// Filter Dashboard
$fKelas = get('f_kelas');
$fNama = get('f_nama');
$fSort = get('f_sort');
$page = get('page') ?: 1;
$limit = 200; 
$offset = ($page - 1) * $limit;

// --- LOGIC PROCESS (Sama seperti sebelumnya) ---
if (isPost() && isset($_POST['update_rumpun']) && $siswaId) {
    $newRumpun = post('kode_rumpun_baru');
    $semesterInput = post('semester');
    $rapor = $db->queryOne("SELECT id FROM nilai_rapor WHERE siswa_id = ? AND semester = ?", [$siswaId, $semesterInput]);
    if ($rapor) {
        $db->execute("UPDATE nilai_rapor SET kode_rumpun = ? WHERE id = ?", [$newRumpun, $rapor['id']]);
    } else {
        $db->execute("INSERT INTO nilai_rapor (siswa_id, semester, rata_rata, kode_rumpun) VALUES (?, ?, 0, ?)", [$siswaId, $semesterInput, $newRumpun]);
    }
    $db->execute("UPDATE siswa_profile SET kode_rumpun = ? WHERE id = ?", [$newRumpun, $siswaId]);
    setFlash('message', "Rumpun Semester $semesterInput disimpan.", 'success');
    redirect("nilai-rapor.php?siswa=$siswaId&semester=$semesterInput");
}

if (isPost() && isset($_POST['add_mapel_id']) && $siswaId) {
    $newMapelId = post('add_mapel_id');
    $exist = $db->queryOne("SELECT id FROM siswa_mapel_pilihan WHERE siswa_id = ? AND master_mapel_id = ?", [$siswaId, $newMapelId]);
    if (!$exist) {
        $db->execute("INSERT INTO siswa_mapel_pilihan (siswa_id, master_mapel_id, tingkat) VALUES (?, ?, 11)", [$siswaId, $newMapelId]);
        setFlash('message', "Mapel ditambahkan.", 'success');
    }
    redirect("nilai-rapor.php?siswa=$siswaId&semester=$semester");
}

if (isset($_GET['remove_mapel']) && $siswaId) {
    $removeId = $_GET['remove_mapel'];
    $db->execute("DELETE FROM siswa_mapel_pilihan WHERE siswa_id = ? AND master_mapel_id = ?", [$siswaId, $removeId]);
    $db->execute("DELETE FROM nilai_rapor_detail WHERE master_mapel_id = ? AND nilai_rapor_id IN (SELECT id FROM nilai_rapor WHERE siswa_id = ? AND semester = ?)", [$removeId, $siswaId, $semester]);
    setFlash('message', "Mapel dihapus.", 'success');
    redirect("nilai-rapor.php?siswa=$siswaId&semester=$semester");
}

if (isPost() && isset($_POST['save_nilai']) && $siswaId) {
    $semesterInput = post('semester');
    $db->execute("START TRANSACTION");
    try {
        $totalNilai = 0; $countMapel = 0;
        $rapor = $db->queryOne("SELECT id FROM nilai_rapor WHERE siswa_id = ? AND semester = ?", [$siswaId, $semesterInput]);
        if ($rapor) {
            $raporId = $rapor['id'];
        } else {
            $profil = $db->queryOne("SELECT kode_rumpun FROM siswa_profile WHERE id = ?", [$siswaId]);
            $defaultRumpun = $profil['kode_rumpun'] ?? null;
            $db->execute("INSERT INTO nilai_rapor (siswa_id, semester, rata_rata, kode_rumpun) VALUES (?, ?, 0, ?)", [$siswaId, $semesterInput, $defaultRumpun]);
            $raporId = $db->lastInsertId();
        }

        $validMapelIds = [];
        foreach ($_POST as $key => $val) {
            if (strpos($key, 'mapel_') === 0) {
                $mapelId = str_replace('mapel_', '', $key);
                $nilaiRaw = $val;
                $nilai = ($nilaiRaw === '') ? 0 : floatval($nilaiRaw);
                $validMapelIds[] = $mapelId;

                $detail = $db->queryOne("SELECT id FROM nilai_rapor_detail WHERE nilai_rapor_id = ? AND master_mapel_id = ?", [$raporId, $mapelId]);
                if ($detail) {
                    $db->execute("UPDATE nilai_rapor_detail SET nilai = ? WHERE id = ?", [$nilai, $detail['id']]);
                } else {
                    $db->execute("INSERT INTO nilai_rapor_detail (nilai_rapor_id, master_mapel_id, nilai) VALUES (?, ?, ?)", [$raporId, $mapelId, $nilai]);
                }
                if ($nilai > 0) { $totalNilai += $nilai; $countMapel++; }
            }
        }

        if (!empty($validMapelIds)) {
            $placeholders = implode(',', array_fill(0, count($validMapelIds), '?'));
            $sqlDelete = "DELETE FROM nilai_rapor_detail WHERE nilai_rapor_id = ? AND master_mapel_id NOT IN ($placeholders)";
            $paramsDelete = array_merge([$raporId], $validMapelIds);
            $db->execute($sqlDelete, $paramsDelete);
        }

        $rataRata = $countMapel > 0 ? $totalNilai / $countMapel : 0;
        $db->execute("UPDATE nilai_rapor SET rata_rata = ? WHERE id = ?", [$rataRata, $raporId]);
        $db->execute("COMMIT");
        setFlash('message', "Nilai Semester $semesterInput berhasil disimpan!", 'success');
    } catch (Exception $e) {
        $db->execute("ROLLBACK");
        setFlash('message', "Gagal: " . $e->getMessage(), 'error');
    }
    redirect("nilai-rapor.php?siswa=$siswaId&semester=$semesterInput");
}

require_once __DIR__ . '/../templates/header-admin.php';
$inputClass = "w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 sm:text-sm shadow-sm transition-all";

// QUERY SIDEBAR
$queryList = "SELECT sp.id, u.nama, sp.kelas FROM siswa_profile sp JOIN users u ON sp.user_id = u.id";
$paramsList = [];
if ($searchSiswa) {
    $queryList .= " WHERE u.nama LIKE ? OR sp.nisn LIKE ?";
    $paramsList = ["%$searchSiswa%", "%$searchSiswa%"];
}
$queryList .= " ORDER BY u.nama ASC";
$siswaList = $db->query($queryList, $paramsList);

// INIT VARIABEL
$currentSiswa = null; $listWajib = []; $listPeminatan = []; $existingNilai = []; $rekapSiswa = []; $availableMapelPilihan = []; $listSemuaRumpun = [];
$totalDataRekap = 0; $totalPages = 1; $stats = ['avg' => 0, 'max' => 0, 'min' => 0, 'count' => 0];

if ($siswaId) {
    $currentSiswa = $db->queryOne("SELECT sp.*, u.nama FROM siswa_profile sp JOIN users u ON sp.user_id = u.id WHERE sp.id = ?", [$siswaId]);
    
    if ($currentSiswa) {
        $rapor = $db->queryOne("SELECT id, kode_rumpun FROM nilai_rapor WHERE siswa_id = ? AND semester = ?", [$siswaId, $semester]);
        $kodeRumpunAktif = $rapor['kode_rumpun'] ?? $currentSiswa['kode_rumpun'];
        $listSemuaRumpun = $db->query("SELECT DISTINCT kode_rumpun FROM paket_rumpun ORDER BY kode_rumpun ASC");
        
        $rumpunIds = [];
        if (!empty($kodeRumpunAktif)) {
            $rumpunRaw = $db->query("SELECT master_mapel_id FROM paket_rumpun WHERE kode_rumpun = ?", [$kodeRumpunAktif]);
            $rumpunIds = array_column($rumpunRaw, 'master_mapel_id');
        }
        $manualRaw = $db->query("SELECT master_mapel_id FROM siswa_mapel_pilihan WHERE siswa_id = ?", [$siswaId]);
        $manualIds = array_column($manualRaw, 'master_mapel_id');
        $allPeminatanIds = array_unique(array_merge($rumpunIds, $manualIds));
        
        $allMaster = $db->query("SELECT * FROM master_mapel ORDER BY kelompok DESC, id ASC");
        
        foreach ($allMaster as $m) {
            $nama = $m['nama_mapel'];
            $isSejarah = stripos($nama, 'Sejarah') !== false;
            $isInformatika = stripos($nama, 'Informatika') !== false;
            $isIPA = ($nama == 'IPA' || $nama == 'Ilmu Pengetahuan Alam');
            $isIPS = ($nama == 'IPS' || $nama == 'Ilmu Pengetahuan Sosial');

            if ($semester <= 2) {
                if ($isSejarah) continue; 
                if ($isInformatika) { $listWajib[] = $m; continue; }
            }
            if ($semester > 2) {
                if ($isIPA || $isIPS) continue; 
            }

            if (in_array($m['id'], $allPeminatanIds)) {
                $m['is_manual'] = in_array($m['id'], $manualIds) && !in_array($m['id'], $rumpunIds);
                $listPeminatan[] = $m;
            } else if ($m['kelompok'] == 'Wajib' || $m['kelompok'] == 'Muatan Lokal') {
                $listWajib[] = $m;
            }
        }

        $takenIdsStr = empty($allPeminatanIds) ? "0" : implode(',', $allPeminatanIds);
        $rawAvailable = $db->query("SELECT * FROM master_mapel WHERE id NOT IN ($takenIdsStr) AND kelompok = 'Pilihan' ORDER BY nama_mapel ASC");
        $availableMapelPilihan = [];
        foreach ($rawAvailable as $am) {
            if ($semester <= 2 && stripos($am['nama_mapel'], 'Informatika') !== false) continue;
            $availableMapelPilihan[] = $am;
        }

        if ($rapor) {
            $details = $db->query("SELECT master_mapel_id, nilai FROM nilai_rapor_detail WHERE nilai_rapor_id = ?", [$rapor['id']]);
            foreach ($details as $d) $existingNilai[$d['master_mapel_id']] = $d['nilai'];
        }
    }
} else {
    // DASHBOARD REKAP
    $listKelas = $db->query("SELECT DISTINCT kelas FROM siswa_profile WHERE kelas IS NOT NULL AND kelas != '' ORDER BY LENGTH(kelas) ASC, kelas ASC");
    $baseQuery = "FROM siswa_profile sp JOIN users u ON sp.user_id = u.id LEFT JOIN nilai_rapor nr ON sp.id = nr.siswa_id";
    $paramsRekap = []; $conditions = [];
    if ($fKelas) { $conditions[] = "sp.kelas = ?"; $paramsRekap[] = $fKelas; }
    if ($fNama) { $conditions[] = "u.nama LIKE ?"; $paramsRekap[] = "%$fNama%"; }
    if (!empty($conditions)) { $baseQuery .= " WHERE " . implode(" AND ", $conditions); }

    $statsQuery = "SELECT AVG(avg_skor) as rata_global, MAX(avg_skor) as max_skor, MIN(avg_skor) as min_skor, COUNT(*) as total_siswa 
                   FROM (SELECT AVG(NULLIF(nr.rata_rata, 0)) as avg_skor $baseQuery GROUP BY sp.id) as subquery WHERE avg_skor > 0";
    $statsResult = $db->queryOne($statsQuery, $paramsRekap);
    if($statsResult) {
        $stats = ['avg' => $statsResult['rata_global'], 'max' => $statsResult['max_skor'], 'min' => $statsResult['min_skor'], 'count' => $statsResult['total_siswa']];
    }

    $countQuery = "SELECT COUNT(DISTINCT sp.id) as total $baseQuery";
    $totalResult = $db->queryOne($countQuery, $paramsRekap);
    $totalDataRekap = $totalResult['total'] ?? 0;
    $totalPages = ceil($totalDataRekap / $limit);

    $queryRekap = "SELECT sp.id, u.nama, sp.kelas, AVG(NULLIF(nr.rata_rata, 0)) as skor_akhir, 
                   MAX(CASE WHEN nr.semester = 1 THEN nr.rata_rata END) as smt1, 
                   MAX(CASE WHEN nr.semester = 2 THEN nr.rata_rata END) as smt2, 
                   MAX(CASE WHEN nr.semester = 3 THEN nr.rata_rata END) as smt3, 
                   MAX(CASE WHEN nr.semester = 4 THEN nr.rata_rata END) as smt4, 
                   MAX(CASE WHEN nr.semester = 5 THEN nr.rata_rata END) as smt5, 
                   MAX(CASE WHEN nr.semester = 6 THEN nr.rata_rata END) as smt6 $baseQuery GROUP BY sp.id, u.nama, sp.kelas";
    
    if ($fSort == 'highest') { $queryRekap .= " ORDER BY skor_akhir DESC"; } 
    elseif ($fSort == 'lowest') { $queryRekap .= " ORDER BY skor_akhir ASC"; } 
    else { $queryRekap .= " ORDER BY LENGTH(sp.kelas) ASC, sp.kelas ASC, u.nama ASC"; }
    
    $queryRekap .= " LIMIT $limit OFFSET $offset";
    $rekapSiswa = $db->query($queryRekap, $paramsRekap);
}
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    
    <div class="lg:hidden mb-2">
        <button onclick="toggleListSiswa()" class="w-full flex justify-between items-center px-4 py-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm">
            <span class="font-bold text-slate-700 dark:text-white">
                <i class="fas fa-users text-indigo-500 mr-2"></i> 
                <?= $siswaId ? 'Ganti Siswa' : 'Pilih Siswa' ?>
            </span>
            <i id="chevronIcon" class="fas fa-chevron-down text-slate-400 transition-transform"></i>
        </button>
    </div>

    <div id="listSiswaContainer" class="hidden lg:block lg:col-span-1 space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden lg:sticky lg:top-24">
            <div class="px-4 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <form method="GET" class="relative">
                    <?php if ($siswaId): ?><input type="hidden" name="siswa" value="<?= $siswaId ?>"><?php endif; ?>
                    <input type="text" name="search_siswa" value="<?= sanitize($searchSiswa) ?>" placeholder="Cari nama..." class="<?= $inputClass ?> pl-10 py-2">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                </form>
            </div>
            <div class="max-h-[60vh] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700 scrollbar-thin scrollbar-thumb-slate-200 dark:scrollbar-thumb-slate-600">
                <?php if (empty($siswaList)): ?>
                    <div class="p-6 text-center text-slate-400 text-xs">Tidak ada siswa.</div>
                <?php else: ?>
                    <?php foreach ($siswaList as $s): 
                        $active = $siswaId == $s['id'] ? 'bg-blue-50 border-l-4 border-blue-500 dark:bg-blue-900/30' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50 border-l-4 border-transparent';
                    ?>
                        <a href="?siswa=<?= $s['id'] ?>" onclick="showLoader()" class="block px-4 py-3 transition-colors <?= $active ?>">
                            <div class="flex justify-between items-center">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-white truncate"><?= sanitize($s['nama']) ?></p>
                                    <div class="flex gap-2 mt-1">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"><?= $s['kelas'] ?? '-' ?></span>
                                    </div>
                                </div>
                                <?php if($siswaId == $s['id']): ?><i class="fas fa-chevron-right text-xs text-indigo-500 ml-2"></i><?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="lg:col-span-3 space-y-6">
        
        <?php if ($siswaId): ?>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800 dark:text-white"><?= sanitize($currentSiswa['nama']) ?></h2>
                        <p class="text-sm text-slate-500 mt-1 flex items-center gap-2">
                            <span class="bg-white border border-slate-200 px-2 py-0.5 rounded text-xs font-bold"><?= $currentSiswa['kelas'] ?></span>
                            <span><?= $currentSiswa['asal_sekolah'] ?></span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <a href="nilai-rapor.php" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg text-sm font-medium transition-colors text-slate-700">Kembali</a>
                        <button type="submit" form="formNilai" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold shadow-lg shadow-blue-500/30 transition">Simpan</button>
                    </div>
                </div>

                <div class="p-6">
                    <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide mb-6 border-b border-slate-100">
                        <?php for($i=1; $i<=6; $i++): $cls = $semester == $i ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>
                            <a href="?siswa=<?= $siswaId ?>&semester=<?= $i ?>" class="px-5 py-2 rounded-lg text-sm font-bold whitespace-nowrap transition-all <?= $cls ?>">Semester <?= $i ?></a>
                        <?php endfor; ?>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 mb-8 p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-100 dark:border-indigo-800">
                        <div class="text-sm text-indigo-700 dark:text-indigo-300 font-medium">
                            <i class="fas fa-layer-group mr-1"></i> Rumpun Semester <?= $semester ?>: <strong><?= $kodeRumpunAktif ?: '-' ?></strong>
                        </div>
                        <button onclick="document.getElementById('modalChangeRumpun').classList.remove('hidden')" class="text-xs text-white bg-indigo-500 hover:bg-indigo-600 px-3 py-1 rounded-md ml-auto font-bold shadow-sm">Ubah</button>
                    </div>

                    <form method="POST" id="formNilai">
                        <input type="hidden" name="save_nilai" value="1">
                        <input type="hidden" name="siswa_id" value="<?= $siswaId ?>"><input type="hidden" name="semester" value="<?= $semester ?>">
                        
                        <div class="mb-8">
                            <h4 class="text-sm font-bold text-slate-800 dark:text-white uppercase mb-4 flex items-center gap-2"><span class="w-1 h-5 bg-blue-500 rounded-full"></span> Mapel Umum</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                                <?php foreach ($listWajib as $m): ?>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 mb-1 truncate" title="<?= $m['nama_mapel'] ?>"><?= $m['nama_mapel'] ?></label>
                                        <input type="number" step="0.01" name="mapel_<?= $m['id'] ?>" class="<?= $inputClass ?> h-10 px-3 font-medium" value="<?= $existingNilai[$m['id']] ?? '' ?>" placeholder="0">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="flex justify-between items-end mb-4 border-b border-slate-100 pb-2">
                                <h4 class="text-sm font-bold text-slate-800 uppercase flex items-center gap-2"><span class="w-1 h-5 bg-indigo-500 rounded-full"></span> Pilihan</h4>
                                <?php if($semester > 2): ?>
                                    <button type="button" onclick="document.getElementById('modalAddMapel').classList.remove('hidden')" class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-lg border border-indigo-200 hover:bg-indigo-100 font-bold">+ Tambah</button>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($semester <= 2): ?>
                                <div class="p-6 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-slate-100 dark:border-slate-600 text-center">
                                    <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Semester 1 & 2 tidak ada mapel pilihan.</p>
                                </div>
                            <?php else: ?>
                                <?php if (empty($listPeminatan)): ?>
                                    <div class="p-6 bg-slate-50 rounded-xl text-center text-sm text-slate-500 italic">Belum ada mapel peminatan.</div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                                        <?php foreach ($listPeminatan as $m): ?>
                                            <div class="relative group">
                                                <label class="block text-xs font-bold text-slate-500 mb-1 truncate"><?= $m['nama_mapel'] ?></label>
                                                <div class="relative">
                                                    <input type="number" step="0.01" name="mapel_<?= $m['id'] ?>" class="<?= $inputClass ?> h-10 px-3 bg-indigo-50/30 pr-8 font-medium text-indigo-700" value="<?= $existingNilai[$m['id']] ?? '' ?>" placeholder="0">
                                                    <a href="?siswa=<?= $siswaId ?>&semester=<?= $semester ?>&remove_mapel=<?= $m['id'] ?>" onclick="return confirm('Hapus?')" class="absolute right-2 top-1/2 -translate-y-1/2 text-rose-300 hover:text-rose-500 p-1 transition"><i class="fas fa-times-circle"></i></a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div id="modalAddMapel" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-lg font-bold mb-4 text-slate-800">Tambah Mapel</h3>
                    <form method="POST">
                        <input type="hidden" name="siswa_id" value="<?= $siswaId ?>"><input type="hidden" name="semester" value="<?= $semester ?>">
                        <div class="mb-6"><label class="block text-sm font-medium mb-1 text-slate-700">Pilih Mapel</label><select name="add_mapel_id" class="<?= $inputClass ?> h-10 px-3" required><option value="">-- Pilih --</option><?php foreach ($availableMapelPilihan as $am): ?><option value="<?= $am['id'] ?>"><?= $am['nama_mapel'] ?></option><?php endforeach; ?></select></div>
                        <div class="flex justify-end gap-3"><button type="button" onclick="document.getElementById('modalAddMapel').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg font-bold">Batal</button><button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold">Tambah</button></div>
                    </form>
                </div>
            </div>

            <div id="modalChangeRumpun" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-lg font-bold mb-4 text-slate-800">Ubah Rumpun</h3>
                    <form method="POST">
                        <input type="hidden" name="update_rumpun" value="1"><input type="hidden" name="siswa_id" value="<?= $siswaId ?>"><input type="hidden" name="semester" value="<?= $semester ?>">
                        <div class="mb-6"><label class="block text-sm font-medium mb-1 text-slate-700">Pilih Paket</label><select name="kode_rumpun_baru" class="<?= $inputClass ?> h-10 px-3" required><?php foreach ($listSemuaRumpun as $r): ?><option value="<?= $r['kode_rumpun'] ?>" <?= $kodeRumpunAktif == $r['kode_rumpun'] ? 'selected' : '' ?>>Rumpun <?= $r['kode_rumpun'] ?></option><?php endforeach; ?></select></div>
                        <div class="flex justify-end gap-3"><button type="button" onclick="document.getElementById('modalChangeRumpun').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg font-bold">Batal</button><button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold">Simpan</button></div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Siswa Input</p>
                    <p class="text-xl font-black text-slate-800 dark:text-white"><?= $stats['count'] ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Rata Global</p>
                    <p class="text-xl font-black text-slate-800 dark:text-white"><?= number_format($stats['avg'], 2) ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Tertinggi</p>
                    <p class="text-xl font-black text-slate-800 dark:text-white"><?= number_format($stats['max'], 2) ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col items-center justify-center text-center">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Terendah</p>
                    <p class="text-xl font-black text-slate-800 dark:text-white"><?= number_format($stats['min'], 2) ?></p>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <div class="flex flex-col xl:flex-row justify-between items-center gap-4 mb-6">
                    <h3 class="font-bold text-lg text-slate-800 dark:text-white flex items-center gap-2"><i class="fas fa-list text-amber-500"></i> Rekap Nilai</h3>
                    <form method="GET" class="flex flex-wrap gap-2 w-full xl:w-auto items-center">
                        <input type="text" name="f_nama" value="<?= sanitize($fNama) ?>" placeholder="Cari Nama..." class="<?= $inputClass ?> pl-3 !w-32 h-9">
                        <select name="f_kelas" class="<?= $inputClass ?> !w-auto h-9 px-3 cursor-pointer" onchange="this.form.submit()">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($listKelas as $k): ?><option value="<?= $k['kelas'] ?>" <?= $fKelas == $k['kelas'] ? 'selected' : '' ?>><?= $k['kelas'] ?></option><?php endforeach; ?>
                        </select>
                        <select name="f_sort" class="<?= $inputClass ?> !w-auto h-9 px-3 bg-blue-50 border-blue-200 text-blue-700 font-medium cursor-pointer" onchange="this.form.submit()">
                            <option value="">Urutkan</option>
                            <option value="highest" <?= $fSort == 'highest' ? 'selected' : '' ?>>Tertinggi</option>
                            <option value="lowest" <?= $fSort == 'lowest' ? 'selected' : '' ?>>Terendah</option>
                        </select>
                        <?php if($fKelas || $fNama || $fSort): ?><a href="nilai-rapor.php" class="px-3 py-2 bg-slate-100 rounded-lg text-sm hover:bg-slate-200 transition">Reset</a><?php endif; ?>
                    </form>
                </div>
                
                <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead class="bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 font-bold sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-center w-10">#</th>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3 text-center">Kelas</th>
                                <th class="px-4 py-3 text-center bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border-r border-blue-100">Rata2</th>
                                <?php for($i=1; $i<=6; $i++): ?><th class="px-2 py-3 text-center text-xs text-slate-400">S<?= $i ?></th><?php endfor; ?>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-800">
                            <?php if (empty($rekapSiswa)): ?>
                                <tr><td colspan="10" class="px-6 py-12 text-center text-slate-400 italic">Data tidak ditemukan.</td></tr>
                            <?php else: ?>
                                <?php $no = ($page - 1) * $limit + 1; foreach ($rekapSiswa as $row): ?>
                                    <tr class="hover:bg-blue-50 dark:hover:bg-slate-700/50 transition-colors">
                                        <td class="px-4 py-3 text-center text-slate-400 text-xs font-bold"><?= $no++ ?></td>
                                        <td class="px-4 py-3 font-medium dark:text-white"><?= sanitize($row['nama']) ?></td>
                                        <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700 font-medium"><?= $row['kelas'] ?? '-' ?></span></td>
                                        <td class="px-4 py-3 text-center font-bold text-blue-700 dark:text-blue-300 bg-blue-50/50 dark:bg-blue-900/10 border-r border-blue-50"><?= $row['skor_akhir'] > 0 ? number_format($row['skor_akhir'], 2) : '-' ?></td>
                                        <?php for($i=1; $i<=6; $i++): $val = $row['smt'.$i]; $color = $val >= 90 ? 'text-emerald-600 font-bold' : ($val > 0 ? 'text-slate-600' : 'text-slate-300'); ?>
                                            <td class="px-2 py-3 text-center text-xs <?= $color ?>"><?= $val > 0 ? number_format($val, 1) : '-' ?></td>
                                        <?php endfor; ?>
                                        <td class="px-4 py-3 text-right"><a href="?siswa=<?= $row['id'] ?>" class="text-blue-600 hover:text-blue-800 transition"><i class="fas fa-pen-square text-xl"></i></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <div class="flex justify-between items-center mt-6 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <div class="text-sm text-slate-500">Hal <b><?= $page ?></b> dari <?= $totalPages ?></div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&f_kelas=<?= urlencode($fKelas) ?>&f_nama=<?= urlencode($fNama) ?>&f_sort=<?= urlencode($fSort) ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm hover:bg-slate-50 font-medium">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&f_kelas=<?= urlencode($fKelas) ?>&f_nama=<?= urlencode($fNama) ?>&f_sort=<?= urlencode($fSort) ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 font-medium shadow-sm">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
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
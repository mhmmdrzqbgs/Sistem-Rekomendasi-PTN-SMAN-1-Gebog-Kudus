<?php
/**
 * Siswa - Profil Saya
 * Updated: UI Fixed (Select Style Uniformity, No Black Outline)
 */
$pageTitle = 'Profil Saya';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
require_once __DIR__ . '/../templates/header-siswa.php';

$siswaId = $_SESSION['siswa_id'];

// --- 1. HANDLE UPDATE PROFIL ---
if (isPost()) {
    function formatMinat($prodi, $ptnId, $db) {
        if (empty($prodi) || empty($ptnId)) return '';
        $ptnInfo = $db->queryOne("SELECT singkatan FROM ptn WHERE id = ?", [$ptnId]);
        return $ptnInfo ? "$prodi - " . $ptnInfo['singkatan'] : '';
    }

    $saintek1 = formatMinat(post('saintek_prodi_1'), post('saintek_ptn_1'), $db);
    $saintek2 = formatMinat(post('saintek_prodi_2'), post('saintek_ptn_2'), $db);
    $minatSaintek = trim(($saintek1 ? $saintek1 : '') . ($saintek1 && $saintek2 ? ' | ' : '') . ($saintek2 ? $saintek2 : ''));

    $soshum1 = formatMinat(post('soshum_prodi_1'), post('soshum_ptn_1'), $db);
    $soshum2 = formatMinat(post('soshum_prodi_2'), post('soshum_ptn_2'), $db);
    $minatSoshum = trim(($soshum1 ? $soshum1 : '') . ($soshum1 && $soshum2 ? ' | ' : '') . ($soshum2 ? $soshum2 : ''));

    try {
        $db->execute("UPDATE siswa_profile SET minat_saintek = ?, minat_soshum = ? WHERE id = ?", [$minatSaintek, $minatSoshum, $siswaId]);
        setFlash('message', 'Rencana studi berhasil diperbarui.', 'success');
    } catch (Exception $e) {
        setFlash('message', 'Gagal update: ' . $e->getMessage(), 'error');
    }
    redirect('profil.php');
}

// --- 2. GET DATA PROFIL ---
$profileData = $db->queryOne("SELECT * FROM siswa_profile JOIN users ON siswa_profile.user_id = users.id WHERE siswa_profile.id = ?", [$siswaId]);

// --- 3. PREPARE DATA FOR JS ---
$rawData = $db->query("
    SELECT p.nama as prodi, ptn.id as ptn_id, ptn.nama as ptn_nama, ptn.singkatan 
    FROM prodi p 
    JOIN ptn ON p.ptn_id = ptn.id 
    ORDER BY p.nama ASC, ptn.singkatan ASC
");

$keywordsSaintek = ['Teknik', 'Kedokteran', 'MIPA', 'Farmasi', 'Komputer', 'Sistem', 'Informatika', 'Biologi', 'Fisika', 'Kimia', 'Matematika', 'Statistika', 'Gizi', 'Keperawatan', 'Arsitektur', 'Agroteknologi', 'Peternakan', 'Kehutanan', 'Sains', 'Rekayasa'];
$keywordsSoshum = ['Hukum', 'Ekonomi', 'Manajemen', 'Akuntansi', 'Psikologi', 'Sastra', 'Bahasa', 'Hubungan', 'Komunikasi', 'Sosiologi', 'Politik', 'Administrasi', 'Sejarah', 'Antropologi', 'Pariwisata', 'Seni', 'Desain', 'Pendidikan', 'Sosial'];

$saintekList = [];
$soshumList = [];
$ptnByProdi = [];

foreach ($rawData as $d) {
    $ptnByProdi[$d['prodi']][] = [
        'id' => $d['ptn_id'],
        'text' => $d['singkatan'] . ' - ' . $d['ptn_nama']
    ];

    $isSaintek = false;
    foreach ($keywordsSaintek as $k) {
        if (stripos($d['prodi'], $k) !== false) {
            $saintekList[$d['prodi']] = 1;
            $isSaintek = true; break;
        }
    }
    if (!$isSaintek) {
        $isSoshum = false;
        foreach ($keywordsSoshum as $k) {
            if (stripos($d['prodi'], $k) !== false) {
                $soshumList[$d['prodi']] = 1;
                $isSoshum = true; break;
            }
        }
        if (!$isSoshum) $soshumList[$d['prodi']] = 1; 
    }
}

$saintekList = array_keys($saintekList); 
$soshumList = array_keys($soshumList);   
$jsonPtnByProdi = json_encode($ptnByProdi);

// --- 4. PARSE SAVED DATA ---
function parseChoices($str, $db) {
    $choices = explode('|', $str);
    $result = [['prodi' => '', 'ptn_id' => ''], ['prodi' => '', 'ptn_id' => '']];
    foreach ($choices as $index => $val) {
        if ($index > 1) break;
        $parts = explode(' - ', trim($val));
        if (count($parts) >= 2) {
            $ptnName = array_pop($parts);
            $prodiName = implode(' - ', $parts);
            $ptnId = $db->queryOne("SELECT id FROM ptn WHERE singkatan = ?", [$ptnName])['id'] ?? '';
            $result[$index] = ['prodi' => $prodiName, 'ptn_id' => $ptnId];
        }
    }
    return $result;
}
$savedSaintek = parseChoices($profileData['minat_saintek'], $db);
$savedSoshum = parseChoices($profileData['minat_soshum'], $db);

// --- 5. MAPEL LOGIC ---
$mapelDisplay = [];
if (!empty($profileData['kode_rumpun'])) {
    $rumpunRaw = $db->query("SELECT mm.nama_mapel FROM paket_rumpun pr JOIN master_mapel mm ON pr.master_mapel_id = mm.id WHERE pr.kode_rumpun = ? ORDER BY mm.nama_mapel ASC", [$profileData['kode_rumpun']]);
    foreach ($rumpunRaw as $r) { $mapelDisplay['Paket Rumpun (' . $profileData['kode_rumpun'] . ')'][] = $r['nama_mapel']; }
}
$manualRaw = $db->query("SELECT mm.nama_mapel, smp.tingkat FROM siswa_mapel_pilihan smp JOIN master_mapel mm ON smp.master_mapel_id = mm.id WHERE smp.siswa_id = ? ORDER BY smp.tingkat ASC, mm.nama_mapel ASC", [$siswaId]);
foreach ($manualRaw as $m) {
    $isDuplicate = false;
    if (isset($mapelDisplay['Paket Rumpun (' . $profileData['kode_rumpun'] . ')'])) {
        if (in_array($m['nama_mapel'], $mapelDisplay['Paket Rumpun (' . $profileData['kode_rumpun'] . ')'])) $isDuplicate = true;
    }
    if (!$isDuplicate) $mapelDisplay["Pilihan Tambahan (Kelas {$m['tingkat']})"][] = $m['nama_mapel'];
}

// STYLE CSS (Removed black ring)
$inputClass = "w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent sm:text-sm py-2.5 px-3 transition-all placeholder-slate-400";
?>

<div class="max-w-6xl mx-auto space-y-6">

    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-700 p-8 text-white shadow-lg">
        <div class="absolute right-0 top-0 -mr-16 -mt-16 h-64 w-64 rounded-full bg-white opacity-10 blur-3xl"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center gap-6">
            <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl border-4 border-white/30 bg-white/20 text-4xl font-bold backdrop-blur-sm shadow-xl">
                <?= strtoupper(substr($profileData['nama'], 0, 1)) ?>
            </div>
            <div class="text-center md:text-left">
                <h2 class="text-3xl font-bold tracking-tight"><?= sanitize($profileData['nama']) ?></h2>
                <div class="text-blue-100 mt-2 flex flex-wrap justify-center md:justify-start gap-3 text-sm">
                    <span class="bg-white/10 px-3 py-1 rounded-full"><i class="fas fa-id-card opacity-70 mr-1"></i> <?= $profileData['username'] ?></span>
                    <span class="bg-white/10 px-3 py-1 rounded-full"><i class="fas fa-layer-group opacity-70 mr-1"></i> Kelas <?= $profileData['kelas'] ?? '-' ?></span>
                    <?php if(!empty($profileData['kode_rumpun'])): ?>
                        <span class="bg-indigo-500/30 px-3 py-1 rounded-full border border-indigo-400/30">
                            <i class="fas fa-cubes opacity-70 mr-1"></i> Rumpun <?= $profileData['kode_rumpun'] ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <div class="flex items-center gap-2 mb-6 border-b border-slate-100 dark:border-slate-700 pb-4">
                    <i class="fas fa-compass text-blue-500 text-xl"></i>
                    <h3 class="font-bold text-lg text-slate-800 dark:text-white">Rencana Studi (4 Pilihan)</h3>
                </div>

                <form method="POST" class="space-y-10">
                    
                    <div>
                        <h4 class="font-bold text-slate-800 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2 mb-4 text-sm uppercase tracking-wide flex items-center gap-2">
                            <span class="w-1 h-4 bg-indigo-500 rounded-full inline-block"></span> Kelompok Saintek
                        </h4>
                        
                        <div class="space-y-6">
                            <div>
                                <h5 class="text-xs font-bold text-slate-400 mb-2">Pilihan 1 (Prioritas)</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1">Program Studi</label>
                                        <select name="saintek_prodi_1" id="saintek_prodi_1" class="<?= $inputClass ?>" onchange="loadPtn(this.value, 'saintek_ptn_1')">
                                            <option value="">-- Pilih Prodi Saintek --</option>
                                            <?php foreach($saintekList as $p): ?>
                                                <option value="<?= sanitize($p) ?>" <?= $savedSaintek[0]['prodi'] == $p ? 'selected' : '' ?>><?= sanitize($p) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1">Perguruan Tinggi</label>
                                        <select name="saintek_ptn_1" id="saintek_ptn_1" class="<?= $inputClass ?>" data-selected="<?= $savedSaintek[0]['ptn_id'] ?>">
                                            <option value="">-- Pilih PTN --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h5 class="text-xs font-bold text-slate-400 mb-2">Pilihan 2 (Cadangan)</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1">Program Studi</label>
                                        <select name="saintek_prodi_2" id="saintek_prodi_2" class="<?= $inputClass ?>" onchange="loadPtn(this.value, 'saintek_ptn_2')">
                                            <option value="">-- Pilih Prodi Saintek --</option>
                                            <?php foreach($saintekList as $p): ?>
                                                <option value="<?= sanitize($p) ?>" <?= $savedSaintek[1]['prodi'] == $p ? 'selected' : '' ?>><?= sanitize($p) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1">Perguruan Tinggi</label>
                                        <select name="saintek_ptn_2" id="saintek_ptn_2" class="<?= $inputClass ?>" data-selected="<?= $savedSaintek[1]['ptn_id'] ?>">
                                            <option value="">-- Pilih PTN --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-bold text-slate-800 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2 mb-4 text-sm uppercase tracking-wide flex items-center gap-2">
                             <span class="w-1 h-4 bg-amber-500 rounded-full inline-block"></span> Kelompok Soshum
                        </h4>
                        
                        <div class="space-y-6">
                            <div>
                                <h5 class="text-xs font-bold text-slate-400 mb-2">Pilihan 1 (Prioritas)</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1">Program Studi</label>
                                        <select name="soshum_prodi_1" id="soshum_prodi_1" class="<?= $inputClass ?>" onchange="loadPtn(this.value, 'soshum_ptn_1')">
                                            <option value="">-- Pilih Prodi Soshum --</option>
                                            <?php foreach($soshumList as $p): ?>
                                                <option value="<?= sanitize($p) ?>" <?= $savedSoshum[0]['prodi'] == $p ? 'selected' : '' ?>><?= sanitize($p) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1">Perguruan Tinggi</label>
                                        <select name="soshum_ptn_1" id="soshum_ptn_1" class="<?= $inputClass ?>" data-selected="<?= $savedSoshum[0]['ptn_id'] ?>">
                                            <option value="">-- Pilih PTN --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h5 class="text-xs font-bold text-slate-400 mb-2">Pilihan 2 (Cadangan)</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1">Program Studi</label>
                                        <select name="soshum_prodi_2" id="soshum_prodi_2" class="<?= $inputClass ?>" onchange="loadPtn(this.value, 'soshum_ptn_2')">
                                            <option value="">-- Pilih Prodi Soshum --</option>
                                            <?php foreach($soshumList as $p): ?>
                                                <option value="<?= sanitize($p) ?>" <?= $savedSoshum[1]['prodi'] == $p ? 'selected' : '' ?>><?= sanitize($p) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1">Perguruan Tinggi</label>
                                        <select name="soshum_ptn_2" id="soshum_ptn_2" class="<?= $inputClass ?>" data-selected="<?= $savedSoshum[1]['ptn_id'] ?>">
                                            <option value="">-- Pilih PTN --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-700">
                        <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg shadow-lg shadow-blue-500/30 transition-all active:scale-95 flex items-center gap-2">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center gap-2">
                    <i class="fas fa-book-open text-emerald-500"></i>
                    <h3 class="font-bold text-slate-700">Mapel Pilihan Anda</h3>
                </div>
                <div class="p-5">
                    <?php if (empty($mapelDisplay)): ?>
                        <div class="text-center py-6 text-sm text-slate-500">Belum ada mapel pilihan.</div>
                    <?php else: ?>
                        <div class="space-y-5">
                            <?php foreach ($mapelDisplay as $label => $mapels): ?>
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2"><?= $label ?></span>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($mapels as $m): ?>
                                            <span class="px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                                                <?= $m ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Data Relasi Prodi -> PTN dari PHP
    const ptnByProdi = <?= $jsonPtnByProdi ?>;

    /**
     * Fungsi untuk mengisi dropdown PTN berdasarkan Prodi yang dipilih
     */
    function loadPtn(prodiName, targetSelectId) {
        const ptnSelect = document.getElementById(targetSelectId);
        const savedValue = ptnSelect.getAttribute('data-selected'); 
        
        ptnSelect.innerHTML = '<option value="">-- Pilih PTN --</option>';
        ptnSelect.disabled = true;

        if (prodiName && ptnByProdi[prodiName]) {
            ptnByProdi[prodiName].forEach(function(ptn) {
                const option = document.createElement('option');
                option.value = ptn.id;
                option.text = ptn.text;
                
                if (ptn.id == savedValue) {
                    option.selected = true;
                }
                
                ptnSelect.appendChild(option);
            });
            ptnSelect.disabled = false;
        } else {
            const option = document.createElement('option');
            option.text = "Tidak ada data kampus";
            ptnSelect.appendChild(option);
        }
    }

    // Jalankan saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        loadPtn(document.getElementById('saintek_prodi_1').value, 'saintek_ptn_1');
        loadPtn(document.getElementById('saintek_prodi_2').value, 'saintek_ptn_2');
        loadPtn(document.getElementById('soshum_prodi_1').value, 'soshum_ptn_1');
        loadPtn(document.getElementById('soshum_prodi_2').value, 'soshum_ptn_2');
    });
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
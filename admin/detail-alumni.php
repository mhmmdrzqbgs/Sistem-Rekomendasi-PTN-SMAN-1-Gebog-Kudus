<?php
/**
 * Admin - Detail Siswa / Alumni
 * Updated: Fix Mapel Pilihan (Gabungan Rumpun + Manual), Hapus Fitur Cetak
 */
$pageTitle = 'Detail Siswa'; 
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
$id = get('id');

if (!$id) {
    redirect('siswa.php');
}

// 1. AMBIL DATA PROFIL
$siswa = $db->queryOne("
    SELECT sp.*, u.nama, u.username, u.is_active, u.created_at as join_date
    FROM siswa_profile sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.id = ?
", [$id]);

if (!$siswa) {
    echo "Data tidak ditemukan.";
    exit;
}

// Tentukan Mode (Siswa/Alumni)
$isAlumni = ($siswa['status'] === 'Lulus');
$pageTitle = $isAlumni ? 'Profil Alumni' : 'Detail Siswa';
$backLink = $isAlumni ? 'daftar-alumni.php' : 'siswa.php';
$backLabel = $isAlumni ? 'Kembali ke Daftar Alumni' : 'Kembali ke Data Siswa';

// 2. AMBIL DATA RAPOR
$nilaiRapor = $db->query("SELECT * FROM nilai_rapor WHERE siswa_id = ? ORDER BY semester ASC", [$id]);
$avgRapor = $db->queryOne("SELECT AVG(rata_rata) as val FROM nilai_rapor WHERE siswa_id = ?", [$id]);

// 3. AMBIL DATA TRYOUT
$historyTryout = $db->query("SELECT * FROM nilai_tryout WHERE siswa_id = ? ORDER BY tanggal_tes DESC", [$id]);

// 4. AMBIL REKOMENDASI
$rekomendasi = $db->query("
    SELECT r.*, 
           p.nama as prodi_nama, 
           pt.singkatan as ptn_nama,
           p.passing_grade,
           p.daya_tampung_snbp,
           p.daya_tampung_snbt
    FROM rekomendasi r
    JOIN prodi p ON r.prodi_id = p.id
    JOIN ptn pt ON p.ptn_id = pt.id
    WHERE r.siswa_id = ?
    ORDER BY r.ranking ASC
", [$id]);

// 5. LOGIKA BARU: AMBIL MAPEL PILIHAN (GABUNGAN RUMPUN + MANUAL)
$mapelDisplay = [];

// A. Ambil dari Paket Rumpun (Jika siswa punya kode_rumpun)
// Ini yang sering terlewat, padahal mapel utama ada disini
if (!empty($siswa['kode_rumpun'])) {
    $rumpunRaw = $db->query("
        SELECT mm.nama_mapel 
        FROM paket_rumpun pr
        JOIN master_mapel mm ON pr.master_mapel_id = mm.id
        WHERE pr.kode_rumpun = ?
        ORDER BY mm.nama_mapel ASC
    ", [$siswa['kode_rumpun']]);
    
    foreach ($rumpunRaw as $r) {
        // Kita masukkan ke kategori "Paket Rumpun"
        $mapelDisplay['Paket Rumpun (' . $siswa['kode_rumpun'] . ')'][] = $r['nama_mapel'];
    }
}

// B. Ambil dari Pilihan Manual (Lintas Minat / Tambahan)
$manualRaw = $db->query("
    SELECT mm.nama_mapel, smp.tingkat
    FROM siswa_mapel_pilihan smp 
    JOIN master_mapel mm ON smp.master_mapel_id = mm.id 
    WHERE smp.siswa_id = ?
    ORDER BY smp.tingkat ASC, mm.nama_mapel ASC
", [$id]);

foreach ($manualRaw as $m) {
    // Hindari duplikasi jika mapel sudah ada di rumpun (Opsional, tapi bagus untuk kerapian)
    $isDuplicate = false;
    if (isset($mapelDisplay['Paket Rumpun (' . $siswa['kode_rumpun'] . ')'])) {
        if (in_array($m['nama_mapel'], $mapelDisplay['Paket Rumpun (' . $siswa['kode_rumpun'] . ')'])) {
            $isDuplicate = true;
        }
    }

    if (!$isDuplicate) {
        $tingkatLabel = "Pilihan Tambahan (Kelas " . $m['tingkat'] . ")";
        $mapelDisplay[$tingkatLabel][] = $m['nama_mapel'];
    }
}

require_once __DIR__ . '/../templates/header-admin.php';
?>

<div class="max-w-7xl mx-auto space-y-6">

    <div class="flex justify-between items-center print:hidden">
        <a href="<?= $backLink ?>" class="inline-flex items-center px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i> <?= $backLabel ?>
        </a>
        <div class="flex gap-2">
            <a href="siswa-edit.php?id=<?= $id ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors">
                <i class="fas fa-edit mr-2"></i> Edit Data
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1 space-y-6">
            
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden relative">
                <div class="h-32 bg-gradient-to-r <?= $isAlumni ? 'from-purple-600 to-indigo-700' : 'from-sky-500 to-blue-600' ?> relative">
                    <div class="absolute inset-0 bg-white/10 pattern-dots"></div>
                </div>
                
                <div class="px-6 pb-6 relative">
                    <div class="flex justify-between items-end -mt-12 mb-4">
                        <div class="w-24 h-24 rounded-2xl border-4 border-white dark:border-slate-800 bg-slate-100 dark:bg-slate-700 flex items-center justify-center shadow-lg overflow-hidden">
                            <span class="text-4xl font-bold <?= $isAlumni ? 'text-purple-600' : 'text-sky-600' ?>">
                                <?= strtoupper(substr($siswa['nama'], 0, 1)) ?>
                            </span>
                        </div>
                        
                        <?php 
                        if ($isAlumni) {
                            $statusClass = 'bg-purple-100 text-purple-700 border-purple-200';
                            $statusLabel = 'ALUMNI ' . ($siswa['tahun_lulus'] ?? '');
                        } else {
                            $statusClass = ($siswa['is_active'] ?? 1) 
                                ? 'bg-emerald-50 text-emerald-600 border-emerald-200' 
                                : 'bg-red-50 text-red-600 border-red-200';
                            $statusLabel = ($siswa['is_active'] ?? 1) ? 'SISWA AKTIF' : 'NON-AKTIF';
                        }
                        ?>
                        <span class="mb-1 px-3 py-1 rounded-full text-xs font-bold border <?= $statusClass ?>">
                            <?= $statusLabel ?>
                        </span>
                    </div>

                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white leading-tight mb-1"><?= sanitize($siswa['nama']) ?></h2>
                        <div class="flex items-center text-sm text-slate-500 dark:text-slate-400 mb-6">
                            <i class="fas fa-id-card mr-2 opacity-70"></i> NISN: <?= sanitize($siswa['username']) ?>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="p-3 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-slate-100 dark:border-slate-700 text-center">
                                <div class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide font-semibold">Kelas</div>
                                <div class="text-lg font-bold text-slate-800 dark:text-white mt-1"><?= $siswa['kelas'] ?? '-' ?></div>
                            </div>
                            <div class="p-3 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-slate-100 dark:border-slate-700 text-center">
                                <div class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide font-semibold">Rapor Avg</div>
                                <div class="text-lg font-bold text-slate-800 dark:text-white mt-1">
                                    <?= $avgRapor['val'] ? number_format($avgRapor['val'], 2) : '-' ?>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                    <i class="fas fa-school text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Asal Sekolah</p>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-white"><?= sanitize($siswa['asal_sekolah'] ?? '-') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-heart text-rose-500"></i> Minat & Rencana Studi
                </h3>
                
                <div class="space-y-4">
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl border border-indigo-100 dark:border-indigo-800">
                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase block mb-1">Minat Saintek</span>
                        <p class="text-sm text-slate-700 dark:text-slate-200">
                            <?= !empty($siswa['minat_saintek']) ? nl2br(sanitize($siswa['minat_saintek'])) : '<span class="text-slate-400 italic">Belum diisi</span>' ?>
                        </p>
                    </div>

                    <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-800">
                        <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase block mb-1">Minat Soshum</span>
                        <p class="text-sm text-slate-700 dark:text-slate-200">
                            <?= !empty($siswa['minat_soshum']) ? nl2br(sanitize($siswa['minat_soshum'])) : '<span class="text-slate-400 italic">Belum diisi</span>' ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-layer-group text-purple-500"></i> Mapel Pilihan (Fase F)
                </h3>

                <?php if (empty($mapelDisplay)): ?>
                    <p class="text-sm text-slate-500 italic">Belum ada mapel pilihan (Rumpun/Manual) yang terdata.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($mapelDisplay as $label => $mapels): ?>
                            <div>
                                <span class="text-xs font-bold text-slate-400 uppercase block mb-2"><?= $label ?></span>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($mapels as $m): ?>
                                        <span class="px-2.5 py-1 rounded-md text-xs font-medium bg-purple-50 text-purple-700 border border-purple-100 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800">
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

        <div class="lg:col-span-2 space-y-8">
            
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-trophy text-amber-500"></i> Hasil Rekomendasi Sistem
                    </h3>
                </div>
                
                <?php if (empty($rekomendasi)): ?>
                    <div class="p-8 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700 mb-3">
                            <i class="fas fa-lightbulb text-slate-400"></i>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm">Belum ada rekomendasi yang dihasilkan.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 uppercase text-xs font-semibold">
                                <tr>
                                    <th class="px-6 py-3 w-16 text-center">Rank</th>
                                    <th class="px-6 py-3">Program Studi</th>
                                    <th class="px-6 py-3 text-center">Jalur</th>
                                    <th class="px-6 py-3 text-center">Peluang</th>
                                    <th class="px-6 py-3 text-center">Skor Akhir</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php foreach ($rekomendasi as $rek): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                        <td class="px-6 py-3 text-center">
                                            <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-600 flex items-center justify-center font-bold text-xs text-slate-700 dark:text-slate-300 mx-auto">
                                                <?= $rek['ranking'] ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="font-bold text-slate-900 dark:text-white"><?= sanitize($rek['prodi_nama']) ?></div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                <?= $rek['ptn_nama'] ?> • PG: <?= number_format($rek['passing_grade']) ?>
                                            </div>
                                            <div class="mt-1">
                                                <?php 
                                                    if (strpos($rek['alasan'], 'TARGET') !== false) 
                                                        echo '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700">TARGET</span>';
                                                    elseif (strpos($rek['alasan'], 'AMAN') !== false) 
                                                        echo '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">AMAN</span>';
                                                    elseif (strpos($rek['alasan'], 'TANTANGAN') !== false) 
                                                        echo '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700">TANTANGAN</span>';
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                                                <?= $rek['jalur'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <?php 
                                            $cls = match($rek['peluang']) {
                                                'Tinggi' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-400',
                                                'Sedang' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-400',
                                                default => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400'
                                            };
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?= $cls ?>">
                                                <?= $rek['peluang'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                            <?= number_format($rek['skor'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-book text-emerald-500"></i> Rekap Nilai Rapor
                    </h3>
                </div>
                <div class="p-6">
                    <?php if (empty($nilaiRapor)): ?>
                        <p class="text-sm text-slate-500 italic text-center">Belum ada data rapor.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <?php 
                                    $nilai = null;
                                    foreach ($nilaiRapor as $nr) { if ($nr['semester'] == $i) $nilai = $nr['rata_rata']; }
                                    $bgClass = ($nilai > 0) 
                                        ? 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400' 
                                        : 'bg-slate-50 border-slate-200 text-slate-400 dark:bg-slate-800/50 dark:border-slate-700 dark:text-slate-600';
                                ?>
                                <div class="rounded-lg p-3 text-center border <?= $bgClass ?>">
                                    <div class="text-[10px] uppercase font-bold tracking-wider opacity-70 mb-1">Sem <?= $i ?></div>
                                    <div class="text-xl font-bold">
                                        <?= ($nilai > 0) ? $nilai : '-' ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-chart-line text-indigo-500"></i> Riwayat Tryout SNBT
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <?php if (empty($historyTryout)): ?>
                        <p class="text-sm text-slate-500 italic text-center py-6">Belum ada nilai tryout.</p>
                    <?php else: ?>
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-indigo-50 dark:bg-indigo-900/20 text-indigo-800 dark:text-indigo-300 font-bold uppercase">
                                <tr>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-2 py-3 text-center">PU</th>
                                    <th class="px-2 py-3 text-center">PPU</th>
                                    <th class="px-2 py-3 text-center">PBM</th>
                                    <th class="px-2 py-3 text-center">PK</th>
                                    <th class="px-2 py-3 text-center">Indo</th>
                                    <th class="px-2 py-3 text-center">Ing</th>
                                    <th class="px-2 py-3 text-center">PM</th>
                                    <th class="px-4 py-3 text-center bg-indigo-100 dark:bg-indigo-900/40">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-slate-700 dark:text-slate-300">
                                <?php foreach ($historyTryout as $h): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                        <td class="px-4 py-3 whitespace-nowrap font-medium">
                                            <?= date('d/m/y', strtotime($h['tanggal_tes'])) ?> 
                                        </td>
                                        <td class="px-2 py-3 text-center"><?= $h['pu'] ?></td>
                                        <td class="px-2 py-3 text-center"><?= $h['ppu'] ?></td>
                                        <td class="px-2 py-3 text-center"><?= $h['pbm'] ?></td>
                                        <td class="px-2 py-3 text-center"><?= $h['pk'] ?></td>
                                        <td class="px-2 py-3 text-center"><?= $h['lit_indo'] ?></td>
                                        <td class="px-2 py-3 text-center"><?= $h['lit_ing'] ?></td>
                                        <td class="px-2 py-3 text-center"><?= $h['pm'] ?></td>
                                        <td class="px-4 py-3 text-center font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/10">
                                            <?= number_format($h['skor_total'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
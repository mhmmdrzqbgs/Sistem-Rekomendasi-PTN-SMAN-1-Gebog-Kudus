<?php
/**
 * Admin - Laporan Rekomendasi (Smart Filter View)
 * Fitur:
 * 1. Default View: Menampilkan Top 1 Saintek & Soshum.
 * 2. Filtered View: Menampilkan SEMUA rekomendasi yang sesuai filter (bisa > 1 per siswa).
 * 3. Statistik item rekomendasi.
 */
$pageTitle = 'Laporan Rekomendasi';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

$filter = get('filter'); // tinggi, sedang, rendah

// 1. QUERY UTAMA: MENDAPATKAN DAFTAR SISWA
// Kita menggunakan EXISTS agar siswa yang tampil adalah siswa yang relevan dengan filter
$sql = "
    SELECT sp.*, u.nama, u.username,
           -- Default Data (Top 1) - Tetap diambil untuk fallback
           (SELECT p.nama FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Saintek%' OR p.rumpun LIKE '%Teknik%' OR p.rumpun LIKE '%Sains%') ORDER BY r.ranking ASC LIMIT 1) as saintek_prodi,
           (SELECT pt.singkatan FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id JOIN ptn pt ON p.ptn_id = pt.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Saintek%' OR p.rumpun LIKE '%Teknik%' OR p.rumpun LIKE '%Sains%') ORDER BY r.ranking ASC LIMIT 1) as saintek_ptn,
           (SELECT r.peluang FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Saintek%' OR p.rumpun LIKE '%Teknik%' OR p.rumpun LIKE '%Sains%') ORDER BY r.ranking ASC LIMIT 1) as saintek_peluang,

           (SELECT p.nama FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Soshum%' OR p.rumpun LIKE '%Sosial%' OR p.rumpun LIKE '%Hum%' OR p.nama LIKE '%Manajemen%') ORDER BY r.ranking ASC LIMIT 1) as soshum_prodi,
           (SELECT pt.singkatan FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id JOIN ptn pt ON p.ptn_id = pt.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Soshum%' OR p.rumpun LIKE '%Sosial%' OR p.rumpun LIKE '%Hum%' OR p.nama LIKE '%Manajemen%') ORDER BY r.ranking ASC LIMIT 1) as soshum_ptn,
           (SELECT r.peluang FROM rekomendasi r JOIN prodi p ON r.prodi_id = p.id WHERE r.siswa_id = sp.id AND (p.rumpun LIKE '%Soshum%' OR p.rumpun LIKE '%Sosial%' OR p.rumpun LIKE '%Hum%' OR p.nama LIKE '%Manajemen%') ORDER BY r.ranking ASC LIMIT 1) as soshum_peluang

    FROM siswa_profile sp
    JOIN users u ON sp.user_id = u.id
";

// Logic Filter: Hanya ambil siswa yang MEMILIKI rekomendasi sesuai filter
$params = [];
if ($filter === 'tinggi') {
    $sql .= " WHERE EXISTS (SELECT 1 FROM rekomendasi r WHERE r.siswa_id = sp.id AND r.peluang = 'Tinggi') ";
} elseif ($filter === 'sedang') {
    $sql .= " WHERE EXISTS (SELECT 1 FROM rekomendasi r WHERE r.siswa_id = sp.id AND r.peluang = 'Sedang') ";
} elseif ($filter === 'rendah') {
    $sql .= " WHERE EXISTS (SELECT 1 FROM rekomendasi r WHERE r.siswa_id = sp.id AND r.peluang = 'Rendah') ";
}

$sql .= " ORDER BY sp.kelas ASC, u.nama ASC";
$siswaList = $db->query($sql, $params);


// 2. STATISTIK (Total Item)
$totalSiswa = $db->count('siswa_profile');
$stats = $db->queryOne("
    SELECT 
        SUM(CASE WHEN peluang = 'Tinggi' THEN 1 ELSE 0 END) as tinggi,
        SUM(CASE WHEN peluang = 'Sedang' THEN 1 ELSE 0 END) as sedang,
        SUM(CASE WHEN peluang = 'Rendah' THEN 1 ELSE 0 END) as rendah
    FROM rekomendasi
");
$rekomTinggi = $stats['tinggi'] ?? 0;
$rekomSedang = $stats['sedang'] ?? 0;
$rekomRendah = $stats['rendah'] ?? 0;


// 3. TREND JURUSAN
$trendJurusan = $db->query("
    SELECT p.nama, p.rumpun, pt.singkatan, COUNT(r.id) as cnt
    FROM rekomendasi r
    JOIN prodi p ON r.prodi_id = p.id
    JOIN ptn pt ON p.ptn_id = pt.id
    WHERE r.ranking <= 3
    GROUP BY p.id, p.nama, p.rumpun, pt.singkatan
    ORDER BY cnt DESC
    LIMIT 5
");

require_once __DIR__ . '/../templates/header-admin.php';
?>

<div class="space-y-8">

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <a href="laporan.php" class="group bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700 hover:shadow-lg transition-all duration-300 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-blue-50 dark:bg-blue-900/20 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-2xl shadow-sm">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Total Siswa</p>
                    <h3 class="text-3xl font-bold text-slate-800 dark:text-white mt-1"><?= $totalSiswa ?></h3>
                </div>
            </div>
        </a>

        <a href="?filter=tinggi" class="group bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700 hover:shadow-lg transition-all duration-300 relative overflow-hidden <?= $filter === 'tinggi' ? 'ring-2 ring-emerald-500' : '' ?>">
            <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-50 dark:bg-emerald-900/20 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl shadow-sm">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Rekom. Tinggi</p>
                    <h3 class="text-3xl font-bold text-slate-800 dark:text-white mt-1"><?= $rekomTinggi ?></h3>
                    <p class="text-[10px] text-emerald-600">Total Item Rekomendasi</p>
                </div>
            </div>
        </a>

        <a href="?filter=sedang" class="group bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700 hover:shadow-lg transition-all duration-300 relative overflow-hidden <?= $filter === 'sedang' ? 'ring-2 ring-amber-500' : '' ?>">
            <div class="absolute top-0 right-0 w-24 h-24 bg-amber-50 dark:bg-amber-900/20 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl shadow-sm">
                    <i class="fas fa-minus-circle"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Rekom. Sedang</p>
                    <h3 class="text-3xl font-bold text-slate-800 dark:text-white mt-1"><?= $rekomSedang ?></h3>
                    <p class="text-[10px] text-amber-600">Total Item Rekomendasi</p>
                </div>
            </div>
        </a>

        <a href="?filter=rendah" class="group bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700 hover:shadow-lg transition-all duration-300 relative overflow-hidden <?= $filter === 'rendah' ? 'ring-2 ring-red-500' : '' ?>">
            <div class="absolute top-0 right-0 w-24 h-24 bg-red-50 dark:bg-red-900/20 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 flex items-center justify-center text-2xl shadow-sm">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Perlu Bimbingan</p>
                    <h3 class="text-3xl font-bold text-slate-800 dark:text-white mt-1"><?= $rekomRendah ?></h3>
                    <p class="text-[10px] text-red-600">Total Item Rekomendasi</p>
                </div>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1 space-y-8">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-filter text-indigo-500"></i> Filter Tabel Siswa
                    </h3>
                </div>
                <div class="p-4 space-y-2">
                    <?php 
                    $menuItems = [
                        ['label' => 'Semua Siswa', 'val' => '', 'icon' => 'fa-list', 'color' => 'slate'],
                        ['label' => 'Punya Rekom. Tinggi', 'val' => 'tinggi', 'icon' => 'fa-check', 'color' => 'emerald'],
                        ['label' => 'Punya Rekom. Sedang', 'val' => 'sedang', 'icon' => 'fa-minus', 'color' => 'amber'],
                        ['label' => 'Punya Rekom. Rendah', 'val' => 'rendah', 'icon' => 'fa-exclamation', 'color' => 'red'],
                    ];
                    
                    foreach($menuItems as $item):
                        $isActive = ($filter === $item['val']);
                        $bgClass = $isActive 
                            ? "bg-{$item['color']}-50 text-{$item['color']}-700 border-{$item['color']}-200 dark:bg-{$item['color']}-900/30 dark:text-{$item['color']}-300 dark:border-{$item['color']}-800" 
                            : "hover:bg-slate-50 text-slate-600 dark:text-slate-400 dark:hover:bg-slate-700/50 border-transparent";
                    ?>
                        <a href="?filter=<?= $item['val'] ?>" class="flex items-center justify-between px-4 py-3 rounded-xl border transition-all duration-200 <?= $bgClass ?>">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 shadow-sm text-xs">
                                    <i class="fas <?= $item['icon'] ?>"></i>
                                </span>
                                <span class="font-medium text-sm"><?= $item['label'] ?></span>
                            </div>
                            <?php if($isActive): ?>
                                <i class="fas fa-chevron-right text-xs opacity-50"></i>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-chart-line text-rose-500"></i> Trend Jurusan (Top 5)
                    </h3>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if (empty($trendJurusan)): ?>
                        <div class="p-8 text-center text-slate-500 text-sm">Belum ada data.</div>
                    <?php else: ?>
                        <?php foreach ($trendJurusan as $index => $t): ?>
                            <div class="px-6 py-3 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-bold text-slate-400 w-4 text-center">#<?= $index + 1 ?></span>
                                    <div>
                                        <p class="text-sm font-bold text-slate-700 dark:text-slate-200 line-clamp-1"><?= sanitize($t['nama']) ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= $t['singkatan'] ?></p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    <?= $t['cnt'] ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                
                <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-800">
                    <div>
                        <h3 class="font-bold text-lg text-slate-800 dark:text-white">Daftar Siswa</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            <?= $filter ? 'Filter: Ada Rekomendasi ' . ucfirst($filter) : 'Menampilkan semua data siswa' ?>
                        </p>
                    </div>
                    
                    <a href="print-laporan.php?filter=<?= $filter ?>" target="_blank" class="inline-flex items-center justify-center px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-xl shadow-lg shadow-slate-300/50 dark:shadow-none transition-all transform hover:-translate-y-0.5">
                        <i class="fas fa-print mr-2"></i> Cetak PDF
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <?php if (empty($siswaList)): ?>
                        <div class="p-12 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-700 mb-4 text-slate-400 text-2xl">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Tidak Ada Data</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Belum ada siswa yang sesuai kriteria filter ini.</p>
                        </div>
                    <?php else: ?>
                        <table class="w-full text-left text-sm border-collapse">
                            <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 uppercase text-xs font-semibold tracking-wider border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="px-6 py-4">Siswa</th>
                                    <?php if($filter): ?>
                                        <th class="px-6 py-4 w-2/5">Rekomendasi (Saintek)</th>
                                        <th class="px-6 py-4 w-2/5">Rekomendasi (Soshum)</th>
                                    <?php else: ?>
                                        <th class="px-6 py-4 w-1/3">Top Saintek</th>
                                        <th class="px-6 py-4 w-1/3">Top Soshum</th>
                                    <?php endif; ?>
                                    <th class="px-6 py-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php foreach ($siswaList as $s): 
                                    
                                    // LOGIC: Jika ada Filter, ambil SEMUA data sesuai filter
                                    $saintekContent = [];
                                    $soshumContent = [];

                                    if ($filter) {
                                        $filterCap = ucfirst($filter);
                                        $allRekom = $db->query("
                                            SELECT p.nama, p.rumpun, pt.singkatan, r.peluang 
                                            FROM rekomendasi r 
                                            JOIN prodi p ON r.prodi_id = p.id 
                                            JOIN ptn pt ON p.ptn_id = pt.id
                                            WHERE r.siswa_id = ? AND r.peluang = ?
                                            ORDER BY r.ranking ASC
                                        ", [$s['id'], $filterCap]);

                                        foreach ($allRekom as $r) {
                                            $style = match($r['peluang']) {
                                                'Tinggi' => 'text-emerald-600 bg-emerald-50 border-emerald-100',
                                                'Sedang' => 'text-amber-600 bg-amber-50 border-amber-100',
                                                default => 'text-red-600 bg-red-50 border-red-100'
                                            };
                                            $item = "
                                                <div class='mb-2 pb-2 border-b border-slate-100 dark:border-slate-700 last:border-0 last:mb-0 last:pb-0'>
                                                    <div class='font-medium text-slate-700 dark:text-slate-200 text-xs'>{$r['nama']}</div>
                                                    <div class='text-[10px] text-slate-500'>{$r['singkatan']}</div>
                                                    <span class='inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold border $style'>{$r['peluang']}</span>
                                                </div>";

                                            if (stripos($r['rumpun'], 'Saintek') !== false || stripos($r['rumpun'], 'Teknik') !== false || stripos($r['rumpun'], 'Sains') !== false) {
                                                $saintekContent[] = $item;
                                            } else {
                                                $soshumContent[] = $item;
                                            }
                                        }
                                    } else {
                                        // Default View (Top 1) logic handled below in HTML
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group">
                                        
                                        <td class="px-6 py-4 align-top">
                                            <div class="font-bold text-slate-800 dark:text-white mb-0.5"><?= sanitize($s['nama']) ?></div>
                                            <div class="text-xs text-slate-500 flex items-center gap-1 mb-1">
                                                <i class="fas fa-id-card opacity-50"></i> <?= sanitize($s['username']) ?>
                                            </div>
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                                <?= $s['kelas'] ?? '-' ?>
                                            </span>
                                        </td>

                                        <td class="px-6 py-4 align-top">
                                            <?php if ($filter): ?>
                                                <?php echo empty($saintekContent) ? '<span class="text-slate-400 text-xs italic">- Tidak ada -</span>' : implode('', $saintekContent); ?>
                                            <?php else: ?>
                                                <?php if($s['saintek_prodi']): 
                                                    $style = match($s['saintek_peluang']) {
                                                        'Tinggi' => 'text-emerald-600 bg-emerald-50 border-emerald-100',
                                                        'Sedang' => 'text-amber-600 bg-amber-50 border-amber-100',
                                                        default => 'text-red-600 bg-red-50 border-red-100'
                                                    };
                                                ?>
                                                    <div class="font-medium text-slate-700 dark:text-slate-200 text-sm"><?= sanitize($s['saintek_prodi']) ?></div>
                                                    <div class="text-xs text-slate-500 mt-0.5"><?= $s['saintek_ptn'] ?></div>
                                                    <span class="inline-block mt-2 px-2 py-0.5 rounded text-[10px] font-bold border <?= $style ?>">
                                                        <?= $s['saintek_peluang'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-slate-400 text-xs italic">- Tidak ada -</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 align-top">
                                            <?php if ($filter): ?>
                                                <?php echo empty($soshumContent) ? '<span class="text-slate-400 text-xs italic">- Tidak ada -</span>' : implode('', $soshumContent); ?>
                                            <?php else: ?>
                                                <?php if($s['soshum_prodi']): 
                                                    $style = match($s['soshum_peluang']) {
                                                        'Tinggi' => 'text-emerald-600 bg-emerald-50 border-emerald-100',
                                                        'Sedang' => 'text-amber-600 bg-amber-50 border-amber-100',
                                                        default => 'text-red-600 bg-red-50 border-red-100'
                                                    };
                                                ?>
                                                    <div class="font-medium text-slate-700 dark:text-slate-200 text-sm"><?= sanitize($s['soshum_prodi']) ?></div>
                                                    <div class="text-xs text-slate-500 mt-0.5"><?= $s['soshum_ptn'] ?></div>
                                                    <span class="inline-block mt-2 px-2 py-0.5 rounded text-[10px] font-bold border <?= $style ?>">
                                                        <?= $s['soshum_peluang'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-slate-400 text-xs italic">- Tidak ada -</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 text-right align-middle">
                                            <a href="detail-siswa.php?id=<?= $s['id'] ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-500 hover:text-blue-600 hover:border-blue-300 hover:bg-blue-50 transition-all shadow-sm" title="Lihat Detail">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($siswaList)): ?>
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-xs text-slate-500 dark:text-slate-400 flex justify-between items-center">
                    <span>Menampilkan <?= count($siswaList) ?> data</span>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
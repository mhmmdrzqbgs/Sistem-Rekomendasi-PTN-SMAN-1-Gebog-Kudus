<?php
/**
 * Siswa Dashboard
 * Updated: 
 * 1. Top Rekomendasi = Paling AMAN (Surplus Nilai Terbesar).
 * 2. Total Rekomendasi = Hitung hanya yang peluang 'Tinggi'.
 * 3. Top 20 List = Diurutkan berdasarkan Safety Margin (Paling Aman Dulu).
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';

$db = Database::getInstance();
require_once __DIR__ . '/../templates/header-siswa.php';

$siswaId = $_SESSION['siswa_id'];

// 1. Ambil Data Profil
if (!isset($profile) || !$profile) {
    $profile = $db->queryOne("SELECT * FROM siswa_profile WHERE id = ?", [$siswaId]);
}

// 2. Get Nilai Rapor Summary
$nilaiRapor = $db->query("SELECT * FROM nilai_rapor WHERE siswa_id = ? ORDER BY semester ASC", [$siswaId]);

// 3. Get Nilai Tryout Terbaru
$tryoutTerbaru = $db->queryOne("SELECT * FROM nilai_tryout WHERE siswa_id = ? ORDER BY tanggal_tes DESC LIMIT 1", [$siswaId]);

// 4. Hitung Statistik
// [LOGIC BARU] Total Rekomendasi = Hanya hitung yang peluangnya 'Tinggi' (Sangat Aman)
$jumlahRekomAman = $db->count('rekomendasi', "siswa_id = ? AND peluang = 'Tinggi'", [$siswaId]);
$avgRapor = $db->queryOne("SELECT AVG(rata_rata) as avg FROM nilai_rapor WHERE siswa_id = ?", [$siswaId]);

// 5. GET TOP REKOMENDASI (LOGIC: PALING AMAN / SURPLUS NILAI TERBESAR)
// Kita urutkan berdasarkan (skor_siswa - passing_grade) DESC agar yang paling aman muncul paling atas.

// A. Top Saintek Paling Aman
$topSaintek = $db->queryOne("
    SELECT r.*, p.nama as prodi_nama, pt.singkatan as ptn_singkatan,
           (r.skor - p.passing_grade) as safety_margin
    FROM rekomendasi r
    JOIN prodi p ON r.prodi_id = p.id
    JOIN ptn pt ON p.ptn_id = pt.id
    WHERE r.siswa_id = ? 
    AND (p.rumpun LIKE '%Saintek%' OR p.rumpun LIKE '%Teknik%' OR p.rumpun LIKE '%Sains%' OR p.nama LIKE '%Teknik%')
    ORDER BY safety_margin DESC
    LIMIT 1
", [$siswaId]);

// B. Top Soshum Paling Aman
$topSoshum = $db->queryOne("
    SELECT r.*, p.nama as prodi_nama, pt.singkatan as ptn_singkatan,
           (r.skor - p.passing_grade) as safety_margin
    FROM rekomendasi r
    JOIN prodi p ON r.prodi_id = p.id
    JOIN ptn pt ON p.ptn_id = pt.id
    WHERE r.siswa_id = ? 
    AND (p.rumpun LIKE '%Soshum%' OR p.rumpun LIKE '%Sosial%' OR p.rumpun LIKE '%Hum%' OR p.nama LIKE '%Hukum%' OR p.nama LIKE '%Manajemen%')
    ORDER BY safety_margin DESC
    LIMIT 1
", [$siswaId]);

// 6. GET TOP 20 LIST (SORT BY SAFETY MARGIN - PALING AMAN DULU)
$top20List = $db->query("
    SELECT r.*, p.nama as prodi_nama, pt.singkatan as ptn_singkatan, p.daya_tampung_snbp, p.daya_tampung_snbt,
           (r.skor - p.passing_grade) as safety_margin
    FROM rekomendasi r
    JOIN prodi p ON r.prodi_id = p.id
    JOIN ptn pt ON p.ptn_id = pt.id
    WHERE r.siswa_id = ?
    ORDER BY safety_margin DESC
    LIMIT 20
", [$siswaId]);
?>

<div class="space-y-6">

    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-600 p-8 text-white shadow-lg dark:from-sky-700 dark:to-indigo-800">
        <div class="absolute top-0 right-0 -mt-10 -mr-10 h-40 w-40 rounded-full bg-white opacity-10 blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -mb-10 -ml-10 h-32 w-32 rounded-full bg-white opacity-10 blur-2xl"></div>
        <div class="relative z-10 flex flex-col justify-between gap-6 md:flex-row md:items-center">
            <div>
                <h2 class="mb-2 text-3xl font-bold tracking-tight">Halo, <?= sanitize($profile['nama'] ?? $user['nama']) ?>! 👋</h2>
                <p class="text-sky-100">
                    <?= !empty($profile['asal_sekolah']) ? sanitize($profile['asal_sekolah']) : 'Selamat Datang' ?>
                    <span class="mx-2 opacity-60">•</span>
                    <?= !empty($profile['kode_rumpun']) ? 'Rumpun ' . $profile['kode_rumpun'] : 'Mapel Pilihan' ?>
                </p>
            </div>
            <div class="min-w-[140px] rounded-xl border border-white/20 bg-white/10 p-4 text-center backdrop-blur-sm">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-sky-100">Kelas</p>
                <div class="text-2xl font-bold"><?= $profile['kelas'] ?? '-' ?></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-xl text-blue-600"><i class="fas fa-file-alt"></i></div>
            <div><h3 class="text-xl font-bold text-slate-800 dark:text-white"><?= count($nilaiRapor) ?>/6</h3><p class="text-xs text-slate-500">Semester Rapor</p></div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-xl text-emerald-600"><i class="fas fa-chart-line"></i></div>
            <div><h3 class="text-xl font-bold text-slate-800 dark:text-white"><?= $avgRapor['avg'] ? number_format($avgRapor['avg'], 2) : '-' ?></h3><p class="text-xs text-slate-500">Rata-rata Rapor</p></div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-xl text-amber-600"><i class="fas fa-clipboard-check"></i></div>
            <div><h3 class="text-xl font-bold text-slate-800 dark:text-white"><?= $tryoutTerbaru ? number_format($tryoutTerbaru['skor_total'], 2) : '-' ?></h3><p class="text-xs text-slate-500">Skor Try Out</p></div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-violet-50 text-xl text-violet-600"><i class="fas fa-shield-alt"></i></div>
            <div>
                <h3 class="text-xl font-bold text-slate-800 dark:text-white"><?= $jumlahRekomAman ?></h3>
                <p class="text-xs text-slate-500">Peluang Aman</p>
            </div>
        </div>
    </div>

    <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
        <i class="fas fa-thumbs-up text-blue-500"></i> Rekomendasi Paling Aman
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <div class="relative bg-gradient-to-r from-teal-500 to-emerald-600 rounded-xl p-6 text-white shadow-lg overflow-hidden group transition hover:shadow-xl">
            <div class="absolute right-0 top-0 p-4 opacity-10 transform translate-x-4 -translate-y-4 transition-transform group-hover:scale-110"><i class="fas fa-microscope text-9xl"></i></div>
            <div class="relative z-10">
                <div class="flex items-center gap-2 mb-3">
                    <span class="bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border border-white/10"><i class="fas fa-flask mr-1"></i> Saintek Teraman</span>
                </div>
                <?php if($topSaintek): ?>
                    <h3 class="text-2xl font-bold mb-1 leading-tight"><?= $topSaintek['prodi_nama'] ?></h3>
                    <p class="text-emerald-50 font-medium mb-4 text-sm opacity-90"><?= $topSaintek['ptn_singkatan'] ?></p>
                    <div class="inline-flex items-center gap-3">
                        <div class="bg-white text-emerald-700 px-3 py-1.5 rounded-lg font-bold shadow-md text-sm">
                            Peluang: <?= $topSaintek['peluang'] ?>
                        </div>
                        <span class="text-xs font-medium bg-emerald-700/30 px-2 py-1 rounded border border-emerald-400/30">
                            Surplus Nilai: +<?= number_format($topSaintek['safety_margin'], 1) ?>
                        </span>
                    </div>
                <?php else: ?>
                    <p class="italic opacity-80 text-sm py-4">Belum ada rekomendasi Saintek yang aman.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="relative bg-gradient-to-r from-rose-500 to-orange-500 rounded-xl p-6 text-white shadow-lg overflow-hidden group transition hover:shadow-xl">
            <div class="absolute right-0 top-0 p-4 opacity-10 transform translate-x-4 -translate-y-4 transition-transform group-hover:scale-110"><i class="fas fa-balance-scale text-9xl"></i></div>
            <div class="relative z-10">
                <div class="flex items-center gap-2 mb-3">
                    <span class="bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border border-white/10"><i class="fas fa-users mr-1"></i> Soshum Teraman</span>
                </div>
                <?php if($topSoshum): ?>
                    <h3 class="text-2xl font-bold mb-1 leading-tight"><?= $topSoshum['prodi_nama'] ?></h3>
                    <p class="text-rose-50 font-medium mb-4 text-sm opacity-90"><?= $topSoshum['ptn_singkatan'] ?></p>
                    <div class="inline-flex items-center gap-3">
                        <div class="bg-white text-rose-700 px-3 py-1.5 rounded-lg font-bold shadow-md text-sm">
                            Peluang: <?= $topSoshum['peluang'] ?>
                        </div>
                        <span class="text-xs font-medium bg-rose-700/30 px-2 py-1 rounded border border-rose-400/30">
                            Surplus Nilai: +<?= number_format($topSoshum['safety_margin'], 1) ?>
                        </span>
                    </div>
                <?php else: ?>
                    <p class="italic opacity-80 text-sm py-4">Belum ada rekomendasi Soshum yang aman.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <div class="lg:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <h3 class="flex items-center gap-2 font-bold text-slate-800 dark:text-white"><i class="fas fa-chart-area text-blue-500"></i> Tren Nilai Rapor</h3>
            </div>
            <div class="p-6">
                <div class="h-64 w-full"><canvas id="nilaiChart"></canvas></div>
            </div>
        </div>

        <div class="lg:col-span-1 flex flex-col gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 dark:text-white mb-4">Aksi Cepat</h3>
                <div class="space-y-3">
                    <a href="nilai-tryout.php" class="block w-full rounded-lg bg-indigo-600 px-4 py-3 text-white text-center font-medium hover:bg-indigo-700 shadow transition">Input Nilai Try Out</a>
                    <a href="rekomendasi.php" class="block w-full rounded-lg bg-emerald-600 px-4 py-3 text-white text-center font-medium hover:bg-emerald-700 shadow transition">Lihat Semua Rekomendasi</a>
                    <a href="analisis.php" class="block w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-slate-700 text-center font-medium hover:bg-slate-50 transition">Cek Analisis Rapor</a>
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
            <h3 class="flex items-center gap-2 font-bold text-slate-800 dark:text-white"><i class="fas fa-list-ol text-amber-500"></i> Top 20 Rekomendasi Paling Aman</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 text-xs uppercase text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                    <tr>
                        <th class="px-6 py-3">Rank</th>
                        <th class="px-6 py-3">Prodi</th>
                        <th class="px-6 py-3">PTN</th>
                        <th class="px-6 py-3 text-center">Jalur</th>
                        <th class="px-6 py-3 text-center">Peluang</th>
                        <th class="px-6 py-3 text-center">Surplus</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if(empty($top20List)): ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400 italic">Belum ada data rekomendasi.</td></tr>
                    <?php else: ?>
                        <?php foreach($top20List as $idx => $row): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-6 py-4 font-bold text-slate-800 dark:text-white">#<?= $idx + 1 ?></td>
                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-white"><?= $row['prodi_nama'] ?></td>
                            <td class="px-6 py-4"><?= $row['ptn_singkatan'] ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="rounded px-2 py-1 text-xs font-bold <?= $row['jalur'] == 'SNBP' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' ?>"><?= $row['jalur'] ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php 
                                    $cls = match($row['peluang']) { 'Tinggi'=>'text-emerald-600 bg-emerald-50', 'Sedang'=>'text-amber-600 bg-amber-50', default=>'text-rose-600 bg-rose-50' };
                                ?>
                                <span class="rounded px-2 py-1 text-xs font-bold <?= $cls ?>"><?= $row['peluang'] ?></span>
                            </td>
                            <td class="px-6 py-4 text-center text-xs font-mono font-medium text-slate-500">
                                +<?= number_format($row['safety_margin'], 1) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($nilaiRapor)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('nilaiChart');
            if (ctx) {
                const isDark = document.documentElement.classList.contains('dark');
                const textColor = isDark ? '#94a3b8' : '#64748b';
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(array_map(fn($n) => 'Sem ' . $n['semester'], $nilaiRapor)) ?>,
                        datasets: [{
                            label: 'Nilai Rata-rata',
                            data: <?= json_encode(array_map(fn($n) => $n['rata_rata'], $nilaiRapor)) ?>,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: '#6366f1',
                            pointRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { min: 60, max: 100, ticks: { color: textColor } },
                            x: { ticks: { color: textColor }, grid: { display: false } }
                        }
                    }
                });
            }
        });
    </script>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
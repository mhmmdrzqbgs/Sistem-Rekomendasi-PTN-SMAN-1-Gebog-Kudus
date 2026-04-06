<?php
/**
 * Siswa - Analisis Nilai & Saran Peningkatan
 * Updated: Filter Mapel Aktif + Cetak PDF
 */
$pageTitle = 'Analisis Nilai';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/RecommendationEngineV2.php';

$db = Database::getInstance();
$engine = new RecommendationEngineV2();

require_once __DIR__ . '/../templates/header-siswa.php';

$siswaId = $_SESSION['siswa_id'];

// 1. Get Nilai Rapor (Untuk Chart)
$nilaiRapor = $db->query(
    "SELECT * FROM nilai_rapor WHERE siswa_id = ? ORDER BY semester ASC",
    [$siswaId]
);

// 2. Get Nilai Tryout (Untuk Chart)
$tryoutList = $db->query(
    "SELECT * FROM nilai_tryout WHERE siswa_id = ? ORDER BY tanggal_tes ASC",
    [$siswaId]
);

// 3. Get Improvement Suggestions
if (method_exists($engine, 'getImprovementSuggestions')) {
    $suggestions = $engine->getImprovementSuggestions($siswaId);
} else {
    $suggestions = [];
}

// 4. Calculate Mapel Averages (Hanya Mapel yang diambil siswa)
// Kita filter mapel yang memiliki nilai > 0 di tabel detail
$mapelStats = $db->query("
    SELECT mm.nama_mapel, AVG(nrd.nilai) as avg_nilai
    FROM nilai_rapor nr
    JOIN nilai_rapor_detail nrd ON nr.id = nrd.nilai_rapor_id
    JOIN master_mapel mm ON nrd.master_mapel_id = mm.id
    WHERE nr.siswa_id = ? AND nrd.nilai > 0
    GROUP BY mm.id, mm.nama_mapel
    ORDER BY avg_nilai DESC
", [$siswaId]);

$mapelAverages = [];
foreach ($mapelStats as $ms) {
    $mapelAverages[] = [
        'name' => $ms['nama_mapel'],
        'avg'  => floatval($ms['avg_nilai'])
    ];
}
?>

<div class="max-w-6xl mx-auto space-y-6 print:hidden">

    <div class="flex justify-between items-center bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <h2 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
            <i class="fas fa-chart-pie text-blue-500"></i> Analisis Performa Akademik
        </h2>
        
        <?php if (!empty($nilaiRapor) || !empty($tryoutList)): ?>
            <a href="cetak-analisis.php" target="_blank" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium shadow-md transition-colors flex items-center gap-2">
                <i class="fas fa-print"></i> Cetak Laporan
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($nilaiRapor) && empty($tryoutList)): ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-700 mb-4">
                <i class="fas fa-chart-bar text-2xl text-slate-400"></i>
            </div>
            <h3 class="text-lg font-medium text-slate-900 dark:text-white">Belum Ada Data Nilai</h3>
            <p class="text-slate-500 dark:text-slate-400 mt-1 mb-6">Silakan input nilai rapor atau tryout terlebih dahulu untuk melihat analisis.</p>
            <div class="flex justify-center gap-3">
                <a href="nilai-rapor.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-sm">Input Rapor</a>
                <a href="nilai-tryout.php" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow-sm">Input Try Out</a>
            </div>
        </div>
    <?php else: ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-50 dark:bg-emerald-900/10 rounded-bl-full -mr-4 -mt-4"></div>
                
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100 dark:border-slate-700 relative">
                    <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg text-emerald-600 dark:text-emerald-400">
                        <i class="fas fa-trophy text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white">Mata Pelajaran Unggul</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">3 Mapel dengan rata-rata tertinggi</p>
                    </div>
                </div>

                <div class="space-y-5 relative">
                    <?php if (empty($mapelAverages)): ?>
                        <p class="text-slate-500 text-center text-sm italic">Belum ada data nilai rapor.</p>
                    <?php else: ?>
                        <?php $topMapels = array_slice($mapelAverages, 0, 3); ?>
                        <?php foreach ($topMapels as $index => $mapel): ?>
                            <div class="flex items-center gap-4 group">
                                <div class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 font-bold text-xs">
                                    #<?= $index + 1 ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200"><?= $mapel['name'] ?></span>
                                        <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($mapel['avg'], 1) ?></span>
                                    </div>
                                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                                        <div class="bg-emerald-500 h-2 rounded-full transition-all duration-1000" style="width: <?= $mapel['avg'] ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-amber-50 dark:bg-amber-900/10 rounded-bl-full -mr-4 -mt-4"></div>

                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100 dark:border-slate-700 relative">
                    <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg text-amber-600 dark:text-amber-400">
                        <i class="fas fa-arrow-up text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white">Perlu Ditingkatkan</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">3 Mapel dengan rata-rata terendah</p>
                    </div>
                </div>

                <div class="space-y-5 relative">
                    <?php if (empty($mapelAverages)): ?>
                        <p class="text-slate-500 text-center text-sm italic">Belum ada data nilai rapor.</p>
                    <?php else: ?>
                        <?php $bottomMapels = array_slice(array_reverse($mapelAverages), 0, 3); ?>
                        <?php foreach ($bottomMapels as $mapel): ?>
                            <div class="flex items-center gap-4 group">
                                <div class="w-8 h-8 flex items-center justify-center rounded-full bg-amber-50 text-amber-600 font-bold text-xs dark:bg-amber-900/20 dark:text-amber-400">
                                    <i class="fas fa-exclamation"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200"><?= $mapel['name'] ?></span>
                                        <span class="text-sm font-bold text-amber-600 dark:text-amber-400"><?= number_format($mapel['avg'], 1) ?></span>
                                    </div>
                                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                                        <div class="bg-amber-500 h-2 rounded-full transition-all duration-1000" style="width: <?= $mapel['avg'] ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <?php if (!empty($suggestions)): ?>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-lightbulb text-yellow-500"></i> Saran Akademik
                    </h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($suggestions as $sug): ?>
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl p-4 transition hover:shadow-md">
                            <div class="flex items-start gap-3">
                                <div class="mt-1">
                                    <i class="fas fa-info-circle text-blue-500"></i>
                                </div>
                                <div>
                                    <?php if (is_string($sug)): ?>
                                        <p class="text-xs text-blue-700 dark:text-blue-200 leading-relaxed"><?= $sug ?></p>
                                    <?php else: ?>
                                        <span class="text-xs font-bold uppercase tracking-wider text-blue-400 mb-1 block"><?= $sug['type'] ?? 'Info' ?></span>
                                        <h4 class="text-sm font-bold text-blue-800 dark:text-blue-300 mb-1"><?= $sug['subject'] ?? '' ?></h4>
                                        <p class="text-xs text-blue-700 dark:text-blue-200 leading-relaxed"><?= $sug['message'] ?? '' ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <?php if (!empty($nilaiRapor)): ?>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="font-bold text-slate-800 dark:text-white mb-6 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center text-sm"><i class="fas fa-chart-area"></i></div>
                    Tren Rapor (Rata-rata)
                </h3>
                <div class="h-64 relative">
                    <canvas id="raporTrendChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($tryoutList)): ?>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="font-bold text-slate-800 dark:text-white mb-6 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm"><i class="fas fa-chart-line"></i></div>
                    Tren Try Out SNBT
                </h3>
                <div class="h-64 relative">
                    <canvas id="tryoutTrendChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const isDark = document.documentElement.classList.contains('dark');
                const gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';
                const textColor = isDark ? '#94a3b8' : '#64748b';
                
                const commonOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 10 } } },
                        x: { grid: { display: false }, ticks: { color: textColor, font: { size: 10 } } }
                    },
                    plugins: { legend: { display: false } },
                    interaction: { mode: 'index', intersect: false }
                };

                // Rapor Chart
                <?php if (!empty($nilaiRapor)): ?>
                const raporCtx = document.getElementById('raporTrendChart').getContext('2d');
                new Chart(raporCtx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(array_map(fn($n) => 'Sem ' . $n['semester'], $nilaiRapor)) ?>,
                        datasets: [{
                            label: 'Rata-rata',
                            data: <?= json_encode(array_map(fn($n) => $n['rata_rata'], $nilaiRapor)) ?>,
                            borderColor: '#3b82f6',
                            backgroundColor: (context) => {
                                const ctx = context.chart.ctx;
                                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                                gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
                                gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');
                                return gradient;
                            },
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: '#3b82f6',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        ...commonOptions,
                        scales: {
                            ...commonOptions.scales,
                            y: { ...commonOptions.scales.y, min: 60, max: 100 }
                        }
                    }
                });
                <?php endif; ?>

                // Tryout Chart
                <?php if (!empty($tryoutList)): ?>
                const tryoutCtx = document.getElementById('tryoutTrendChart').getContext('2d');
                new Chart(tryoutCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_map(fn($t) => date('d M', strtotime($t['tanggal_tes'])), $tryoutList)) ?>,
                        datasets: [{
                            label: 'Skor Total',
                            data: <?= json_encode(array_map(fn($t) => $t['skor_total'], $tryoutList)) ?>,
                            backgroundColor: '#6366f1',
                            borderRadius: 6,
                            barThickness: 20
                        }]
                    },
                    options: {
                        ...commonOptions,
                        scales: {
                            ...commonOptions.scales,
                            y: { ...commonOptions.scales.y, beginAtZero: false, min: 0, max: 1000 }
                        }
                    }
                });
                <?php endif; ?>
            });
        </script>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
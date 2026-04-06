<?php
/**
 * Halaman Detail Rekomendasi Siswa
 */
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/RecommendationEngine.php';

$db = Database::getInstance();
$engine = new RecommendationEngine();

// Get siswa ID
$siswaId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$siswaId) {
    redirect('siswa.php');
}

// Get siswa data
$siswa = $db->queryOne("SELECT * FROM siswa WHERE id = ?", [$siswaId]);

if (!$siswa) {
    setFlash('message', 'Data siswa tidak ditemukan', 'error');
    redirect('siswa.php');
}

$pageTitle = 'Rekomendasi - ' . $siswa['nama'];

// Handle generate
if (isset($_GET['generate'])) {
    $result = $engine->generateForStudent($siswaId);
    if ($result) {
        setFlash('message', "Berhasil generate $result rekomendasi", 'success');
    }
    redirect("rekomendasi.php?id=$siswaId");
}

// Get recommendations
$recommendations = $engine->getRecommendations($siswaId);

require_once __DIR__ . '/templates/header.php';
?>

<!-- Student Profile -->
<div class="card mb-3">
    <div class="card-body">
        <div class="flex flex-between flex-center">
            <div class="flex flex-gap flex-center">
                <div class="stat-icon primary" style="width: 64px; height: 64px; font-size: 1.75rem;">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                    <h2 style="font-size: 1.5rem; margin-bottom: 4px;">
                        <?= sanitize($siswa['nama']) ?>
                    </h2>
                    <p class="text-muted">
                        <?= sanitize($siswa['asal_sekolah'] ?: 'Sekolah tidak diketahui') ?> •
                        <span class="badge bg-primary"><?= $siswa['jurusan_sma'] ?></span>
                    </p>
                </div>
            </div>
            <div class="flex flex-gap">
                <a href="siswa.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <a href="?id=<?= $siswaId ?>&generate=1" class="btn btn-success">
                    <i class="fas fa-sync"></i> Refresh Rekomendasi
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Stats & Info -->
<div class="grid grid-2 mb-3">
    <!-- Nilai Akademik -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-bar text-primary"></i>
                Nilai Akademik
            </h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                <?php
                $subjects = [
                    'Matematika' => $siswa['nilai_mtk'],
                    'B. Inggris' => $siswa['nilai_bing'],
                    'B. Indonesia' => $siswa['nilai_bind'],
                    'IPA' => $siswa['nilai_ipa'],
                    'IPS' => $siswa['nilai_ips']
                ];
                foreach ($subjects as $name => $value):
                    ?>
                    <div>
                        <div class="flex flex-between mb-1">
                            <span class="text-muted"><?= $name ?></span>
                            <strong><?= formatNumber($value) ?></strong>
                        </div>
                        <div class="score-bar">
                            <div class="score-bar-track" style="flex: 1;">
                                <div class="score-bar-fill <?= $value >= 80 ? 'high' : ($value >= 60 ? 'medium' : 'low') ?>"
                                    style="width: <?= $value ?>%;"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div
                style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); text-align: center;">
                <p class="text-muted mb-1">Nilai Rata-rata</p>
                <h2 style="font-size: 2.5rem; color: var(--primary);">
                    <?= formatNumber($siswa['nilai_rata']) ?>
                </h2>
            </div>
        </div>
    </div>

    <!-- Info Tambahan -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-info-circle text-info"></i>
                Informasi Siswa
            </h3>
        </div>
        <div class="card-body">
            <table class="table" style="margin: 0;">
                <tr>
                    <td class="text-muted" style="width: 40%;">NISN</td>
                    <td><strong><?= sanitize($siswa['nisn'] ?: '-') ?></strong></td>
                </tr>
                <tr>
                    <td class="text-muted">Jenis Kelamin</td>
                    <td><strong><?= $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></strong></td>
                </tr>
                <tr>
                    <td class="text-muted">Jurusan SMA</td>
                    <td><span class="badge bg-primary"><?= $siswa['jurusan_sma'] ?></span></td>
                </tr>
                <tr>
                    <td class="text-muted">Rumpun</td>
                    <td><strong><?= getRumpunFromJurusan($siswa['jurusan_sma']) ?></strong></td>
                </tr>
                <tr>
                    <td class="text-muted">Minat Bidang</td>
                    <td><strong><?= sanitize($siswa['minat_bidang'] ?: 'Tidak diisi') ?></strong></td>
                </tr>
                <tr>
                    <td class="text-muted">Tanggal Input</td>
                    <td><strong><?= date('d M Y', strtotime($siswa['created_at'])) ?></strong></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- Recommendations -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-star text-warning"></i>
            Top <?= TOP_RECOMMENDATIONS ?> Rekomendasi Jurusan
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($recommendations)): ?>
            <div class="empty-state">
                <i class="fas fa-magic"></i>
                <h3>Belum Ada Rekomendasi</h3>
                <p>Klik tombol "Refresh Rekomendasi" untuk menghasilkan rekomendasi jurusan</p>
                <a href="?id=<?= $siswaId ?>&generate=1" class="btn btn-primary">
                    <i class="fas fa-magic"></i> Generate Sekarang
                </a>
            </div>
        <?php else: ?>

            <?php foreach ($recommendations as $rec): ?>
                <div class="recommendation-card">
                    <div class="recommendation-rank rank-<?= $rec['ranking'] ?>">
                        #<?= $rec['ranking'] ?>
                    </div>
                    <div class="recommendation-content">
                        <h3><?= sanitize($rec['nama_jurusan']) ?></h3>
                        <p class="ptn-name">
                            <?= sanitize($rec['nama_ptn']) ?> (<?= $rec['singkatan'] ?>) •
                            <?= $rec['kota'] ?> •
                            <span class="badge <?= getAkreditasiBadge($rec['ptn_akreditasi']) ?>">
                                Akreditasi <?= $rec['ptn_akreditasi'] ?>
                            </span>
                        </p>

                        <div class="flex flex-gap mt-2" style="flex-wrap: wrap;">
                            <span class="badge bg-secondary">
                                <i class="fas fa-layer-group"></i> <?= $rec['rumpun'] ?>
                            </span>
                            <span class="badge bg-secondary">
                                <i class="fas fa-chart-line"></i> PG: <?= formatNumber($rec['passing_grade']) ?>
                            </span>
                            <span class="badge bg-secondary">
                                <i class="fas fa-users"></i> Daya Tampung: <?= $rec['daya_tampung'] ?>
                            </span>
                            <span class="badge <?= getAkreditasiBadge($rec['jurusan_akreditasi']) ?>">
                                Prodi: <?= $rec['jurusan_akreditasi'] ?>
                            </span>
                        </div>

                        <?php if ($rec['prospek_kerja']): ?>
                            <p class="mt-2 text-muted" style="font-size: 0.9rem;">
                                <i class="fas fa-briefcase"></i>
                                <strong>Prospek Kerja:</strong> <?= sanitize($rec['prospek_kerja']) ?>
                            </p>
                        <?php endif; ?>

                        <div class="recommendation-scores">
                            <div class="score-item">
                                <label>Akademik</label>
                                <span class="<?= $rec['skor_akademik'] >= 70 ? 'text-success' : 'text-warning' ?>">
                                    <?= formatNumber($rec['skor_akademik']) ?>
                                </span>
                            </div>
                            <div class="score-item">
                                <label>Kesesuaian</label>
                                <span class="<?= $rec['skor_kesesuaian'] >= 70 ? 'text-success' : 'text-warning' ?>">
                                    <?= formatNumber($rec['skor_kesesuaian']) ?>
                                </span>
                            </div>
                            <div class="score-item">
                                <label>Minat</label>
                                <span class="<?= $rec['skor_minat'] >= 70 ? 'text-success' : 'text-warning' ?>">
                                    <?= formatNumber($rec['skor_minat']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center; padding: 16px;">
                        <div
                            style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">
                            Skor Total
                        </div>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary);">
                            <?= formatNumber($rec['skor_total']) ?>
                        </div>
                        <div class="score-bar mt-2" style="width: 80px;">
                            <div class="score-bar-track">
                                <div class="score-bar-fill <?= $rec['skor_total'] >= 70 ? 'high' : ($rec['skor_total'] >= 50 ? 'medium' : 'low') ?>"
                                    style="width: <?= $rec['skor_total'] ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Score Explanation -->
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-question-circle text-info"></i>
            Cara Perhitungan Skor
        </h3>
    </div>
    <div class="card-body">
        <div class="grid grid-3">
            <div>
                <h4 class="text-primary mb-2">Skor Akademik (50%)</h4>
                <p class="text-muted">Perbandingan nilai rata-rata siswa dengan passing grade jurusan. Semakin tinggi
                    nilai dibanding passing grade, semakin besar skor.</p>
            </div>
            <div>
                <h4 class="text-primary mb-2">Skor Kesesuaian (30%)</h4>
                <p class="text-muted">Kesesuaian jurusan SMA dengan rumpun jurusan kuliah (IPA → Saintek, IPS → Soshum).
                    Jurusan yang cocok mendapat skor maksimal.</p>
            </div>
            <div>
                <h4 class="text-primary mb-2">Skor Minat (20%)</h4>
                <p class="text-muted">Matching antara bidang minat siswa dengan karakteristik jurusan dan prospek kerja.
                    Semakin relevan, semakin tinggi skor.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
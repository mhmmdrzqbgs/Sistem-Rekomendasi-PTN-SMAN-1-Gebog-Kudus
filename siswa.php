<?php
/**
 * Halaman Data Siswa
 */
$pageTitle = 'Data Siswa';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/RecommendationEngine.php';

$db = Database::getInstance();
$engine = new RecommendationEngine();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $db->execute("DELETE FROM siswa WHERE id = ?", [$id]);
    setFlash('message', 'Data siswa berhasil dihapus', 'success');
    redirect('siswa.php');
}

// Handle generate recommendation
if (isset($_GET['generate']) && is_numeric($_GET['generate'])) {
    $id = (int) $_GET['generate'];
    $result = $engine->generateForStudent($id);
    if ($result) {
        setFlash('message', "Berhasil generate $result rekomendasi", 'success');
    } else {
        setFlash('message', 'Gagal generate rekomendasi', 'error');
    }
    redirect('siswa.php');
}

// Handle generate all
if (isset($_GET['generate_all'])) {
    $count = $engine->generateForAll();
    setFlash('message', "Berhasil generate rekomendasi untuk $count siswa", 'success');
    redirect('siswa.php');
}

// Filters
$search = get('search');
$jurusan = get('jurusan');
$sort = get('sort', 'created_at');
$order = get('order', 'DESC');

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(nama LIKE ? OR nisn LIKE ? OR asal_sekolah LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($jurusan) {
    $where[] = "jurusan_sma = ?";
    $params[] = $jurusan;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$validSorts = ['nama', 'asal_sekolah', 'jurusan_sma', 'nilai_rata', 'created_at'];
$sort = in_array($sort, $validSorts) ? $sort : 'created_at';
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

$siswaList = $db->query("SELECT * FROM siswa $whereClause ORDER BY $sort $order", $params);
$totalSiswa = count($siswaList);

require_once __DIR__ . '/templates/header.php';
?>

<!-- Action Bar -->
<div class="flex flex-between flex-center mb-3">
    <div class="flex flex-gap flex-center">
        <form method="GET" class="flex flex-gap">
            <div class="search-box" style="position: relative;">
                <i class="fas fa-search"
                    style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" name="search" value="<?= sanitize($search) ?>"
                    placeholder="Cari nama, NISN, sekolah..." class="form-control"
                    style="padding-left: 42px; width: 300px;">
            </div>
            <select name="jurusan" class="form-control" style="width: auto;">
                <option value="">Semua Jurusan</option>
                <option value="IPA" <?= $jurusan === 'IPA' ? 'selected' : '' ?>>IPA</option>
                <option value="IPS" <?= $jurusan === 'IPS' ? 'selected' : '' ?>>IPS</option>
                <option value="Bahasa" <?= $jurusan === 'Bahasa' ? 'selected' : '' ?>>Bahasa</option>
                <option value="Teknik" <?= $jurusan === 'Teknik' ? 'selected' : '' ?>>Teknik</option>
            </select>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
    </div>
    <div class="flex flex-gap">
        <a href="?generate_all=1" class="btn btn-success"
            onclick="return confirm('Generate rekomendasi untuk semua siswa?')">
            <i class="fas fa-magic"></i> Generate Semua
        </a>
        <a href="import.php" class="btn btn-primary">
            <i class="fas fa-file-import"></i> Import Excel
        </a>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-users text-primary"></i>
            Daftar Siswa (<?= $totalSiswa ?>)
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($siswaList)): ?>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <h3>Tidak Ada Data Siswa</h3>
                <p>Import data siswa dari file Excel atau coba filter lain</p>
                <a href="import.php" class="btn btn-primary">
                    <i class="fas fa-file-import"></i> Import Sekarang
                </a>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th data-sort="nama">Nama <i class="fas fa-sort"></i></th>
                            <th>NISN</th>
                            <th data-sort="asal_sekolah">Asal Sekolah <i class="fas fa-sort"></i></th>
                            <th data-sort="jurusan_sma">Jurusan <i class="fas fa-sort"></i></th>
                            <th data-sort="nilai_rata" data-type="number">Nilai Rata-rata <i class="fas fa-sort"></i></th>
                            <th>Minat</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($siswaList as $siswa): ?>
                            <tr>
                                <td>
                                    <strong><?= sanitize($siswa['nama']) ?></strong>
                                    <br>
                                    <small
                                        class="text-muted"><?= $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></small>
                                </td>
                                <td><?= sanitize($siswa['nisn'] ?: '-') ?></td>
                                <td><?= sanitize($siswa['asal_sekolah'] ?: '-') ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= $siswa['jurusan_sma'] ?></span>
                                </td>
                                <td data-nilai="<?= $siswa['nilai_rata'] ?>">
                                    <div class="score-bar">
                                        <div class="score-bar-track" style="width: 80px;">
                                            <div class="score-bar-fill <?= $siswa['nilai_rata'] >= 80 ? 'high' : ($siswa['nilai_rata'] >= 60 ? 'medium' : 'low') ?>"
                                                style="width: <?= $siswa['nilai_rata'] ?>%;"></div>
                                        </div>
                                        <span class="score-value"><?= formatNumber($siswa['nilai_rata']) ?></span>
                                    </div>
                                </td>
                                <td><?= sanitize(truncate($siswa['minat_bidang'] ?: '-', 20)) ?></td>
                                <td class="text-center">
                                    <div class="flex flex-gap" style="justify-content: center; gap: 8px;">
                                        <a href="rekomendasi.php?id=<?= $siswa['id'] ?>" class="btn btn-sm btn-primary"
                                            title="Lihat Rekomendasi">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?generate=<?= $siswa['id'] ?>" class="btn btn-sm btn-success"
                                            title="Generate Rekomendasi">
                                            <i class="fas fa-magic"></i>
                                        </a>
                                        <a href="?delete=<?= $siswa['id'] ?>" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Hapus data siswa ini?')" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
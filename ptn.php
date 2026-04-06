<?php
/**
 * Halaman Data PTN & Jurusan
 */
$pageTitle = 'Data PTN & Jurusan';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getInstance();

// Get filter
$selectedPTN = get('ptn');
$rumpun = get('rumpun');
$search = get('search');

// Get all PTN
$ptnList = $db->query("SELECT * FROM ptn ORDER BY nama_ptn ASC");

// Build query for jurusan
$where = [];
$params = [];

if ($selectedPTN) {
    $where[] = "j.ptn_id = ?";
    $params[] = $selectedPTN;
}

if ($rumpun) {
    $where[] = "j.rumpun = ?";
    $params[] = $rumpun;
}

if ($search) {
    $where[] = "(j.nama_jurusan LIKE ? OR j.fakultas LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$jurusanList = $db->query("
    SELECT j.*, p.nama_ptn, p.singkatan, p.kota
    FROM jurusan j
    JOIN ptn p ON j.ptn_id = p.id
    $whereClause
    ORDER BY p.nama_ptn ASC, j.nama_jurusan ASC
", $params);

require_once __DIR__ . '/templates/header.php';
?>

<!-- PTN Cards -->
<div class="grid grid-3 mb-4">
    <?php foreach ($ptnList as $ptn): ?>
        <div class="ptn-card <?= $selectedPTN == $ptn['id'] ? 'active' : '' ?>"
            style="<?= $selectedPTN == $ptn['id'] ? 'border: 2px solid var(--primary);' : '' ?>">
            <div class="ptn-header">
                <h3><?= sanitize($ptn['singkatan']) ?></h3>
                <p><?= sanitize($ptn['nama_ptn']) ?></p>
            </div>
            <div class="ptn-body">
                <div class="ptn-info">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?= sanitize($ptn['kota']) ?></span>
                </div>
                <div class="ptn-info">
                    <i class="fas fa-award"></i>
                    <span>Akreditasi <?= $ptn['akreditasi'] ?></span>
                </div>
                <?php if ($ptn['website']): ?>
                    <div class="ptn-info">
                        <i class="fas fa-globe"></i>
                        <a href="<?= $ptn['website'] ?>" target="_blank" class="text-primary"><?= $ptn['website'] ?></a>
                    </div>
                <?php endif; ?>

                <?php
                $jurusanCount = $db->count('jurusan', 'ptn_id = ?', [$ptn['id']]);
                ?>
                <div class="mt-2">
                    <a href="?ptn=<?= $ptn['id'] ?>"
                        class="btn btn-sm <?= $selectedPTN == $ptn['id'] ? 'btn-primary' : 'btn-secondary' ?> btn-block">
                        <i class="fas fa-list"></i> <?= $jurusanCount ?> Program Studi
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="flex flex-gap flex-center">
            <?php if ($selectedPTN): ?>
                <input type="hidden" name="ptn" value="<?= $selectedPTN ?>">
            <?php endif; ?>

            <div class="search-box" style="position: relative; flex: 1;">
                <i class="fas fa-search"
                    style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" name="search" value="<?= sanitize($search) ?>"
                    placeholder="Cari nama jurusan atau fakultas..." class="form-control" style="padding-left: 42px;">
            </div>

            <select name="rumpun" class="form-control" style="width: auto;">
                <option value="">Semua Rumpun</option>
                <option value="Saintek" <?= $rumpun === 'Saintek' ? 'selected' : '' ?>>Saintek</option>
                <option value="Soshum" <?= $rumpun === 'Soshum' ? 'selected' : '' ?>>Soshum</option>
                <option value="Campuran" <?= $rumpun === 'Campuran' ? 'selected' : '' ?>>Campuran</option>
            </select>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>

            <?php if ($selectedPTN || $rumpun || $search): ?>
                <a href="ptn.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Reset
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Jurusan Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-graduation-cap text-primary"></i>
            Daftar Program Studi (<?= count($jurusanList) ?>)
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($jurusanList)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>Tidak Ada Hasil</h3>
                <p>Coba ubah filter pencarian</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Program Studi</th>
                            <th>PTN</th>
                            <th>Fakultas</th>
                            <th>Rumpun</th>
                            <th>Passing Grade</th>
                            <th>Daya Tampung</th>
                            <th>Akreditasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jurusanList as $jurusan): ?>
                            <tr>
                                <td>
                                    <strong><?= sanitize($jurusan['nama_jurusan']) ?></strong>
                                    <?php if ($jurusan['prospek_kerja']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-briefcase"></i>
                                            <?= sanitize(truncate($jurusan['prospek_kerja'], 50)) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= $jurusan['singkatan'] ?></span>
                                    <br>
                                    <small class="text-muted"><?= $jurusan['kota'] ?></small>
                                </td>
                                <td><?= sanitize($jurusan['fakultas'] ?: '-') ?></td>
                                <td>
                                    <span
                                        class="badge <?= $jurusan['rumpun'] === 'Saintek' ? 'bg-success' : ($jurusan['rumpun'] === 'Soshum' ? 'bg-warning' : 'bg-secondary') ?>">
                                        <?= $jurusan['rumpun'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="score-bar">
                                        <div class="score-bar-track" style="width: 60px;">
                                            <div class="score-bar-fill <?= $jurusan['passing_grade'] >= 600 ? 'high' : ($jurusan['passing_grade'] >= 500 ? 'medium' : 'low') ?>"
                                                style="width: <?= min(100, ($jurusan['passing_grade'] / 700) * 100) ?>%;"></div>
                                        </div>
                                        <span class="score-value"><?= formatNumber($jurusan['passing_grade'], 0) ?></span>
                                    </div>
                                </td>
                                <td><?= number_format($jurusan['daya_tampung']) ?></td>
                                <td>
                                    <span class="badge <?= getAkreditasiBadge($jurusan['akreditasi']) ?>">
                                        <?= $jurusan['akreditasi'] ?>
                                    </span>
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
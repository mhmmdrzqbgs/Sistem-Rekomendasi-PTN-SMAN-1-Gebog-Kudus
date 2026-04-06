<?php
/**
 * Halaman Import Excel
 */
$pageTitle = 'Import Data Siswa';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/ExcelImporter.php';

$db = Database::getInstance();
$importer = new ExcelImporter();

$preview = null;
$importResult = null;

// Handle file upload for preview
if (isPost() && isset($_FILES['excel_file']) && isset($_POST['action']) && $_POST['action'] === 'preview') {
    $file = $_FILES['excel_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($file, ALLOWED_EXCEL_TYPES);

        if ($result['success']) {
            $_SESSION['upload_file'] = $result['path'];
            $preview = $importer->preview($result['path'], 10);
        } else {
            setFlash('message', $result['message'], 'error');
        }
    } else {
        setFlash('message', 'Gagal upload file', 'error');
    }
}

// Handle actual import
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'import') {
    $filePath = $_SESSION['upload_file'] ?? null;

    if ($filePath && file_exists($filePath)) {
        $success = $importer->import($filePath);

        if ($success) {
            $count = $importer->getImportedCount();
            setFlash('message', "Berhasil import $count data siswa", 'success');

            // Clean up
            unlink($filePath);
            unset($_SESSION['upload_file']);

            redirect('siswa.php');
        } else {
            $errors = $importer->getErrors();
            setFlash('message', 'Import gagal: ' . implode(', ', array_slice($errors, 0, 3)), 'error');
        }
    } else {
        setFlash('message', 'File tidak ditemukan. Silakan upload ulang.', 'error');
    }
}

// Cancel preview
if (isset($_GET['cancel'])) {
    if (isset($_SESSION['upload_file']) && file_exists($_SESSION['upload_file'])) {
        unlink($_SESSION['upload_file']);
    }
    unset($_SESSION['upload_file']);
    redirect('import.php');
}

require_once __DIR__ . '/templates/header.php';
?>

<?php if ($preview && $preview['success']): ?>
    <!-- Preview Mode -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-eye text-primary"></i>
                Preview Data (<?= $preview['total'] ?> baris)
            </h3>
            <div class="flex flex-gap">
                <a href="?cancel=1" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="import">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Import Sekarang
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>NISN</th>
                            <th>Sekolah</th>
                            <th>Jurusan</th>
                            <th>MTK</th>
                            <th>B.Ing</th>
                            <th>B.Ind</th>
                            <th>IPA</th>
                            <th>IPS</th>
                            <th>Rata-rata</th>
                            <th>Minat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview['data'] as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= sanitize($row['nama'] ?? '-') ?></strong></td>
                                <td><?= sanitize($row['nisn'] ?? '-') ?></td>
                                <td><?= sanitize($row['asal_sekolah'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= $row['jurusan_sma'] ?? '-' ?></span>
                                </td>
                                <td><?= formatNumber($row['nilai_mtk'] ?? 0) ?></td>
                                <td><?= formatNumber($row['nilai_bing'] ?? 0) ?></td>
                                <td><?= formatNumber($row['nilai_bind'] ?? 0) ?></td>
                                <td><?= formatNumber($row['nilai_ipa'] ?? 0) ?></td>
                                <td><?= formatNumber($row['nilai_ips'] ?? 0) ?></td>
                                <td><strong><?= formatNumber($row['nilai_rata'] ?? 0) ?></strong></td>
                                <td><?= sanitize(truncate($row['minat_bidang'] ?? '-', 15)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <p class="text-muted">
                <i class="fas fa-info-circle"></i>
                Menampilkan 10 baris pertama dari total <?= $preview['total'] ?> baris data.
            </p>
        </div>
    </div>

<?php else: ?>
    <!-- Upload Mode -->
    <div class="grid grid-2">
        <!-- Upload Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-excel text-success"></i>
                    Upload File Excel
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <input type="hidden" name="action" value="preview">

                    <div class="file-upload">
                        <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                        <div class="file-upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <h3>Drag & Drop file di sini</h3>
                        <p>atau klik untuk memilih file (.xlsx, .xls, .csv)</p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block mt-3">
                        <i class="fas fa-search"></i> Preview Data
                    </button>
                </form>
            </div>
        </div>

        <!-- Instructions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle text-info"></i>
                    Panduan Format Excel
                </h3>
            </div>
            <div class="card-body">
                <p class="mb-2">File Excel harus memiliki header dengan nama kolom berikut:</p>

                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kolom</th>
                                <th>Deskripsi</th>
                                <th>Contoh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>nama</code></td>
                                <td>Nama lengkap siswa <span class="badge bg-danger">Wajib</span></td>
                                <td>Budi Santoso</td>
                            </tr>
                            <tr>
                                <td><code>nisn</code></td>
                                <td>Nomor Induk Siswa Nasional</td>
                                <td>1234567890</td>
                            </tr>
                            <tr>
                                <td><code>jk</code></td>
                                <td>Jenis Kelamin (L/P)</td>
                                <td>L</td>
                            </tr>
                            <tr>
                                <td><code>sekolah</code></td>
                                <td>Nama sekolah asal</td>
                                <td>SMAN 1 Kudus</td>
                            </tr>
                            <tr>
                                <td><code>jurusan</code></td>
                                <td>Jurusan SMA (IPA/IPS/Bahasa)</td>
                                <td>IPA</td>
                            </tr>
                            <tr>
                                <td><code>mtk</code></td>
                                <td>Nilai Matematika</td>
                                <td>85</td>
                            </tr>
                            <tr>
                                <td><code>bing</code></td>
                                <td>Nilai Bahasa Inggris</td>
                                <td>80</td>
                            </tr>
                            <tr>
                                <td><code>bind</code></td>
                                <td>Nilai Bahasa Indonesia</td>
                                <td>82</td>
                            </tr>
                            <tr>
                                <td><code>ipa</code></td>
                                <td>Nilai IPA</td>
                                <td>88</td>
                            </tr>
                            <tr>
                                <td><code>ips</code></td>
                                <td>Nilai IPS</td>
                                <td>75</td>
                            </tr>
                            <tr>
                                <td><code>minat</code></td>
                                <td>Bidang minat siswa</td>
                                <td>Teknologi</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb"></i>
                    <span>Sistem akan menghitung nilai rata-rata secara otomatis jika tidak ada kolom
                        <code>rata</code>.</span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
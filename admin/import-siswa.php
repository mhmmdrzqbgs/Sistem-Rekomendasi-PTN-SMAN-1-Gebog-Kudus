<?php
/**
 * Admin - Import Excel Siswa (Smart Import)
 * Updated: Tailwind CSS & Dark Mode Support
 */
$pageTitle = 'Import Data Siswa';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check PhpSpreadsheet
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    $composerError = 'PhpSpreadsheet belum terinstall. Jalankan: composer install';
} else {
    require_once $vendorAutoload;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

$db = Database::getInstance();

$previewData = [];
$rawData = [];
$errors = [];
$debugInfo = [];
$step = 'upload'; // upload, mapping, preview, import

if (isPost() && !isset($composerError)) {

    // STEP 3: Confirm Import (EKSEKUSI DATA)
    if (isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
        $data = json_decode($_POST['import_data'], true);

        if (!$data || !is_array($data)) {
            setFlash('message', 'Data import tidak valid atau kosong', 'error');
            redirect('import-siswa.php');
        }

        $successCount = 0;
        $errorCount = 0;
        $errorDetails = [];

        foreach ($data as $index => $row) {
            $rowNum = $index + 1;
            try {
                if (empty($row['nama'])) {
                    $errorDetails[] = "Baris $rowNum: Nama kosong";
                    $errorCount++;
                    continue;
                }

                if (empty($row['nisn'])) {
                    $errorDetails[] = "Baris $rowNum: NISN kosong (Wajib untuk username)";
                    $errorCount++;
                    continue;
                }

                $db->beginTransaction();

                $existing = $db->queryOne("SELECT id FROM users WHERE username = ?", [$row['nisn']]);
                if ($existing) {
                    $errorDetails[] = "Baris $rowNum: NISN '{$row['nisn']}' sudah terdaftar";
                    $errorCount++;
                    $db->rollback();
                    continue;
                }

                // 1. Insert ke tabel USERS
                $password = password_hash('password123', PASSWORD_DEFAULT);
                $db->execute(
                    "INSERT INTO users (username, password, role, nama) VALUES (?, ?, 'siswa', ?)",
                    [$row['nisn'], $password, $row['nama']]
                );
                $userId = $db->lastInsertId();

                // 2. Insert ke tabel SISWA_PROFILE
                $db->execute(
                    "INSERT INTO siswa_profile (user_id, nisn, kelas, jurusan_sma, asal_sekolah, tahun_lulus, minat, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Aktif')",
                    [
                        $userId,
                        $row['nisn'],
                        $row['kelas'] ?? null,
                        $row['jurusan'] ?? 'IPA',
                        $row['sekolah'] ?? null,
                        $row['tahun_lulus'] ?? date('Y'),
                        $row['minat'] ?? null
                    ]
                );

                $db->commit();
                $successCount++;
            } catch (Exception $e) {
                $db->rollback();
                $errorDetails[] = "Baris $rowNum: " . $e->getMessage();
                $errorCount++;
            }
        }

        if ($successCount > 0) {
            $message = "$successCount siswa berhasil diimport.";
            if ($errorCount > 0) {
                $message .= " $errorCount gagal.";
            }
            setFlash('message', $message, 'success');
        } else {
            setFlash('message', "Import gagal! " . implode('; ', array_slice($errorDetails, 0, 3)), 'error');
        }
        redirect('import-siswa.php');
    }

    // STEP 2: Apply Mapping
    if (isset($_POST['action']) && $_POST['action'] === 'apply_mapping') {
        $step = 'preview';
        $rawData = json_decode($_POST['raw_data'], true);
        $startRow = intval($_POST['start_row']);

        $mapping = [
            'nama' => $_POST['col_nama'] ?? '',
            'nisn' => $_POST['col_nisn'] ?? '',
            'kelas' => $_POST['col_kelas'] ?? '',
            'jurusan' => $_POST['col_jurusan'] ?? '',
            'sekolah' => $_POST['col_sekolah'] ?? ''
        ];

        foreach ($rawData as $i => $row) {
            if ($i < $startRow - 1) continue;

            $mapped = [];
            foreach ($mapping as $field => $colIndex) {
                if ($colIndex !== '' && isset($row[$colIndex])) {
                    $mapped[$field] = trim($row[$colIndex]);
                } else {
                    $mapped[$field] = '';
                }
            }

            if (empty($mapped['nama'])) continue;

            if (empty($mapped['jurusan'])) {
                $mapped['jurusan'] = 'IPA';
            }

            if (!empty($mapped['nisn'])) {
                $mapped['nisn'] = preg_replace('/[^0-9]/', '', $mapped['nisn']);
            }

            $previewData[] = $mapped;
        }

        if (empty($previewData)) {
            $errors[] = "Tidak ada data valid setelah mapping. Pastikan kolom 'nama' dipilih dengan benar.";
            $step = 'mapping';
        }
    }

    // STEP 1: Upload and Analyze
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $step = 'mapping';
        $file = $_FILES['excel_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'xls'])) {
            $errors[] = "Format file tidak didukung: .$ext";
            $step = 'upload';
        } else {
            try {
                $tempFile = sys_get_temp_dir() . '/import_' . time() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], $tempFile);

                $spreadsheet = IOFactory::load($tempFile);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = min($worksheet->getHighestRow(), 200);
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                for ($row = 1; $row <= $highestRow; $row++) {
                    $rowData = [];
                    for ($col = 1; $col <= $highestColumnIndex; $col++) {
                        $cell = $worksheet->getCellByColumnAndRow($col, $row);
                        $value = $cell->getValue();
                        $rowData[] = is_string($value) ? trim($value) : (string) $value;
                    }
                    $rawData[] = $rowData;
                }

                $detectedStartRow = 1;
                $maxFilledCols = 0;
                foreach ($rawData as $i => $row) {
                    $filled = count(array_filter($row, fn($v) => !empty($v)));
                    if ($filled > $maxFilledCols) {
                        $maxFilledCols = $filled;
                        $detectedStartRow = $i + 1;
                    }
                }

                $columnGuesses = [];
                $sampleRow = $rawData[$detectedStartRow] ?? $rawData[0] ?? [];
                foreach ($sampleRow as $colIdx => $value) {
                    $val = strtolower($value);
                    if (preg_match('/^\d{5,20}$/', $value)) {
                        $columnGuesses[$colIdx] = 'nisn';
                    } elseif (preg_match('/^[a-z\s\.]+$/i', $value) && strlen($value) > 3) {
                        $columnGuesses[$colIdx] = 'nama';
                    } elseif (preg_match('/(kelas|x|xi|xii)/i', $value)) {
                        $columnGuesses[$colIdx] = 'kelas';
                    } elseif (preg_match('/(ipa|ips)/i', $value)) {
                        $columnGuesses[$colIdx] = 'jurusan';
                    } elseif (preg_match('/(sma|ma|smk)/i', $value)) {
                        $columnGuesses[$colIdx] = 'sekolah';
                    }
                }

                $debugInfo['detected_start_row'] = $detectedStartRow;
                $debugInfo['column_guesses'] = $columnGuesses;

                @unlink($tempFile);
            } catch (Exception $e) {
                $errors[] = "Error membaca file: " . $e->getMessage();
                $step = 'upload';
            }
        }
    }
}

require_once __DIR__ . '/../templates/header-admin.php';
?>

<div class="max-w-5xl mx-auto space-y-6">

    <?php if (isset($composerError)): ?>
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-red-900/30 dark:text-red-400" role="alert">
            <span class="font-medium">Error!</span> <?= $composerError ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-red-900/30 dark:text-red-400" role="alert">
            <ul class="list-disc pl-5 space-y-1">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($msg = getFlash('message')): ?>
        <div class="p-4 mb-4 text-sm rounded-lg <?= $msg['type'] === 'success' ? 'text-green-800 bg-green-50 dark:bg-green-900/30 dark:text-green-400' : 'text-red-800 bg-red-50 dark:bg-red-900/30 dark:text-red-400' ?>" role="alert">
            <?= $msg['message'] ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 'upload'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fas fa-file-excel text-emerald-500"></i> Upload File Excel
                    </h3>
                </div>
                <div class="p-6">
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Pilih File (.xlsx, .xls)</label>
                            <input type="file" name="excel_file" class="block w-full text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-slate-700 dark:file:text-slate-300" accept=".xlsx,.xls" required>
                        </div>
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-upload mr-2"></i> Upload & Analisis
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-blue-50 dark:bg-blue-900/20">
                    <h3 class="font-semibold text-blue-700 dark:text-blue-300 flex items-center gap-2">
                        <i class="fas fa-magic"></i> Fitur Smart Import
                    </h3>
                </div>
                <div class="p-6">
                    <ul class="list-disc pl-5 space-y-2 text-sm text-slate-600 dark:text-slate-300 mb-4">
                        <li>Auto-detect baris awal data.</li>
                        <li><strong>Username Login = NISN</strong></li>
                        <li>Status Siswa otomatis: <strong>Aktif</strong></li>
                    </ul>

                    <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg flex gap-3">
                        <i class="fas fa-key text-amber-500 mt-0.5"></i>
                        <div class="text-sm text-amber-800 dark:text-amber-200">
                            <strong>Info Penting:</strong><br>
                            Password default untuk semua siswa baru adalah: <strong>password123</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($step === 'mapping'): ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-indigo-50 dark:bg-indigo-900/20">
                <h3 class="font-semibold text-indigo-700 dark:text-indigo-300 flex items-center gap-2">
                    <i class="fas fa-columns"></i> Mapping Kolom Excel
                </h3>
            </div>
            <div class="p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="apply_mapping">
                    <input type="hidden" name="raw_data" value="<?= htmlspecialchars(json_encode($rawData)) ?>">

                    <div class="p-4 mb-6 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 text-sm rounded-lg flex gap-2">
                        <i class="fas fa-lightbulb mt-0.5"></i>
                        <span>Silakan cocokkan kolom Excel dengan data sistem. NISN Wajib diisi karena digunakan untuk Login.</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Baris Awal Data *</label>
                            <select name="start_row" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm" required>
                                <?php for ($i = 1; $i <= min(20, count($rawData)); $i++): ?>
                                    <option value="<?= $i ?>" <?= ($debugInfo['detected_start_row'] ?? 2) == $i ? 'selected' : '' ?>>
                                        Baris <?= $i ?>: <?= sanitize(truncate(implode(' | ', array_filter($rawData[$i - 1] ?? [])), 30)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Kolom NAMA *</label>
                            <select name="col_nama" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($rawData[0] ?? [] as $i => $val): ?>
                                    <option value="<?= $i ?>" <?= ($debugInfo['column_guesses'][$i] ?? '') === 'nama' ? 'selected' : '' ?>>
                                        Kolom <?= $i + 1 ?>: <?= sanitize(truncate($val ?: '(kosong)', 20)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Kolom NISN (User) *</label>
                            <select name="col_nisn" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($rawData[0] ?? [] as $i => $val): ?>
                                    <option value="<?= $i ?>" <?= ($debugInfo['column_guesses'][$i] ?? '') === 'nisn' ? 'selected' : '' ?>>
                                        Kolom <?= $i + 1 ?>: <?= sanitize(truncate($val ?: '(kosong)', 20)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Kolom KELAS</label>
                            <select name="col_kelas" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm">
                                <option value="">-- Tidak ada --</option>
                                <?php foreach ($rawData[0] ?? [] as $i => $val): ?>
                                    <option value="<?= $i ?>" <?= ($debugInfo['column_guesses'][$i] ?? '') === 'kelas' ? 'selected' : '' ?>>
                                        Kolom <?= $i + 1 ?>: <?= sanitize(truncate($val ?: '(kosong)', 20)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Kolom JURUSAN</label>
                            <select name="col_jurusan" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm">
                                <option value="">-- Default: IPA --</option>
                                <?php foreach ($rawData[0] ?? [] as $i => $val): ?>
                                    <option value="<?= $i ?>" <?= ($debugInfo['column_guesses'][$i] ?? '') === 'jurusan' ? 'selected' : '' ?>>
                                        Kolom <?= $i + 1 ?>: <?= sanitize(truncate($val ?: '(kosong)', 20)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Kolom SEKOLAH</label>
                            <select name="col_sekolah" class="block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm">
                                <option value="">-- Tidak ada --</option>
                                <?php foreach ($rawData[0] ?? [] as $i => $val): ?>
                                    <option value="<?= $i ?>" <?= ($debugInfo['column_guesses'][$i] ?? '') === 'sekolah' ? 'selected' : '' ?>>
                                        Kolom <?= $i + 1 ?>: <?= sanitize(truncate($val ?: '(kosong)', 20)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3 border-t border-slate-200 dark:border-slate-700 pt-4">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-check mr-1"></i> Terapkan Mapping
                        </button>
                        <a href="import-siswa.php" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-medium rounded-lg transition-colors">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mt-6">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-table text-slate-500"></i> Preview Data Mentah
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase text-slate-500 dark:text-slate-400 font-semibold">
                            <th class="px-4 py-2 border-r border-slate-200 dark:border-slate-700">Baris</th>
                            <?php for ($i = 0; $i < count($rawData[0] ?? []); $i++): ?>
                                <th class="px-4 py-2 whitespace-nowrap">Kolom <?= $i + 1 ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <?php foreach (array_slice($rawData, 0, 10) as $rowIdx => $row): ?>
                            <tr class="<?= $rowIdx == ($debugInfo['detected_start_row'] ?? 2) - 1 ? 'bg-indigo-50/50 dark:bg-indigo-900/10' : '' ?>">
                                <td class="px-4 py-2 font-bold border-r border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300"><?= $rowIdx + 1 ?></td>
                                <?php foreach ($row as $cell): ?>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-400 whitespace-nowrap"><?= sanitize(truncate($cell ?: '-', 20)) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($step === 'preview'): ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-emerald-50 dark:bg-emerald-900/20">
                <h3 class="font-semibold text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> Siap Import (<?= count($previewData) ?> siswa)
                </h3>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700 mb-6">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase text-slate-500 dark:text-slate-400 font-semibold">
                                <th class="px-4 py-2">#</th>
                                <th class="px-4 py-2">Nama</th>
                                <th class="px-4 py-2">NISN</th>
                                <th class="px-4 py-2">Kelas</th>
                                <th class="px-4 py-2">Jurusan</th>
                                <th class="px-4 py-2">Sekolah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <?php foreach (array_slice($previewData, 0, 50) as $i => $row): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-4 py-2 text-slate-500"><?= $i + 1 ?></td>
                                    <td class="px-4 py-2 font-medium text-slate-900 dark:text-white"><?= sanitize($row['nama'] ?? '-') ?></td>
                                    <td class="px-4 py-2">
                                        <?php if (empty($row['nisn'])): ?>
                                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300">KOSONG</span>
                                        <?php else: ?>
                                            <span class="text-slate-600 dark:text-slate-300"><?= sanitize($row['nisn']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-300"><?= sanitize($row['kelas'] ?? '-') ?></td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= ($row['jurusan'] ?? 'IPA') === 'IPA' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' ?>">
                                            <?= sanitize($row['jurusan'] ?? 'IPA') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-300"><?= sanitize($row['sekolah'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="action" value="confirm_import">
                    <input type="hidden" name="import_data" value="<?= htmlspecialchars(json_encode($previewData)) ?>">

                    <button type="submit" class="flex-1 px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-colors shadow-lg shadow-emerald-500/30 flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                        <i class="fas fa-save"></i> EKSEKUSI IMPORT
                    </button>
                    <a href="import-siswa.php" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-medium rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-arrow-left mr-2"></i> Batal
                    </a>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
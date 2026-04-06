<?php
/**
 * Excel Importer Class
 * Menggunakan PhpSpreadsheet untuk parsing file Excel
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/functions.php';

class ExcelImporter
{
    private $db;
    private $errors = [];
    private $imported = 0;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Import file Excel ke database
     */
    public function import($filePath, $userId = null)
    {
        // Check if PhpSpreadsheet is available
        $autoloadPath = ROOT_PATH . '/vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            $this->errors[] = "PhpSpreadsheet belum terinstall. Jalankan: composer require phpoffice/phpspreadsheet";
            return false;
        }

        require_once $autoloadPath;

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Skip header row
            $header = array_shift($rows);
            $headerMap = $this->mapHeader($header);

            if (empty($headerMap)) {
                $this->errors[] = "Format header Excel tidak valid";
                return false;
            }

            $this->db->beginTransaction();

            $fileName = basename($filePath);
            $importLogId = $this->createImportLog($fileName, $userId);

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // +2 karena skip header dan 0-indexed

                if ($this->isEmptyRow($row))
                    continue;

                $data = $this->parseRow($row, $headerMap);

                if (!$this->validateRow($data, $rowNum))
                    continue;

                if ($this->insertSiswa($data)) {
                    $this->imported++;
                }
            }

            $this->updateImportLog($importLogId, 'success', $this->imported, 0, implode('; ', $this->errors));

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            $this->errors[] = "Error: " . $e->getMessage();
            if (isset($importLogId)) {
                $this->updateImportLog($importLogId, 'failed', 0, 0, implode('; ', $this->errors));
            }
            return false;
        }
    }

    /**
     * Preview data dari Excel tanpa menyimpan
     */
    public function preview($filePath, $limit = 10)
    {
        $autoloadPath = ROOT_PATH . '/vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            return ['success' => false, 'message' => 'PhpSpreadsheet belum terinstall'];
        }

        require_once $autoloadPath;

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            $header = array_shift($rows);
            $headerMap = $this->mapHeader($header);

            $preview = [];
            $count = 0;

            foreach ($rows as $row) {
                if ($this->isEmptyRow($row))
                    continue;
                if ($count >= $limit)
                    break;

                $data = $this->parseRow($row, $headerMap);
                $preview[] = $data;
                $count++;
            }

            return [
                'success' => true,
                'header' => $header,
                'data' => $preview,
                'total' => count($rows)
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Map header Excel ke field database
     */
    private function mapHeader($header)
    {
        $map = [];
        $headerLower = array_map('strtolower', array_map('trim', $header));

        // Mapping fleksibel
        $mappings = [
            'nisn' => ['nisn', 'no_induk', 'nis'],
            'nama' => ['nama', 'nama_siswa', 'nama_lengkap', 'name'],
            'email' => ['email', 'email_siswa', 'e-mail'],
            'jenis_kelamin' => ['jk', 'jenis_kelamin', 'gender', 'kelamin', 'l/p'],
            'asal_sekolah' => ['sekolah', 'asal_sekolah', 'sma', 'smk', 'asal'],
            'jurusan_sma' => ['jurusan', 'jurusan_sma', 'peminatan', 'program'],
            'kelas' => ['kelas', 'class'],
            'tahun_lulus' => ['tahun_lulus', 'tahun_kelulusan', 'tahun'],
            'eligible_snbp' => ['eligible_snbp', 'snbp_eligible', 'eligible'],
            'peringkat_sekolah' => ['peringkat_sekolah', 'peringkat', 'rank'],
            'nilai_mtk' => ['mtk', 'matematika', 'nilai_mtk', 'math'],
            'nilai_bing' => ['bing', 'b_inggris', 'inggris', 'nilai_bing', 'english'],
            'nilai_bind' => ['bind', 'b_indonesia', 'indonesia', 'nilai_bind'],
            'nilai_ipa' => ['ipa', 'nilai_ipa', 'sains', 'science'],
            'nilai_ips' => ['ips', 'nilai_ips', 'sosial'],
            'nilai_rata' => ['rata', 'rata-rata', 'nilai_rata', 'average', 'rerata'],
            'minat_bidang' => ['minat', 'minat_bidang', 'interest', 'bidang_minat']
        ];

        foreach ($mappings as $field => $aliases) {
            foreach ($headerLower as $index => $col) {
                if (in_array($col, $aliases)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Parse row data berdasarkan header map
     */
    private function parseRow($row, $headerMap)
    {
        $data = [];

        foreach ($headerMap as $field => $index) {
            $value = isset($row[$index]) ? trim($row[$index]) : '';

            // Normalize values
            switch ($field) {
                case 'jenis_kelamin':
                    $value = strtoupper(substr($value, 0, 1));
                    if (!in_array($value, ['L', 'P']))
                        $value = 'L';
                    break;

                case 'jurusan_sma':
                    $value = strtoupper($value);
                    if (!in_array($value, ['IPA', 'IPS', 'BAHASA', 'TEKNIK'])) {
                        $value = 'IPA';
                    }
                    break;

                case 'eligible_snbp':
                    $value = in_array(strtolower($value), ['1', 'ya', 'yes', 'true', 'y']) ? 1 : 0;
                    break;

                case 'peringkat_sekolah':
                case 'tahun_lulus':
                    $value = intval($value);
                    break;

                case 'nilai_mtk':
                case 'nilai_bing':
                case 'nilai_bind':
                case 'nilai_ipa':
                case 'nilai_ips':
                case 'nilai_rata':
                    $value = floatval(str_replace(',', '.', $value));
                    break;

                case 'email':
                    if (empty($value)) {
                        $value = $this->generateEmail($data['nama'] ?? '', $data['nisn'] ?? '');
                    }
                    break;
            }

            $data[$field] = $value;
        }

        // Calculate average if not provided
        if (empty($data['nilai_rata']) || $data['nilai_rata'] == 0) {
            $values = array_filter([
                $data['nilai_mtk'] ?? 0,
                $data['nilai_bing'] ?? 0,
                $data['nilai_bind'] ?? 0,
                $data['nilai_ipa'] ?? 0,
                $data['nilai_ips'] ?? 0
            ]);

            if (!empty($values)) {
                $data['nilai_rata'] = array_sum($values) / count($values);
            }
        }

        return $data;
    }

    /**
     * Validate row data
     */
    private function validateRow($data, $rowNum)
    {
        if (empty($data['nama'])) {
            $this->errors[] = "Baris $rowNum: Nama tidak boleh kosong";
            return false;
        }

        if (empty($data['email'])) {
            $this->errors[] = "Baris $rowNum: Email tidak boleh kosong";
            return false;
        }

        // Check duplicate email
        $existing = $this->db->queryOne(
            "SELECT id FROM users WHERE email = ?",
            [$data['email']]
        );
        if ($existing) {
            $this->errors[] = "Baris $rowNum: Email {$data['email']} sudah terdaftar";
            return false;
        }

        // Check duplicate NISN if provided
        if (!empty($data['nisn'])) {
            $existing = $this->db->queryOne(
                "SELECT id FROM siswa_profile WHERE nisn = ?",
                [$data['nisn']]
            );
            if ($existing) {
                $this->errors[] = "Baris $rowNum: NISN {$data['nisn']} sudah terdaftar";
                return false;
            }
        }

        return true;
    }

    /**
     * Insert siswa ke database
     */
    private function insertSiswa($data)
    {
        try {
            // Insert to users table
            $password = password_hash('password123', PASSWORD_DEFAULT);
            $this->db->execute(
                "INSERT INTO users (email, password, role, nama) VALUES (?, ?, 'siswa', ?)",
                [$data['email'], $password, $data['nama']]
            );
            $userId = $this->db->lastInsertId();

            // Insert to siswa_profile table
            $this->db->execute(
                "INSERT INTO siswa_profile (user_id, nisn, kelas, jurusan_sma, asal_sekolah, tahun_lulus, minat, eligible_snbp, peringkat_sekolah)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $data['nisn'] ?? null,
                    $data['kelas'] ?? null,
                    $data['jurusan_sma'] ?? 'IPA',
                    $data['asal_sekolah'] ?? null,
                    $data['tahun_lulus'] ?? date('Y'),
                    $data['minat_bidang'] ?? null,
                    $data['eligible_snbp'] ?? 0,
                    $data['peringkat_sekolah'] ?? 0
                ]
            );

            return true;
        } catch (Exception $e) {
            $this->errors[] = "Error inserting siswa: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Check if row is empty
     */
    private function isEmptyRow($row)
    {
        return empty(array_filter($row, function ($cell) {
            return trim($cell) !== '';
        }));
    }

    /**
     * Get import errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get imported count
     */
    public function getImportedCount()
    {
        return $this->imported;
    }

    /**
     * Create import log entry
     */
    private function createImportLog($fileName, $userId)
    {
        $this->db->execute(
            "INSERT INTO import_log (file_name, imported_by, status, total_records) VALUES (?, ?, 'partial', 0)",
            [$fileName, $userId]
        );
        return $this->db->lastInsertId();
    }

    /**
     * Update import log entry
     */
    private function updateImportLog($logId, $status, $successRecords, $totalRecords, $errorMessage = null)
    {
        $this->db->execute(
            "UPDATE import_log SET status = ?, success_records = ?, total_records = ?, error_message = ? WHERE id = ?",
            [$status, $successRecords, $totalRecords, $errorMessage, $logId]
        );
    }

    /**
     * Generate email from name and nisn
     */
    private function generateEmail($nama, $nisn = null)
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nama));
        $email = $base . '@siswa.sch.id';

        // Check if email exists, add number if needed
        $counter = 1;
        $originalEmail = $email;
        while ($this->db->queryOne("SELECT id FROM users WHERE email = ?", [$email])) {
            $email = $base . $counter . '@siswa.sch.id';
            $counter++;
            if ($counter > 100) break; // Prevent infinite loop
        }

        return $email;
    }
}

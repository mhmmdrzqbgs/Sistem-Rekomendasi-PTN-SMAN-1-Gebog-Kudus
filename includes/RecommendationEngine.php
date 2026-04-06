<?php
/**
 * Recommendation Engine Class
 * Algoritma untuk menghasilkan rekomendasi jurusan berdasarkan profil siswa
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/app.php';

class RecommendationEngine
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generate rekomendasi untuk satu siswa
     */
    public function generateForStudent($siswaId)
    {
        $siswa = $this->db->queryOne("
            SELECT sp.*, u.nama, u.email
            FROM siswa_profile sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.id = ?
        ", [$siswaId]);

        if (!$siswa) {
            return false;
        }

        // Hapus rekomendasi lama
        $this->db->execute("DELETE FROM rekomendasi WHERE siswa_id = ?", [$siswaId]);

        // Ambil semua prodi
        $prodiList = $this->db->query("
            SELECT p.*, pt.nama as nama_ptn, pt.singkatan, pt.kota, pt.level, pt.akreditasi as ptn_akreditasi
            FROM prodi p
            JOIN ptn pt ON p.ptn_id = pt.id
            ORDER BY p.daya_tampung DESC
        ");

        $recommendations = [];

        foreach ($prodiList as $prodi) {
            $score = $this->calculateScore($siswa, $prodi);
            $recommendations[] = [
                'prodi_id' => $prodi['id'],
                'jalur' => $siswa['eligible_snbp'] ? 'SNBP' : 'SNBT',
                'skor' => $score['total'],
                'peluang' => $this->calculatePeluang($score['total'], $prodi, $siswa),
                'alasan' => $this->generateAlasan($score, $prodi, $siswa)
            ];
        }

        // Sort by total score
        usort($recommendations, function ($a, $b) {
            return $b['skor'] <=> $a['skor'];
        });

        // Simpan top recommendations
        $topCount = min(TOP_RECOMMENDATIONS, count($recommendations));

        for ($i = 0; $i < $topCount; $i++) {
            $rec = $recommendations[$i];
            $this->db->execute("
                INSERT INTO rekomendasi (siswa_id, prodi_id, jalur, skor, peluang, alasan, ranking)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $siswaId,
                $rec['prodi_id'],
                $rec['jalur'],
                $rec['skor'],
                $rec['peluang'],
                $rec['alasan'],
                $i + 1
            ]);
        }

        return $topCount;
    }

    /**
     * Generate rekomendasi untuk semua siswa
     */
    public function generateForAll()
    {
        $siswaList = $this->db->query("SELECT id FROM siswa_profile");
        $count = 0;

        foreach ($siswaList as $siswa) {
            if ($this->generateForStudent($siswa['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Hitung skor rekomendasi
     */
    private function calculateScore($siswa, $prodi)
    {
        // Ambil nilai rapor siswa (rata-rata dari semua semester)
        $nilaiRapor = $this->db->queryOne("
            SELECT AVG(rata_rata) as nilai_rata
            FROM nilai_rapor
            WHERE siswa_id = ?
        ", [$siswa['id']]);

        $nilaiRata = $nilaiRapor['nilai_rata'] ?? 0;

        // 1. Skor Akademik (50%)
        // Bandingkan dengan cutoff prodi
        $cutoff = $this->db->queryOne("
            SELECT cutoff_avg
            FROM cutoff_prodi
            WHERE prodi_id = ? AND jalur = ?
            ORDER BY tahun DESC LIMIT 1
        ", [$prodi['id'], $siswa['eligible_snbp'] ? 'SNBP' : 'SNBT']);

        $cutoffValue = $cutoff['cutoff_avg'] ?? 0;

        if ($cutoffValue > 0) {
            // Konversi nilai rapor ke skala SNBT (biasanya 100-700)
            $nilaiSkala = ($nilaiRata / 100) * 600 + 100; // Asumsi rapor 0-100 -> SNBT 100-700
            $skorAkademik = min(100, ($nilaiSkala / $cutoffValue) * 100);
        } else {
            $skorAkademik = 50; // Default jika tidak ada cutoff
        }

        // Bonus untuk eligible SNBP di PTN negeri
        if ($siswa['eligible_snbp'] && $prodi['level'] === 'Negeri') {
            $skorAkademik *= 1.2; // Bonus 20%
            $skorAkademik = min(100, $skorAkademik);
        }

        // 2. Skor Kesesuaian (30%)
        // Cocokkan jurusan SMA dengan rumpun prodi
        $rumpunSiswa = $this->getRumpunFromJurusan($siswa['jurusan_sma']);
        $rumpunProdi = $prodi['rumpun'];

        if ($rumpunSiswa === $rumpunProdi) {
            $skorKesesuaian = 100;
        } elseif ($rumpunProdi === 'Saintek' && $rumpunSiswa === 'Soshum') {
            $skorKesesuaian = 60; // Bisa lintas tapi kurang ideal
        } elseif ($rumpunProdi === 'Soshum' && $rumpunSiswa === 'Saintek') {
            $skorKesesuaian = 60;
        } else {
            $skorKesesuaian = 80; // Campuran atau sama
        }

        // 3. Skor Minat (20%)
        $skorMinat = $this->calculateMinatScore($siswa['minat'], $prodi);

        // Hitung total dengan bobot
        $total = ($skorAkademik * WEIGHT_AKADEMIK) +
            ($skorKesesuaian * WEIGHT_KESESUAIAN) +
            ($skorMinat * WEIGHT_MINAT);

        return [
            'akademik' => round($skorAkademik, 2),
            'kesesuaian' => round($skorKesesuaian, 2),
            'minat' => round($skorMinat, 2),
            'total' => round($total, 2)
        ];
    }

    /**
     * Hitung skor minat berdasarkan keyword matching
     */
    private function calculateMinatScore($minat, $prodi)
    {
        if (empty($minat))
            return 50; // Default score jika minat tidak diisi

        $minat = strtolower($minat);
        $namaProdi = strtolower($prodi['nama']);
        $fakultas = strtolower($prodi['fakultas'] ?? '');
        $prospekKerja = strtolower($prodi['prospek_kerja'] ?? '');

        // Keyword matching
        $keywords = [
            'teknologi' => ['teknik', 'informatika', 'komputer', 'elektro', 'it', 'programming', 'sistem'],
            'kesehatan' => ['kedokteran', 'farmasi', 'keperawatan', 'kesehatan', 'medis'],
            'bisnis' => ['manajemen', 'akuntansi', 'ekonomi', 'bisnis', 'marketing', 'bisnis'],
            'pendidikan' => ['pendidikan', 'guru', 'pgsd', 'mengajar', 'didik'],
            'hukum' => ['hukum', 'legal', 'pengacara', 'hukum'],
            'seni' => ['seni', 'desain', 'arsitektur', 'musik', 'kreatif'],
            'bahasa' => ['bahasa', 'sastra', 'linguistik', 'komunikasi', 'bahasa'],
            'islam' => ['islam', 'syariah', 'tarbiyah', 'dakwah', 'agama', 'islam'],
            'sosial' => ['sosial', 'politik', 'psikologi', 'sosiologi', 'humaniora']
        ];

        $score = 0;

        foreach ($keywords as $category => $terms) {
            // Check if student's interest matches category
            if (strpos($minat, $category) !== false) {
                // Check if prodi matches the keywords
                foreach ($terms as $term) {
                    if (
                        strpos($namaProdi, $term) !== false ||
                        strpos($fakultas, $term) !== false ||
                        strpos($prospekKerja, $term) !== false
                    ) {
                        $score = 100;
                        break 2;
                    }
                }
            }

            // Direct match check
            foreach ($terms as $term) {
                if (
                    strpos($minat, $term) !== false &&
                    strpos($namaProdi, $term) !== false
                ) {
                    $score = 100;
                    break 2;
                }
            }
        }

        return $score > 0 ? $score : 30; // Minimum score 30 jika ada minat tapi tidak match
    }

    /**
     * Get rumpun dari jurusan SMA
     */
    private function getRumpunFromJurusan($jurusan)
    {
        $mapping = [
            'IPA' => 'Saintek',
            'IPS' => 'Soshum'
        ];

        return $mapping[$jurusan] ?? 'Saintek';
    }

    /**
     * Hitung peluang berdasarkan skor dan data prodi
     */
    private function calculatePeluang($skor, $prodi, $siswa)
    {
        $baseScore = $skor;

        // Bonus untuk eligible SNBP
        if ($siswa['eligible_snbp'] && $prodi['level'] === 'Negeri') {
            $baseScore += 20;
        }

        // Bonus untuk peringkat sekolah tinggi
        if ($siswa['peringkat_sekolah'] > 0 && $siswa['peringkat_sekolah'] <= 10) {
            $baseScore += 15;
        }

        if ($baseScore >= 85) return 'Tinggi';
        if ($baseScore >= 70) return 'Sedang';
        return 'Rendah';
    }

    /**
     * Generate alasan rekomendasi
     */
    private function generateAlasan($score, $prodi, $siswa)
    {
        $alasan = [];

        if ($score['akademik'] >= 80) {
            $alasan[] = "Nilai akademik sangat baik";
        } elseif ($score['akademik'] >= 60) {
            $alasan[] = "Nilai akademik cukup baik";
        } else {
            $alasan[] = "Perlu peningkatan nilai akademik";
        }

        if ($score['kesesuaian'] >= 80) {
            $alasan[] = "Sesuai dengan jurusan SMA {$siswa['jurusan_sma']}";
        } else {
            $alasan[] = "Beda rumpun dengan jurusan SMA";
        }

        if ($score['minat'] >= 80) {
            $alasan[] = "Sesuai dengan minat siswa";
        } elseif ($score['minat'] >= 50) {
            $alasan[] = "Cukup sesuai dengan minat";
        } else {
            $alasan[] = "Kurang sesuai dengan minat siswa";
        }

        if ($siswa['eligible_snbp'] && $prodi['level'] === 'Negeri') {
            $alasan[] = "Eligible SNBP dan PTN Negeri";
        }

        return implode('; ', $alasan);
    }

    /**
     * Get rekomendasi untuk siswa
     */
    public function getRecommendations($siswaId)
    {
        return $this->db->query("
            SELECT r.*, p.nama, p.fakultas, p.rumpun, p.daya_tampung, p.akreditasi as prodi_akreditasi, p.prospek_kerja,
                   pt.nama as nama_ptn, pt.singkatan, pt.kota, pt.level, pt.akreditasi as ptn_akreditasi
            FROM rekomendasi r
            JOIN prodi p ON r.prodi_id = p.id
            JOIN ptn pt ON p.ptn_id = pt.id
            WHERE r.siswa_id = ?
            ORDER BY r.ranking ASC
        ", [$siswaId]);
    }

    /**
     * Get statistik rekomendasi
     */
    public function getStatistics()
    {
        $stats = [];

        // Total siswa dengan rekomendasi
        $stats['total_siswa'] = $this->db->count('siswa_profile');
        $stats['siswa_dengan_rekomendasi'] = $this->db->queryOne("
            SELECT COUNT(DISTINCT siswa_id) as total FROM rekomendasi
        ")['total'];

        // Prodi paling banyak direkomendasikan
        $stats['prodi_populer'] = $this->db->query("
            SELECT p.nama, pt.singkatan, COUNT(*) as count
            FROM rekomendasi r
            JOIN prodi p ON r.prodi_id = p.id
            JOIN ptn pt ON p.ptn_id = pt.id
            WHERE r.ranking = 1
            GROUP BY p.id
            ORDER BY count DESC
            LIMIT 5
        ");

        // PTN paling banyak direkomendasikan
        $stats['ptn_populer'] = $this->db->query("
            SELECT pt.nama, pt.singkatan, COUNT(*) as count
            FROM rekomendasi r
            JOIN prodi p ON r.prodi_id = p.id
            JOIN ptn pt ON p.ptn_id = pt.id
            WHERE r.ranking = 1
            GROUP BY pt.id
            ORDER BY count DESC
            LIMIT 5
        ");

        return $stats;
    }
}

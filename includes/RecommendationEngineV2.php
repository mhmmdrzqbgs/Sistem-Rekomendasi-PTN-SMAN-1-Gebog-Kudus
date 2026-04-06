<?php
/**
 * Recommendation Engine V7 (SMART PRIORITY)
 * Fitur:
 * 1. PRIORITAS UTAMA: Mengambil prodi yang AMAN/TARGET (Passing Grade <= Skor) terlebih dahulu.
 * 2. PENGISI KEKOSONGAN: Jika kuota (5+5) belum penuh, ambil TANTANGAN yang paling ringan (Passing Grade terendah di atas skor).
 * 3. Parser: Tetap menggunakan Parser Agresif (User approved).
 * 4. Ribbon: Tetap muncul.
 */

require_once __DIR__ . '/Database.php';

class RecommendationEngineV2
{
    private $db;

    // Keyword klasifikasi manual
    private $keywordsSaintek = ['Teknik', 'Kedokteran', 'MIPA', 'Farmasi', 'Komputer', 'Sistem', 'Informatika', 'Biologi', 'Fisika', 'Kimia', 'Matematika', 'Statistika', 'Gizi', 'Keperawatan', 'Arsitektur', 'Agroteknologi', 'Peternakan', 'Kehutanan', 'Sains', 'Rekayasa'];
    private $keywordsSoshum = ['Hukum', 'Ekonomi', 'Manajemen', 'Akuntansi', 'Psikologi', 'Sastra', 'Bahasa', 'Hubungan', 'Komunikasi', 'Sosiologi', 'Politik', 'Administrasi', 'Sejarah', 'Antropologi', 'Pariwisata', 'Seni', 'Desain', 'Pendidikan', 'Sosial'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function generateForStudent($siswaId)
    {
        // 1. AMBIL DATA
        $siswa = $this->db->queryOne("SELECT * FROM siswa_profile WHERE id = ?", [$siswaId]);
        if (!$siswa) return 0;

        // PARSE MINAT
        $pilihanSiswa = $this->parseMinatToProdiIds($siswa['minat_saintek'], $siswa['minat_soshum']);

        // 2. HITUNG SKOR
        $rapor = $this->db->queryOne("SELECT AVG(rata_rata) as val FROM nilai_rapor WHERE siswa_id = ? AND semester BETWEEN 1 AND 5", [$siswaId]);
        $rawRapor = floatval($rapor['val'] ?? 0);
        $tka = $this->db->queryOne("SELECT rata_rata_tka FROM nilai_tka WHERE siswa_id = ?", [$siswaId]);
        $rawTKA = floatval($tka['rata_rata_tka'] ?? 0);
        $factor = 7.5; 
        $skorSiswaSNBP = (($rawRapor * 0.70) + ($rawTKA * 0.30)) * $factor;

        $tryout = $this->db->queryOne("SELECT skor_total FROM nilai_tryout WHERE siswa_id = ? ORDER BY tanggal_tes DESC LIMIT 1", [$siswaId]);
        $skorSiswaSNBT = floatval($tryout['skor_total'] ?? 0);

        // 3. BERSIHKAN LAMA
        $this->db->execute("DELETE FROM rekomendasi WHERE siswa_id = ?", [$siswaId]);

        $count = 0;

        // 4. PROSES
        if ($skorSiswaSNBP > 0) $count += $this->processJalur($siswaId, 'SNBP', $skorSiswaSNBP, $pilihanSiswa);
        if ($skorSiswaSNBT > 0) $count += $this->processJalur($siswaId, 'SNBT', $skorSiswaSNBT, $pilihanSiswa);

        return $count;
    }

    private function processJalur($siswaId, $jalur, $skorSiswa, $pilihanIds)
    {
        $finalList = [];
        $usedProdiIds = [];

        // --- A. PILIHAN SISWA (PRIORITAS) ---
        foreach ($pilihanIds as $pid) {
            if (!$pid) continue;
            $prodi = $this->db->queryOne("SELECT p.id, p.passing_grade, p.nama as prodi_nama FROM prodi p WHERE p.id = ?", [$pid]);
            if ($prodi) {
                $analisis = $this->analyzePeluang($skorSiswa, $prodi['passing_grade']);
                $alasan = "[Minat] " . $analisis['strategi'] . ": " . $analisis['desc'];

                $finalList[] = [
                    'prodi_id' => $prodi['id'], 'jalur' => $jalur, 'skor' => $skorSiswa,
                    'peluang' => $analisis['label'], 'alasan' => $alasan,
                    'ranking' => count($finalList) + 1
                ];
                $usedProdiIds[] = $prodi['id'];
            }
        }

        // --- B. SISTEM (SMART PRIORITY) ---
        // Logika Baru: 
        // 1. Priority 1: PG <= Skor (Aman/Target)
        // 2. Priority 2: PG > Skor (Tantangan)
        // Diurutkan berdasarkan Priority dulu, baru selisih terkecil.
        
        $placeholderUsed = implode(',', array_merge($usedProdiIds, [0]));
        
        $candidates = $this->db->query("
            SELECT p.id, p.passing_grade, p.nama as prodi_nama,
                   (CASE WHEN p.passing_grade <= ? THEN 1 ELSE 2 END) as priority,
                   ABS(p.passing_grade - ?) as selisih
            FROM prodi p
            WHERE p.passing_grade > 0 
            AND p.id NOT IN ($placeholderUsed)
            ORDER BY priority ASC, selisih ASC
            LIMIT 500
        ", [$skorSiswa, $skorSiswa]);

        $sysSaintek = [];
        $sysSoshum = [];

        foreach ($candidates as $cand) {
            // Deteksi Rumpun
            $isSaintek = false;
            foreach ($this->keywordsSaintek as $k) { if (stripos($cand['prodi_nama'], $k) !== false) { $isSaintek = true; break; } }
            
            $isSoshum = false;
            if (!$isSaintek) {
                foreach ($this->keywordsSoshum as $k) { if (stripos($cand['prodi_nama'], $k) !== false) { $isSoshum = true; break; } }
                if (!$isSoshum) $isSoshum = true; 
            }

            // Cek Kuota (Maksimal 5 per rumpun)
            if ($isSaintek && count($sysSaintek) >= 5) continue;
            if ($isSoshum && count($sysSoshum) >= 5) continue;

            $analisis = $this->analyzePeluang($skorSiswa, $cand['passing_grade']);
            $alasan = "[System] " . $analisis['strategi'] . ": " . $analisis['desc'];

            $item = [
                'prodi_id' => $cand['id'], 'jalur' => $jalur, 'skor' => $skorSiswa,
                'peluang' => $analisis['label'], 'alasan' => $alasan, 'ranking' => 0
            ];

            if ($isSaintek) $sysSaintek[] = $item;
            elseif ($isSoshum) $sysSoshum[] = $item;

            // Berhenti jika keduanya penuh
            if (count($sysSaintek) >= 5 && count($sysSoshum) >= 5) break;
        }

        $finalList = array_merge($finalList, $sysSaintek, $sysSoshum);

        $savedCount = 0; $rank = 1;
        foreach ($finalList as $rec) {
            $this->db->execute("
                INSERT INTO rekomendasi (siswa_id, prodi_id, jalur, skor, peluang, alasan, ranking)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [$siswaId, $rec['prodi_id'], $rec['jalur'], $rec['skor'], $rec['peluang'], $rec['alasan'], $rank++]);
            $savedCount++;
        }
        return $savedCount;
    }

    private function analyzePeluang($skor, $pg)
    {
        if ($pg <= 0) return ['label' => 'Unknown', 'strategi' => '', 'desc' => ''];
        $ratio = $skor / $pg;
        $pgTxt = number_format($pg, 0);

        if ($ratio >= 1.10) {
            return ['label' => 'Tinggi', 'strategi' => 'AMAN', 'desc' => "Skor > PG ($pgTxt)"];
        } elseif ($ratio >= 1.00) {
            return ['label' => 'Sedang', 'strategi' => 'TARGET', 'desc' => "Skor = PG ($pgTxt)"];
        } else {
            return ['label' => 'Rendah', 'strategi' => 'TANTANGAN', 'desc' => "Skor < PG ($pgTxt)"];
        }
    }

    // --- PARSER MINAT (User Approved) ---
    private function parseMinatToProdiIds($strSaintek, $strSoshum)
    {
        $ids = [];
        $gabungan = explode('|', $strSaintek . '|' . $strSoshum);
        
        foreach ($gabungan as $str) {
            $str = trim($str);
            if (empty($str)) continue;

            $foundId = null;
            $lastDash = strrpos($str, '-');
            
            // Cara 1: Dengan Strip
            if ($lastDash !== false) {
                $prodiName = trim(substr($str, 0, $lastDash));
                $ptnName = trim(substr($str, $lastDash + 1)); 
                $res = $this->db->queryOne("SELECT p.id FROM prodi p JOIN ptn pt ON p.ptn_id = pt.id WHERE p.nama LIKE CONCAT('%', ?, '%') AND (pt.singkatan LIKE CONCAT('%', ?, '%') OR pt.nama LIKE CONCAT('%', ?, '%')) LIMIT 1", [$prodiName, $ptnName, $ptnName]);
                if ($res) $foundId = $res['id'];
            }
            // Cara 2: Tanpa Strip (Nama Prodi saja)
            if (!$foundId) {
                $res = $this->db->queryOne("SELECT id FROM prodi WHERE nama LIKE CONCAT('%', ?, '%') LIMIT 1", [$str]);
                if ($res) $foundId = $res['id'];
            }
            // Cara 3: Dua Kata Pertama
            if (!$foundId) {
                $words = explode(' ', $str);
                if (count($words) >= 2) {
                    $twoWords = $words[0] . ' ' . $words[1];
                    $res = $this->db->queryOne("SELECT id FROM prodi WHERE nama LIKE CONCAT('%', ?, '%') LIMIT 1", [$twoWords]);
                    if ($res) $foundId = $res['id'];
                }
            }
            if ($foundId) $ids[] = $foundId;
        }
        return array_unique($ids);
    }
}
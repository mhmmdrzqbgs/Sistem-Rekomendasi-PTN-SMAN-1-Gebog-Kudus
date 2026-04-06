<?php

/**
 * Admin - Import Data (Excel)
 * Features: Import Siswa, Rapor, TKA, Tryout, Data PTN & Prodi
 * Updated: UPSERT Logic + Kurikulum Merdeka Handling (Sejarah skipped in Sem 1-2)
 */
$pageTitle = 'Import Master Data';

// --- 1. MEMORY & TIME LIMIT TWEAK ---
ini_set('memory_limit', '1024M');
set_time_limit(300);

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$db = Database::getInstance();

// --- HELPER FUNCTIONS ---
function getSiswaId($db, $nisn)
{
    $nisn = trim((string)$nisn);
    if (empty($nisn)) return null;
    $user = $db->queryOne("SELECT id FROM users WHERE username = ?", [$nisn]);
    if ($user) {
        $profile = $db->queryOne("SELECT id FROM siswa_profile WHERE user_id = ?", [$user['id']]);
        return $profile ? $profile['id'] : null;
    }
    return null;
}

function getMapelMap($db)
{
    $mapels = $db->query("SELECT id, nama_mapel, kelompok FROM master_mapel");
    $map = [];
    foreach ($mapels as $m) {
        $key = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $m['nama_mapel'])));
        $map[$key] = [
            'id' => $m['id'],
            'kelompok' => $m['kelompok'],
            'nama_asli' => $m['nama_mapel']
        ];
    }
    return $map;
}

// --- AUTO GENERATE PTN ACRONYM ---
function generateSingkatan($namaPtn)
{
    if (preg_match('/\((.*?)\)/', $namaPtn, $match)) {
        return strtoupper(trim($match[1]));
    }
    $words = explode(' ', $namaPtn);
    $acronym = '';
    foreach ($words as $w) {
        $acronym .= strtoupper(substr($w, 0, 1));
    }
    return substr($acronym, 0, 10);
}

// --- HELPER FOR PTN ---
function getOrCreatePtnId($db, $namaPtn)
{
    $namaPtn = trim($namaPtn);
    if (empty($namaPtn)) return null;

    $ptn = $db->queryOne("SELECT id FROM ptn WHERE nama = ?", [$namaPtn]);

    if ($ptn) {
        return $ptn['id'];
    } else {
        $singkatan = generateSingkatan($namaPtn);
        $cekSingkatan = $db->queryOne("SELECT id FROM ptn WHERE singkatan = ?", [$singkatan]);
        if ($cekSingkatan) {
            $singkatan .= rand(1, 99);
        }
        $sql = "INSERT INTO ptn (nama, singkatan, jenis) VALUES (?, ?, 'Negeri')";
        $db->execute($sql, [$namaPtn, $singkatan]);
        return $db->lastInsertId();
    }
}

// --- GENERATE TEMPLATE PRODI ---
if (isset($_GET['action']) && $_GET['action'] == 'download_template_prodi') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $headers = ['Program Studi', 'PTN', 'Jenjang', 'Kelompok', 'Passing Grade (Estimasi)', 'Daya Tampung (SNBP)', 'Daya Tampung (SNBT)'];
    $sheet->fromArray($headers, NULL, 'A1');
    $example = [
        ['Kedokteran', 'Universitas Indonesia (UI)', 'S1', 'Saintek', '720', '50', '80'],
        ['Manajemen', 'Universitas Gadjah Mada (UGM)', 'S1', 'Soshum', '680', '40', '60']
    ];
    $sheet->fromArray($example, NULL, 'A2');
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    foreach (range('A', 'G') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Template_Data_Prodi_PTN.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// --- IMPORT PROCESS ---
if (isPost() && isset($_FILES['file_excel'])) {
    $jenisImport = post('jenis_import');
    $file = $_FILES['file_excel']['tmp_name'];

    if (!$file) {
        setFlash('message', ['type' => 'warning', 'message' => 'Pilih file Excel terlebih dahulu.']);
    } else {
        try {
            $inputFileType = IOFactory::identify($file);
            $reader = IOFactory::createReader($inputFileType);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file);

            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $headerRaw = array_shift($rows);
            $header = [];
            foreach ($headerRaw as $idx => $val) {
                if ($val) {
                    $cleanHeader = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $val)));
                    $header[$cleanHeader] = $idx;
                }
            }

            $db->execute("START TRANSACTION");
            $countSukses = 0;
            $countUpdated = 0;
            $countInserted = 0;
            $errors = [];

            // 1. IMPORT DATA SISWA (UPSERT)
            if ($jenisImport === 'siswa') {
                if (!isset($header['nisn']) || !isset($header['namalengkap'])) {
                    throw new Exception("Header Excel tidak valid! Gunakan Template Data Siswa.");
                }
                foreach ($rows as $idx => $row) {
                    $nisn = isset($header['nisn']) ? trim((string)$row[$header['nisn']]) : '';
                    if (empty($nisn)) continue;

                    $nama = isset($header['namalengkap']) ? $row[$header['namalengkap']] : '';
                    $kelas = isset($header['kelas']) ? $row[$header['kelas']] : '';
                    $rumpun = isset($header['rumpun']) ? strtoupper(trim((string)$row[$header['rumpun']])) : '';
                    $sekolah = isset($header['sekolahasal']) ? $row[$header['sekolahasal']] : '';

                    $passRaw = isset($header['password']) ? trim((string)$row[$header['password']]) : '';
                    if (empty($passRaw)) {
                        $passRaw = (strlen($nisn) > 6) ? substr($nisn, -6) : $nisn;
                    }
                    $pass = password_hash($passRaw, PASSWORD_BCRYPT);

                    $cekUser = $db->queryOne("SELECT id FROM users WHERE username = ?", [$nisn]);

                    if (!$cekUser) {
                        $db->execute("INSERT INTO users (username, password, role, nama) VALUES (?, ?, 'siswa', ?)", [$nisn, $pass, $nama]);
                        $userId = $db->lastInsertId();
                        $db->execute("INSERT INTO siswa_profile (user_id, nisn, kelas, kode_rumpun, asal_sekolah) VALUES (?, ?, ?, ?, ?)", [$userId, $nisn, $kelas, $rumpun, $sekolah]);
                        $countInserted++;
                    } else {
                        $userId = $cekUser['id'];
                        $db->execute("UPDATE users SET nama = ? WHERE id = ?", [$nama, $userId]);

                        $cekProfile = $db->queryOne("SELECT id FROM siswa_profile WHERE user_id = ?", [$userId]);
                        if ($cekProfile) {
                            $db->execute("UPDATE siswa_profile SET kelas = ?, kode_rumpun = ?, asal_sekolah = ? WHERE user_id = ?", [$kelas, $rumpun, $sekolah, $userId]);
                        } else {
                            $db->execute("INSERT INTO siswa_profile (user_id, nisn, kelas, kode_rumpun, asal_sekolah) VALUES (?, ?, ?, ?, ?)", [$userId, $nisn, $kelas, $rumpun, $sekolah]);
                        }
                        $countUpdated++;
                    }
                    $countSukses++;
                }
            }

            // 2. IMPORT RAPOR (UPSERT & LOGIC KURIKULUM MERDEKA)
            elseif ($jenisImport === 'rapor') {
                if (!isset($header['nisn']) || !isset($header['semester'])) {
                    throw new Exception("Header Excel tidak valid! Gunakan Template Nilai Rapor.");
                }
                $mapelData = getMapelMap($db);
                $colToMapel = [];
                foreach ($header as $colName => $idx) {
                    if (isset($mapelData[$colName])) $colToMapel[$idx] = $mapelData[$colName];
                }

                foreach ($rows as $idx => $row) {
                    $nisn = trim((string)$row[$header['nisn']]);
                    if (empty($nisn)) continue;
                    $semester = $row[$header['semester']];
                    $siswaId = getSiswaId($db, $nisn);
                    if (!$siswaId) {
                        $errors[] = "Baris " . ($idx + 2) . ": NISN $nisn tidak ditemukan.";
                        continue;
                    }

                    $totalNilai = 0;
                    $jmlMapel = 0;

                    // -- CALCULATE AVERAGE (WITH FILTER LOGIC) --
                    foreach ($colToMapel as $colIdx => $mData) {
                        $val = isset($row[$colIdx]) ? floatval($row[$colIdx]) : 0;
                        $namaMapel = $mData['nama_asli'];

                        // FILTER LOGIC
                        if ($semester <= 2 && stripos($namaMapel, 'Sejarah') !== false) continue;
                        if ($semester > 2 && (stripos($namaMapel, 'IPA') !== false || stripos($namaMapel, 'IPS') !== false) && stripos($namaMapel, 'Informatika') === false) continue;

                        if ($val > 0) {
                            $totalNilai += $val;
                            $jmlMapel++;
                        }
                    }
                    $rataRata = $jmlMapel > 0 ? $totalNilai / $jmlMapel : 0;

                    // UPSERT HEADER RAPOR
                    $rapor = $db->queryOne("SELECT id, kode_rumpun FROM nilai_rapor WHERE siswa_id = ? AND semester = ?", [$siswaId, $semester]);
                    if ($rapor) {
                        $raporId = $rapor['id'];
                        $rumpunAktif = $rapor['kode_rumpun'];
                        $db->execute("UPDATE nilai_rapor SET rata_rata = ? WHERE id = ?", [$rataRata, $raporId]);
                        $countUpdated++;
                    } else {
                        $profil = $db->queryOne("SELECT kode_rumpun FROM siswa_profile WHERE id = ?", [$siswaId]);
                        $rumpunAktif = $profil['kode_rumpun'] ?? null;
                        $db->execute("INSERT INTO nilai_rapor (siswa_id, semester, rata_rata, kode_rumpun) VALUES (?, ?, ?, ?)", [$siswaId, $semester, $rataRata, $rumpunAktif]);
                        $raporId = $db->lastInsertId();
                        $countInserted++;
                    }

                    // -- SAVE DETAIL VALUES --
                    foreach ($colToMapel as $colIdx => $mData) {
                        $nilai = isset($row[$colIdx]) ? floatval($row[$colIdx]) : 0;
                        $mid = $mData['id'];
                        $namaMapel = $mData['nama_asli'];

                        // 1. SKIP LOGIC (CONSISTENT WITH INPUT FORM)
                        if ($semester <= 2 && stripos($namaMapel, 'Sejarah') !== false) continue;
                        if ($semester > 2 && (stripos($namaMapel, 'IPA') !== false || stripos($namaMapel, 'IPS') !== false) && stripos($namaMapel, 'Informatika') === false) continue;

                        $detail = $db->queryOne("SELECT id FROM nilai_rapor_detail WHERE nilai_rapor_id = ? AND master_mapel_id = ?", [$raporId, $mid]);
                        if ($detail) {
                            $db->execute("UPDATE nilai_rapor_detail SET nilai = ? WHERE id = ?", [$nilai, $detail['id']]);
                        } else {
                            $db->execute("INSERT INTO nilai_rapor_detail (nilai_rapor_id, master_mapel_id, nilai) VALUES (?, ?, ?)", [$raporId, $mid, $nilai]);
                        }

                        // 2. LOGIC MAPEL PILIHAN (Add to siswa_mapel_pilihan)
                        // Informatika di Sem 1-2 tidak masuk sini karena Sem <= 2
                        if ($nilai > 0 && $mData['kelompok'] == 'Pilihan' && $semester > 2) {

                            $isRumpun = false;
                            if ($rumpunAktif) {
                                $cekRumpun = $db->queryOne("SELECT id FROM paket_rumpun WHERE kode_rumpun = ? AND master_mapel_id = ?", [$rumpunAktif, $mid]);
                                if ($cekRumpun) $isRumpun = true;
                            }

                            // Jika bukan bagian paket wajib, berarti pilihan bebas
                            if (!$isRumpun) {
                                $cekManual = $db->queryOne("SELECT id FROM siswa_mapel_pilihan WHERE siswa_id = ? AND master_mapel_id = ?", [$siswaId, $mid]);
                                if (!$cekManual) {
                                    $db->execute("INSERT INTO siswa_mapel_pilihan (siswa_id, master_mapel_id, tingkat) VALUES (?, ?, 11)", [$siswaId, $mid]);
                                }
                            }
                        }
                    }
                    $countSukses++;
                }
            }

            // 3. IMPORT NILAI TKA (UPSERT)
            elseif ($jenisImport === 'tka') {
                if (!isset($header['nisn']) || !isset($header['mtk']) || !isset($header['indo'])) {
                    throw new Exception("Header Excel tidak valid! Gunakan Template Nilai TKA.");
                }

                foreach ($rows as $idx => $row) {
                    $nisn = trim((string)$row[$header['nisn']]);
                    if (empty($nisn)) continue;

                    $siswaId = getSiswaId($db, $nisn);
                    if (!$siswaId) {
                        $errors[] = "Baris " . ($idx + 2) . ": NISN $nisn tidak ditemukan.";
                        continue;
                    }

                    $mtk = isset($header['mtk']) ? floatval($row[$header['mtk']]) : 0;
                    $indo = isset($header['indo']) ? floatval($row[$header['indo']]) : 0;
                    $ing = isset($header['ing']) ? floatval($row[$header['ing']]) : 0;

                    $pil1Name = isset($header['mapelpilihan1']) ? trim($row[$header['mapelpilihan1']]) : '';
                    $pil1Val  = isset($header['nilaipil1']) ? floatval($row[$header['nilaipil1']]) : 0;

                    $pil2Name = isset($header['mapelpilihan2']) ? trim($row[$header['mapelpilihan2']]) : '';
                    $pil2Val  = isset($header['nilaipil2']) ? floatval($row[$header['nilaipil2']]) : 0;

                    $total = $mtk + $indo + $ing + $pil1Val + $pil2Val;
                    $pembagi = 3 + ($pil1Val > 0 ? 1 : 0) + ($pil2Val > 0 ? 1 : 0);
                    $rataRata = ($pembagi > 0) ? ($total / $pembagi) : 0;

                    $cekTka = $db->queryOne("SELECT id FROM nilai_tka WHERE siswa_id = ?", [$siswaId]);
                    if ($cekTka) {
                        $db->execute("UPDATE nilai_tka SET nilai_mtk=?, nilai_indo=?, nilai_inggris=?, mapel_pilihan_1=?, nilai_pilihan_1=?, mapel_pilihan_2=?, nilai_pilihan_2=?, rata_rata_tka=? WHERE id=?", [$mtk, $indo, $ing, $pil1Name, $pil1Val, $pil2Name, $pil2Val, $rataRata, $cekTka['id']]);
                        $countUpdated++;
                    } else {
                        $db->execute("INSERT INTO nilai_tka (siswa_id, nilai_mtk, nilai_indo, nilai_inggris, mapel_pilihan_1, nilai_pilihan_1, mapel_pilihan_2, nilai_pilihan_2, rata_rata_tka) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [$siswaId, $mtk, $indo, $ing, $pil1Name, $pil1Val, $pil2Name, $pil2Val, $rataRata]);
                        $countInserted++;
                    }
                    $countSukses++;
                }
            }

            // 4. IMPORT TRYOUT (UPSERT)
            elseif ($jenisImport === 'tryout') {
                if (!isset($header['nisn'])) throw new Exception("Header Excel tidak valid! Gunakan Template Tryout.");
                foreach ($rows as $idx => $row) {
                    $nisn = trim((string)$row[$header['nisn']]);
                    if (empty($nisn)) continue;
                    $siswaId = getSiswaId($db, $nisn);
                    if (!$siswaId) continue;

                    $tgl = isset($header['tanggaltes']) ? date('Y-m-d', strtotime($row[$header['tanggaltes']])) : date('Y-m-d');
                    $getVal = function ($key) use ($header, $row) {
                        return isset($header[$key]) ? floatval($row[$header[$key]]) : 0;
                    };

                    $pu = $getVal('penalaranumum');
                    $ppu = $getVal('pengetahuandanpemahamanumum');
                    $pbm = $getVal('pemahamanbacaandanmenulis');
                    $pk = $getVal('pengetahuankuantitatif');
                    $indo = $getVal('literasibahasaindonesia');
                    $ing = $getVal('literasibahasainggris');
                    $pm = $getVal('penalaranmatematika');
                    $catatan = isset($header['catatan']) ? $row[$header['catatan']] : 'Import Excel';
                    $total = ($pu + $ppu + $pbm + $pk + $indo + $ing + $pm) / 7;

                    $cekTO = $db->queryOne("SELECT id FROM nilai_tryout WHERE siswa_id = ? AND tanggal_tes = ?", [$siswaId, $tgl]);

                    if ($cekTO) {
                        $db->execute(
                            "UPDATE nilai_tryout SET pu=?, ppu=?, pbm=?, pk=?, lit_indo=?, lit_ing=?, pm=?, skor_total=?, catatan=? WHERE id=?",
                            [$pu, $ppu, $pbm, $pk, $indo, $ing, $pm, $total, $catatan, $cekTO['id']]
                        );
                        $countUpdated++;
                    } else {
                        $db->execute(
                            "INSERT INTO nilai_tryout (siswa_id, tryout_ke, jenis, tanggal_tes, pu, ppu, pbm, pk, lit_indo, lit_ing, pm, skor_total, catatan) VALUES (?, 1, 'SNBT', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [$siswaId, $tgl, $pu, $ppu, $pbm, $pk, $indo, $ing, $pm, $total, $catatan]
                        );
                        $countInserted++;
                    }
                    $countSukses++;
                }
            }

            // 5. IMPORT PRODI (UPSERT)
            elseif ($jenisImport === 'prodi') {
                if (!isset($header['programstudi']) || !isset($header['ptn'])) {
                    throw new Exception("Header Excel tidak valid! Pastikan ada kolom 'Program Studi' dan 'PTN'.");
                }

                foreach ($rows as $idx => $row) {
                    $namaProdi = isset($header['programstudi']) ? trim((string)$row[$header['programstudi']]) : '';
                    $namaPtn = isset($header['ptn']) ? trim((string)$row[$header['ptn']]) : '';

                    if (empty($namaProdi) || empty($namaPtn)) continue;

                    $ptnId = getOrCreatePtnId($db, $namaPtn);

                    $jenjang = isset($header['jenjang']) ? trim((string)$row[$header['jenjang']]) : 'S1';
                    $kelompok = isset($header['kelompok']) ? trim((string)$row[$header['kelompok']]) : 'Saintek';
                    $pg = isset($header['passinggradeestimasi']) ? floatval($row[$header['passinggradeestimasi']]) : 0;
                    $dtSnbp = isset($header['dayatampungsnbp']) ? intval($row[$header['dayatampungsnbp']]) : 0;
                    $dtSnbt = isset($header['dayatampungsnbt']) ? intval($row[$header['dayatampungsnbt']]) : 0;

                    $prodi = $db->queryOne("SELECT id FROM prodi WHERE ptn_id = ? AND nama = ?", [$ptnId, $namaProdi]);

                    if ($prodi) {
                        $db->execute(
                            "UPDATE prodi SET passing_grade = ?, daya_tampung_snbp = ?, daya_tampung_snbt = ?, jenjang = ? WHERE id = ?",
                            [$pg, $dtSnbp, $dtSnbt, $jenjang, $prodi['id']]
                        );
                        $countUpdated++;
                    } else {
                        $db->execute(
                            "INSERT INTO prodi (ptn_id, nama, jenjang, rumpun, passing_grade, daya_tampung_snbp, daya_tampung_snbt) VALUES (?, ?, ?, ?, ?, ?, ?)",
                            [$ptnId, $namaProdi, $jenjang, $kelompok, $pg, $dtSnbp, $dtSnbt]
                        );
                        $countInserted++;
                    }
                    $countSukses++;
                }
            }

            $db->execute("COMMIT");

            $msg = "<b>Import Selesai!</b><br>";
            if ($countInserted > 0) $msg .= "✅ $countInserted data baru ditambahkan.<br>";
            if ($countUpdated > 0) $msg .= "🔄 $countUpdated data diperbarui.<br>";
            $msg .= "Total diproses: $countSukses baris.";

            if (!empty($errors)) {
                $msg .= "<br><br><b>⚠️ Perhatian:</b><ul style='font-size:12px;text-align:left;'>";
                foreach (array_slice($errors, 0, 5) as $e) $msg .= "<li>$e</li>";
                $msg .= "</ul>";
                setFlash('message', ['type' => 'warning', 'message' => $msg]);
            } else {
                setFlash('message', ['type' => 'success', 'message' => $msg]);
            }
        } catch (Exception $e) {
            $db->execute("ROLLBACK");

            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, '1364') !== false) {
                $errorMsg = "Gagal: Database menolak data kosong. Pastikan SQL ALTER TABLE sudah dijalankan.";
            } elseif (strpos($errorMsg, '1054') !== false) {
                $errorMsg = "Gagal: Kolom database belum update. Periksa nama kolom.";
            }

            setFlash('message', ['type' => 'error', 'message' => $errorMsg]);
        }
    }
    redirect('import-data.php');
}

require_once __DIR__ . '/../templates/header-admin.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50 flex justify-between items-center">
                <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-file-excel text-emerald-500"></i> Form Import Data
                </h3>
            </div>

            <div class="p-6">
                <form id="importForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">1. Pilih Jenis Data:</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                            <label class="cursor-pointer group">
                                <input type="radio" name="jenis_import" value="siswa" checked class="peer sr-only" onchange="updateTemplateBtn('siswa')">
                                <div class="p-3 rounded-xl border-2 border-slate-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20 hover:border-blue-300 transition h-full text-center flex flex-col items-center justify-center">
                                    <div class="w-8 h-8 mb-2 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center"><i class="fas fa-user-graduate"></i></div>
                                    <div class="text-[10px] font-bold text-slate-700 dark:text-white">Siswa</div>
                                </div>
                            </label>
                            <label class="cursor-pointer group">
                                <input type="radio" name="jenis_import" value="rapor" class="peer sr-only" onchange="updateTemplateBtn('rapor')">
                                <div class="p-3 rounded-xl border-2 border-slate-200 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 dark:peer-checked:bg-emerald-900/20 hover:border-emerald-300 transition h-full text-center flex flex-col items-center justify-center">
                                    <div class="w-8 h-8 mb-2 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center"><i class="fas fa-book"></i></div>
                                    <div class="text-[10px] font-bold text-slate-700 dark:text-white">Rapor</div>
                                </div>
                            </label>
                            <label class="cursor-pointer group">
                                <input type="radio" name="jenis_import" value="tka" class="peer sr-only" onchange="updateTemplateBtn('tka')">
                                <div class="p-3 rounded-xl border-2 border-slate-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 dark:peer-checked:bg-amber-900/20 hover:border-amber-300 transition h-full text-center flex flex-col items-center justify-center">
                                    <div class="w-8 h-8 mb-2 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center"><i class="fas fa-file-contract"></i></div>
                                    <div class="text-[10px] font-bold text-slate-700 dark:text-white">TKA</div>
                                </div>
                            </label>
                            <label class="cursor-pointer group">
                                <input type="radio" name="jenis_import" value="tryout" class="peer sr-only" onchange="updateTemplateBtn('tryout')">
                                <div class="p-3 rounded-xl border-2 border-slate-200 peer-checked:border-rose-500 peer-checked:bg-rose-50 dark:peer-checked:bg-rose-900/20 hover:border-rose-300 transition h-full text-center flex flex-col items-center justify-center">
                                    <div class="w-8 h-8 mb-2 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center"><i class="fas fa-edit"></i></div>
                                    <div class="text-[10px] font-bold text-slate-700 dark:text-white">Tryout</div>
                                </div>
                            </label>
                            <label class="cursor-pointer group">
                                <input type="radio" name="jenis_import" value="prodi" class="peer sr-only" onchange="updateTemplateBtn('prodi')">
                                <div class="p-3 rounded-xl border-2 border-slate-200 peer-checked:border-purple-500 peer-checked:bg-purple-50 dark:peer-checked:bg-purple-900/20 hover:border-purple-300 transition h-full text-center flex flex-col items-center justify-center">
                                    <div class="w-8 h-8 mb-2 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center"><i class="fas fa-university"></i></div>
                                    <div class="text-[10px] font-bold text-slate-700 dark:text-white">Data Prodi</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">2. Unduh Template:</label>
                        <a id="btnDownload" href="../assets/templates/Template Excel Data Siswa.xlsx" download class="inline-flex items-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg border border-slate-300 transition text-sm font-medium">
                            <i class="fas fa-download mr-2"></i> Download Template Excel
                        </a>
                        <p class="text-xs text-slate-500 mt-1">*Pastikan menggunakan template yang sesuai agar data terbaca.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">3. Upload File yang sudah diisi:</label>
                        <div id="dropZone" class="relative border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-6 text-center hover:bg-slate-50 dark:hover:bg-slate-700/50 transition cursor-pointer group">
                            <input type="file" id="fileInput" name="file_excel" accept=".xlsx, .xls" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <div id="fileInfo" class="text-slate-500 pointer-events-none">
                                <i class="fas fa-cloud-upload-alt text-4xl mb-2 text-blue-400 group-hover:scale-110 transition-transform"></i>
                                <p class="text-sm font-medium">Klik atau seret file Excel ke sini</p>
                                <p class="text-xs mt-1">Format: .xlsx atau .xls</p>
                            </div>
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="button" onclick="confirmImport()" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-500/30 transition transform active:scale-95 flex justify-center items-center gap-2">
                            <i class="fas fa-file-import"></i> Mulai Proses Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-1">
        <div class="bg-indigo-50 dark:bg-slate-800 rounded-xl border border-indigo-100 dark:border-slate-700 shadow-sm p-6 sticky top-24">
            <h4 class="font-bold text-indigo-900 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-lightbulb text-amber-400"></i> Format Kolom
            </h4>
            <div class="space-y-4 text-xs">
                <div id="guide-siswa" class="guide-box">
                    <div class="bg-white dark:bg-slate-700/50 p-3 rounded-lg border border-indigo-100 dark:border-slate-600">
                        <strong class="text-indigo-600 dark:text-indigo-400 block mb-2 border-b pb-1">Data Siswa</strong>
                        <table class="w-full text-left">
                            <tr class="border-b border-slate-100 dark:border-slate-600">
                                <td class="font-semibold py-1 w-28">Nama Lengkap</td>
                                <td class="text-slate-600">: Wajib</td>
                            </tr>
                            <tr class="border-b border-slate-100 dark:border-slate-600">
                                <td class="font-semibold py-1">NISN</td>
                                <td class="text-slate-600">: Wajib (Unik)</td>
                            </tr>
                            <tr class="border-b border-slate-100 dark:border-slate-600">
                                <td class="font-semibold py-1">Password</td>
                                <td class="text-slate-600">: Opsional (Auto)</td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-1">Kelas</td>
                                <td class="text-slate-600">: Contoh "XII F-1"</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div id="guide-rapor" class="guide-box hidden">
                    <div class="bg-white dark:bg-slate-700/50 p-3 rounded-lg border border-emerald-100 dark:border-slate-600">
                        <strong class="text-emerald-600 dark:text-emerald-400 block mb-2 border-b pb-1">Nilai Rapor</strong>
                        <table class="w-full text-left">
                            <tr>
                                <td class="font-semibold w-20">NISN</td>
                                <td>: Wajib</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Semester</td>
                                <td>: 1 - 6</td>
                            </tr>
                            <tr>
                                <td colspan="2" class="pt-2 italic text-slate-500">
                                    <b>Catatan Penting:</b><br>
                                    - Sem 1 & 2: Kosongkan nilai Sejarah.<br>
                                    - Sem 3+: Isi Sejarah & Informatika (jika ada).
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div id="guide-tka" class="guide-box hidden">
                    <div class="bg-white dark:bg-slate-700/50 p-3 rounded-lg border border-amber-100 dark:border-slate-600">
                        <strong class="text-amber-600 dark:text-amber-400 block mb-2 border-b pb-1">Nilai TKA</strong>
                        <table class="w-full text-left">
                            <tr>
                                <td class="font-semibold w-20">NISN</td>
                                <td>: Wajib</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">MTK, Indo, Ing</td>
                                <td>: Nilai Wajib</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Mapel Pil 1</td>
                                <td>: Nama Mapel</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Nilai Pil 1</td>
                                <td>: Angka</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div id="guide-tryout" class="guide-box hidden">
                    <div class="bg-white dark:bg-slate-700/50 p-3 rounded-lg border border-rose-100 dark:border-slate-600">
                        <strong class="text-rose-600 dark:text-rose-400 block mb-2 border-b pb-1">Nilai Tryout</strong>
                        <table class="w-full text-left">
                            <tr>
                                <td class="font-semibold w-24">NISN</td>
                                <td>: Wajib</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Tanggal Tes</td>
                                <td>: YYYY-MM-DD</td>
                            </tr>
                            <tr>
                                <td colspan="2" class="pt-2">Isi nilai subtes: PU, PPU, PBM, PK, Lit Indo, Lit Ing, PM.</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div id="guide-prodi" class="guide-box hidden">
                    <div class="bg-white dark:bg-slate-700/50 p-3 rounded-lg border border-purple-100 dark:border-slate-600">
                        <strong class="text-purple-600 dark:text-purple-400 block mb-2 border-b pb-1">Data Prodi</strong>
                        <table class="w-full text-left">
                            <tr>
                                <td class="font-semibold w-28">Program Studi</td>
                                <td>: Wajib</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">PTN</td>
                                <td>: Wajib</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Passing Grade</td>
                                <td>: Angka</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Daya Tampung</td>
                                <td>: Angka</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('fileInput').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name;
        const dropZone = document.getElementById('dropZone');
        const fileInfo = document.getElementById('fileInfo');
        if (fileName) {
            dropZone.classList.remove('border-slate-300', 'dark:border-slate-600');
            dropZone.classList.add('border-emerald-500', 'bg-emerald-50', 'dark:bg-emerald-900/20');
            fileInfo.innerHTML = `<i class="fas fa-check-circle text-4xl mb-2 text-emerald-500 animate-bounce"></i><p class="text-sm font-bold text-slate-700 dark:text-white truncate px-4">${fileName}</p><p class="text-xs mt-1 text-emerald-600 font-medium">File siap diproses</p>`;
        } else {
            dropZone.classList.add('border-slate-300', 'dark:border-slate-600');
            dropZone.classList.remove('border-emerald-500', 'bg-emerald-50', 'dark:bg-emerald-900/20');
            fileInfo.innerHTML = `<i class="fas fa-cloud-upload-alt text-4xl mb-2 text-blue-400 group-hover:scale-110 transition-transform"></i><p class="text-sm font-medium">Klik atau seret file Excel ke sini</p><p class="text-xs mt-1">Format: .xlsx atau .xls</p>`;
        }
    });

    function updateTemplateBtn(type) {
        const btn = document.getElementById('btnDownload');
        if (type === 'siswa') btn.href = '../assets/templates/Template Excel Data Siswa.xlsx';
        else if (type === 'rapor') btn.href = '../assets/templates/Template Excel Nilai Rapot.xlsx';
        else if (type === 'tka') btn.href = '../assets/templates/Template Excel Nilai TKA.xlsx';
        else if (type === 'prodi') btn.href = 'import-data.php?action=download_template_prodi';
        else btn.href = 'download-template.php?type=' + type;

        document.querySelectorAll('.guide-box').forEach(el => el.classList.add('hidden'));
        document.getElementById('guide-' + type).classList.remove('hidden');
    }

    function confirmImport() {
        const fileInput = document.getElementById('fileInput');
        if (!fileInput.value) {
            Swal.fire({
                icon: 'warning',
                title: 'File Kosong',
                text: 'Silakan pilih file Excel terlebih dahulu!'
            });
            return;
        }
        Swal.fire({
            title: 'Mulai Import?',
            text: 'Pastikan format kolom Excel sudah sesuai template yang dipilih.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Ya, Proses Sekarang'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Data...',
                    html: 'Mohon tunggu sebentar, jangan tutup halaman ini.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading()
                    }
                });
                document.getElementById('importForm').submit();
            }
        });
    }

    <?php
    $flash = getFlash('message');
    if ($flash):
        if (is_array($flash)) {
            $type = $flash['type'] ?? 'info';
            $msg = $flash['message'] ?? '';
        } else {
            $type = 'info';
            $msg = $flash;
        }
    ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Informasi',
                html: `<?= $msg ?>`,
                icon: '<?= $type ?>',
                confirmButtonText: 'OK'
            });
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
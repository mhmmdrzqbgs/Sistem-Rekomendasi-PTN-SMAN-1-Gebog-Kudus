<?php
/**
 * Admin - Maintenance / Perbaikan Data
 * Fitur: Hitung ulang rata-rata rapor sesuai logika Kurikulum Merdeka
 */
$pageTitle = 'Perbaikan Data';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

$message = '';

if (isset($_POST['recalculate_avg'])) {
    $db->execute("START TRANSACTION");
    try {
        // 1. Ambil Semua Data Rapor
        $allRapor = $db->query("SELECT id, siswa_id, semester FROM nilai_rapor");
        $countUpdated = 0;

        foreach ($allRapor as $rapor) {
            $raporId = $rapor['id'];
            $semester = $rapor['semester'];

            // 2. Ambil Detail Nilai
            $details = $db->query("
                SELECT nrd.nilai, mm.nama_mapel 
                FROM nilai_rapor_detail nrd
                JOIN master_mapel mm ON nrd.master_mapel_id = mm.id
                WHERE nrd.nilai_rapor_id = ?
            ", [$raporId]);

            $totalNilai = 0;
            $jumlahMapel = 0;

            foreach ($details as $d) {
                $nilai = floatval($d['nilai']);
                $nama = $d['nama_mapel'];

                // --- LOGIKA FILTER (SAMA SEPERTI DI IMPORT & INPUT) ---
                
                // 1. Semester 1 & 2: HILANGKAN SEJARAH
                if ($semester <= 2 && stripos($nama, 'Sejarah') !== false) {
                    continue; // Jangan dihitung
                }

                // 2. Semester 3 - 6: HILANGKAN IPA & IPS (Gantinya Fisika, Eko, dll)
                // Kecuali jika mapel itu Informatika (krn informatika bisa jd pilihan)
                if ($semester > 2 && (stripos($nama, 'IPA') !== false || stripos($nama, 'IPS') !== false) && stripos($nama, 'Informatika') === false) {
                    continue; // Jangan dihitung
                }

                // 3. Pastikan Nilai Valid (> 0)
                if ($nilai > 0) {
                    $totalNilai += $nilai;
                    $jumlahMapel++;
                }
            }

            // 3. Hitung Rata-rata Baru
            $rataBaru = ($jumlahMapel > 0) ? ($totalNilai / $jumlahMapel) : 0;

            // 4. Update ke Database
            $db->execute("UPDATE nilai_rapor SET rata_rata = ? WHERE id = ?", [$rataBaru, $raporId]);
            $countUpdated++;
        }

        $db->execute("COMMIT");
        $message = "<div class='bg-emerald-100 text-emerald-800 p-4 rounded-lg mb-4'>Berhasil menghitung ulang rata-rata untuk <b>$countUpdated</b> data rapor.</div>";

    } catch (Exception $e) {
        $db->execute("ROLLBACK");
        $message = "<div class='bg-rose-100 text-rose-800 p-4 rounded-lg mb-4'>Gagal: " . $e->getMessage() . "</div>";
    }
}

require_once __DIR__ . '/../templates/header-admin.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-bold text-lg text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-tools text-slate-500"></i> Perbaikan Data & Kalibrasi
            </h3>
        </div>
        
        <div class="p-6">
            <?= $message ?>

            <div class="space-y-6">
                <div class="flex items-start gap-4 p-4 border border-blue-100 bg-blue-50/50 dark:bg-blue-900/10 dark:border-blue-800 rounded-xl">
                    <div class="p-3 bg-blue-100 text-blue-600 rounded-lg dark:bg-blue-800 dark:text-blue-200">
                        <i class="fas fa-calculator text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold text-slate-800 dark:text-white">Hitung Ulang Rata-Rata Rapor</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1 mb-3">
                            Fitur ini akan menghitung ulang kolom <code>rata_rata</code> di database berdasarkan detail nilai yang ada.
                            <br><strong>Logika Baru Diterapkan:</strong>
                            <ul class="list-disc ml-5 mt-1 text-xs text-slate-500">
                                <li>Semester 1 & 2: Mengabaikan nilai Sejarah.</li>
                                <li>Semester 3 - 6: Mengabaikan nilai IPA & IPS (Gabungan).</li>
                                <li>Hanya menghitung mapel yang memiliki nilai > 0.</li>
                            </ul>
                        </p>
                        <form method="POST">
                            <button type="submit" name="recalculate_avg" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition shadow-sm" onclick="return confirm('Proses ini mungkin memakan waktu beberapa detik. Lanjutkan?')">
                                <i class="fas fa-sync-alt mr-2"></i> Proses Kalibrasi Sekarang
                            </button>
                        </form>
                    </div>
                </div>

                <div class="text-center text-xs text-slate-400 mt-8">
                    Gunakan fitur ini jika Anda merasa nilai rata-rata di Dashboard atau Rekomendasi tidak sesuai dengan inputan manual / Excel.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
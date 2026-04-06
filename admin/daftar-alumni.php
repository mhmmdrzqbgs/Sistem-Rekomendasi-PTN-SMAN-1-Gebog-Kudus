<?php

/**
 * Admin - Daftar Alumni
 * Fitur: Menampilkan siswa berstatus 'Lulus', Filter Tahun, Pencarian
 * Updated: Formal Print Layout with Kop Surat
 */
$pageTitle = 'Daftar Alumni';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

// --- LOGIC FILTER ---
$yearFilter = get('year');
$search = get('search');

// Base Query
$sql = "SELECT sp.*, u.nama 
        FROM siswa_profile sp 
        JOIN users u ON sp.user_id = u.id 
        WHERE sp.status = 'Lulus'";

$params = [];

// Filter Tahun
if ($yearFilter) {
    $sql .= " AND sp.tahun_lulus = ?";
    $params[] = $yearFilter;
}

// Filter Pencarian
if ($search) {
    $sql .= " AND (u.nama LIKE ? OR sp.nisn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ORDER BY: Tahun Lulus (Desc), Panjang Kelas (Asc), Nama Kelas (Asc), Nama Siswa (Asc)
$sql .= " ORDER BY sp.tahun_lulus DESC, LENGTH(sp.kelas) ASC, sp.kelas ASC, u.nama ASC";

$alumniList = $db->query($sql, $params);

// Ambil Daftar Tahun Lulus untuk Dropdown
$years = $db->query("SELECT DISTINCT tahun_lulus FROM siswa_profile WHERE status = 'Lulus' AND tahun_lulus IS NOT NULL ORDER BY tahun_lulus DESC");

require_once __DIR__ . '/../templates/header-admin.php';
?>

<div class="space-y-6">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 print:hidden">
        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div>
                <p class="text-sm text-slate-500 dark:text-slate-400">Total Alumni</p>
                <h3 class="text-2xl font-bold text-slate-800 dark:text-white"><?= count($alumniList) ?></h3>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">

        <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex flex-col md:flex-row justify-between items-center gap-4 print:hidden">
            <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-list text-blue-500"></i> Data Alumni
            </h3>

            <form method="GET" class="flex flex-wrap gap-2 w-full md:w-auto">
                <select name="year" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                    <option value="">-- Semua Angkatan --</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y['tahun_lulus'] ?>" <?= $yearFilter == $y['tahun_lulus'] ? 'selected' : '' ?>>
                            Lulusan <?= $y['tahun_lulus'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="relative">
                    <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Cari Nama / NISN..."
                        class="pl-9 pr-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-sm focus:ring-0 focus:outline-none w-full md:w-64"
                        style="outline: none !important; box-shadow: none !important;">
                    <i class="fas fa-search absolute left-3 top-2.5 text-slate-400"></i>
                </div>

                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition">
                    Cari
                </button>

                <?php if ($yearFilter || $search): ?>
                    <a href="daftar-alumni.php" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-sm font-medium transition">
                        Reset
                    </a>
                <?php endif; ?>

                <a href="cetak-alumni.php?year=<?= urlencode($yearFilter) ?>&search=<?= urlencode($search) ?>"
                    target="_blank"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition flex items-center gap-2">
                    <i class="fas fa-print"></i> Cetak
                </a>
            </form>
        </div>

        <div class="hidden print:block p-8 border-b-2 border-black mb-4">
            <div class="flex items-center gap-4 mb-2">
                <div class="w-24 h-24 flex items-center justify-center">
                    <img src="../assets/img/logo.png" alt="Logo Sekolah" class="w-full h-auto object-contain">
                </div>

                <div class="flex-1 text-center">
                    <h2 class="text-xl font-bold uppercase tracking-wide">PEMERINTAH PROVINSI JAWA TENGAH</h2>
                    <h1 class="text-2xl font-bold uppercase tracking-wider">SMA NEGERI 1 GEBOG</h1>
                    <p class="text-sm font-medium">Jalan PR Sukun, Gondosari, Gebog, Kudus, Jawa Tengah 59333</p>
                    <p class="text-sm">Telepon: (0291) 434778 | Email: sman1gebog@yahoo.co.id</p>
                </div>
            </div>
            <div class="border-t-4 border-black mt-2"></div>
            <div class="border-t border-black mt-1"></div>

            <div class="text-center mt-6">
                <h3 class="text-lg font-bold uppercase underline">DATA ALUMNI / LULUSAN</h3>
                <p class="text-sm font-medium mt-1">
                    Tahun Lulus: <?= $yearFilter ? $yearFilter : 'SEMUA ANGKATAN' ?>
                </p>
            </div>
        </div>

        <div class="overflow-x-auto print:overflow-visible">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 dark:bg-slate-700/50 text-xs uppercase text-slate-500 dark:text-slate-400 print:bg-gray-100 print:text-black">
                    <tr>
                        <th class="px-6 py-4 w-12 text-center print:border print:border-black print:text-xs">No</th>
                        <th class="px-6 py-4 print:border print:border-black print:text-xs">Nama Lengkap</th>
                        <th class="px-6 py-4 print:border print:border-black print:text-xs">NISN</th>
                        <th class="px-6 py-4 text-center print:border print:border-black print:text-xs">Kelas Terakhir</th>
                        <th class="px-6 py-4 text-center print:border print:border-black print:text-xs">Tahun Lulus</th>
                        <th class="px-6 py-4 text-center print:hidden">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-sm print:text-black">
                    <?php if (empty($alumniList)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500 print:border print:border-black">Data alumni tidak ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($alumniList as $i => $siswa): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition print:hover:bg-transparent">
                                <td class="px-6 py-4 text-center text-slate-500 print:text-black print:border print:border-black print:py-2">
                                    <?= $i + 1 ?>
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-900 dark:text-white print:text-black print:border print:border-black print:py-2">
                                    <?= sanitize($siswa['nama']) ?>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300 print:text-black print:border print:border-black print:py-2">
                                    <?= $siswa['nisn'] ?>
                                </td>
                                <td class="px-6 py-4 text-center text-slate-600 dark:text-slate-300 print:text-black print:border print:border-black print:py-2">
                                    <?= $siswa['kelas'] ?>
                                </td>
                                <td class="px-6 py-4 text-center print:border print:border-black print:py-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 print:bg-transparent print:text-black print:p-0">
                                        <?= $siswa['tahun_lulus'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center print:hidden">
                                    <a href="detail-alumni.php?id=<?= $siswa['id'] ?>" class="text-blue-600 hover:text-blue-800 text-xs font-medium underline">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="hidden print:block mt-8 px-8">
            <div class="flex justify-end">
                <div class="text-center">
                    <p>Kudus, <?= date('d F Y') ?></p>
                    <p class="mb-16">Kepala Sekolah,</p>
                    <p class="font-bold underline">NAMA KEPALA SEKOLAH</p>
                    <p>NIP. ..........................</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        @page {
            size: A4;
            margin: 1cm;
        }

        body {
            background: white !important;
            color: black !important;
            font-size: 12pt;
        }

        /* Sembunyikan elemen non-cetak */
        #sidebar,
        header,
        .print\:hidden {
            display: none !important;
        }

        /* Tampilkan elemen cetak */
        .print\:block {
            display: block !important;
        }

        /* Reset Container */
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            position: static !important;
        }

        .bg-white {
            box-shadow: none !important;
            border: none !important;
        }

        /* Table Styling Formal */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }

        th {
            background-color: #f3f4f6 !important;
            color: black !important;
            font-weight: bold;
            text-align: center;
            border: 1px solid black !important;
            padding: 5px !important;
        }

        td {
            border: 1px solid black !important;
            padding: 5px !important;
            color: black !important;
        }
    }
</style>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
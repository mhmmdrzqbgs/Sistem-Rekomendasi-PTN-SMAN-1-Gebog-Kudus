<?php
/**
 * Admin - Kelola Kelulusan Siswa
 * Fitur: Set status Lulus (Custom Tahun)/Aktif/Pindah secara massal
 * Updated: SweetAlert2 Confirmation & Moved Actions to Top
 */
$pageTitle = 'Kelola Kelulusan';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

// Handle Bulk Action
if (isPost()) {
    $action = post('bulk_action');
    $selectedIds = $_POST['ids'] ?? [];
    $inputTahun = post('tahun_lulus');
    $tahunLulus = !empty($inputTahun) ? $inputTahun : date('Y');

    if (!empty($selectedIds)) {
        $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
        
        if ($action === 'set_lulus') {
            $params = array_merge([$tahunLulus], $selectedIds);
            $sql = "UPDATE siswa_profile SET status = 'Lulus', tahun_lulus = ? WHERE id IN ($placeholders)";
            $db->execute($sql, $params);
            setFlash('message', count($selectedIds) . " siswa berhasil ditandai LULUS (Tahun $tahunLulus).", 'success');
            
        } elseif ($action === 'set_aktif') {
            $db->execute("UPDATE siswa_profile SET status = 'Aktif', tahun_lulus = NULL WHERE id IN ($placeholders)", $selectedIds);
            setFlash('message', count($selectedIds) . ' siswa dikembalikan ke status AKTIF.', 'success');
        }
    } else {
        setFlash('message', 'Tidak ada siswa yang dipilih.', 'warning');
    }
    redirect('kelola-kelulusan.php');
}

// Filter Logic
$kelasFilter = get('kelas');
$statusFilter = get('status') ?: 'Aktif';

$where = ["1=1"];
$params = [];

if ($kelasFilter) {
    $where[] = "kelas = ?";
    $params[] = $kelasFilter;
}
if ($statusFilter) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

$whereSql = implode(' AND ', $where);

// QUERY SISWA (Natural Sort)
$siswaList = $db->query("
    SELECT sp.*, u.nama 
    FROM siswa_profile sp 
    JOIN users u ON sp.user_id = u.id 
    WHERE $whereSql 
    ORDER BY 
        LENGTH(sp.kelas) ASC, 
        CAST(REGEXP_REPLACE(sp.kelas, '[^0-9]+', '') AS UNSIGNED) ASC,
        sp.kelas ASC, 
        u.nama ASC
", $params);

// LIST KELAS
$kelasList = $db->query("
    SELECT DISTINCT kelas 
    FROM siswa_profile 
    WHERE kelas IS NOT NULL AND kelas != '' 
    ORDER BY 
        LENGTH(kelas) ASC, 
        CAST(REGEXP_REPLACE(kelas, '[^0-9]+', '') AS UNSIGNED) ASC,
        kelas ASC
");

require_once __DIR__ . '/../templates/header-admin.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
            <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-user-graduate text-blue-500"></i> Kelola Kelulusan Siswa
            </h3>
            
            <form method="GET" class="flex flex-wrap gap-2 justify-end w-full md:w-auto">
                <select name="status" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-sm focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                    <option value="Aktif" <?= $statusFilter == 'Aktif' ? 'selected' : '' ?>>Siswa Aktif</option>
                    <option value="Lulus" <?= $statusFilter == 'Lulus' ? 'selected' : '' ?>>Alumni (Lulus)</option>
                </select>
                
                <select name="kelas" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-sm focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                    <option value="">-- Semua Kelas --</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= $k['kelas'] ?>" <?= $kelasFilter == $k['kelas'] ? 'selected' : '' ?>><?= $k['kelas'] ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <form method="POST" id="bulkForm">
            <input type="hidden" name="bulk_action" id="bulkActionInput">
            
            <div class="bg-white dark:bg-slate-800 p-4 rounded-lg border border-slate-200 dark:border-slate-600 flex flex-col md:flex-row items-center gap-4 shadow-sm">
                
                <div class="flex items-center gap-2 w-full md:w-auto text-slate-600 dark:text-slate-400 font-medium text-sm">
                    <i class="fas fa-tasks text-blue-500"></i> Aksi Massal:
                </div>

                <div class="flex flex-1 flex-wrap items-center gap-3 w-full md:justify-end">
                    
                    <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-1.5">
                        <span class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">Tahun Lulus:</span>
                        <input type="number" name="tahun_lulus" id="inputTahun" value="<?= date('Y') ?>" min="2000" max="2099" 
                            class="w-16 bg-transparent border-none text-sm font-bold text-slate-700 dark:text-white focus:ring-0 focus:outline-none p-0 text-center" 
                            style="outline: none !important; box-shadow: none !important;">
                    </div>

                    <button type="button" id="btnSetLulus" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                        <i class="fas fa-check-double"></i> Set Lulus
                    </button>
                    
                    <div class="hidden md:block h-6 w-px bg-slate-300 dark:bg-slate-600 mx-1"></div>

                    <button type="button" id="btnSetAktif" class="px-4 py-2 bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-600 text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
                        <i class="fas fa-undo"></i> Set Aktif
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto mt-6 border rounded-lg border-slate-200 dark:border-slate-700">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 text-xs uppercase text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-6 py-4 w-10 border-b dark:border-slate-700">
                                <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                            </th>
                            <th class="px-6 py-4 border-b dark:border-slate-700">Nama Siswa</th>
                            <th class="px-6 py-4 border-b dark:border-slate-700">NISN</th>
                            <th class="px-6 py-4 border-b dark:border-slate-700">Kelas</th>
                            <th class="px-6 py-4 text-center border-b dark:border-slate-700">Status</th>
                            <th class="px-6 py-4 text-center border-b dark:border-slate-700">Thn Lulus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                        <?php if (empty($siswaList)): ?>
                            <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Data tidak ditemukan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($siswaList as $s): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" name="ids[]" value="<?= $s['id'] ?>" class="item-check rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                    </td>
                                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white"><?= sanitize($s['nama']) ?></td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300"><?= $s['nisn'] ?></td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300"><?= $s['kelas'] ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 rounded text-xs font-bold 
                                            <?= $s['status'] == 'Aktif' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                                            <?= $s['status'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-slate-500">
                                        <?= $s['tahun_lulus'] ? $s['tahun_lulus'] : '-' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Select All Logic
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.item-check');

        if(selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }

        // 2. SweetAlert Logic
        const form = document.getElementById('bulkForm');
        const bulkActionInput = document.getElementById('bulkActionInput');
        const inputTahun = document.getElementById('inputTahun');

        function countChecked() {
            return document.querySelectorAll('.item-check:checked').length;
        }

        // Tombol Set Lulus
        document.getElementById('btnSetLulus').addEventListener('click', function() {
            const count = countChecked();
            const tahun = inputTahun.value;

            if (count === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tidak ada siswa dipilih',
                    text: 'Silakan centang minimal satu siswa.',
                    confirmButtonColor: '#3085d6',
                });
                return;
            }

            Swal.fire({
                title: 'Konfirmasi Kelulusan',
                html: `Anda akan meluluskan <b>${count} siswa</b> pada tahun <b>${tahun}</b>.<br>Lanjutkan?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563EB', // Blue-600
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Luluskan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    bulkActionInput.value = 'set_lulus';
                    form.submit();
                }
            });
        });

        // Tombol Set Aktif
        document.getElementById('btnSetAktif').addEventListener('click', function() {
            const count = countChecked();

            if (count === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tidak ada siswa dipilih',
                    text: 'Silakan centang minimal satu siswa.',
                    confirmButtonColor: '#3085d6',
                });
                return;
            }

            Swal.fire({
                title: 'Kembalikan Status Aktif?',
                text: `Status ${count} siswa akan diubah menjadi AKTIF kembali.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10B981', // Emerald-500
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Aktifkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    bulkActionInput.value = 'set_aktif';
                    form.submit();
                }
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
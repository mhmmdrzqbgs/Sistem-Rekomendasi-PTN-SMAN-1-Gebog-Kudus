<?php
/**
 * Admin - Edit Data Siswa
 * Fitur: Edit Profil, Akun Login, Foto
 * Updated: Minat Readonly & Uniform Focus Ring
 */
$pageTitle = 'Edit Data Siswa';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
$id = get('id'); 

if (!$id) {
    setFlash('message', 'ID Siswa tidak valid.', 'error');
    redirect('siswa.php');
}

// 1. AMBIL DATA SAAT INI
$siswa = $db->queryOne("
    SELECT sp.*, u.nama, u.username, u.is_active, u.id as uid 
    FROM siswa_profile sp 
    JOIN users u ON sp.user_id = u.id 
    WHERE sp.id = ?
", [$id]);

if (!$siswa) {
    setFlash('message', 'Data siswa tidak ditemukan di database.', 'error');
    redirect('siswa.php');
}

// 2. PROSES UPDATE
if (isPost()) {
    $nama = post('nama');
    $nisn = post('nisn');
    $kelas = post('kelas');
    $rumpun = post('kode_rumpun');
    $sekolah = post('asal_sekolah');
    $status = post('is_active');
    $password = post('password');
    
    // Minat (Readonly - Tidak diupdate dari sini jika hanya ingin lihat)
    // Jika ingin tetap bisa diedit admin, hapus komentar di bawah ini
    // $minatSaintek = post('minat_saintek');
    // $minatSoshum = post('minat_soshum');

    $db->execute("START TRANSACTION");
    try {
        // A. Update Tabel USERS
        $userId = $siswa['uid'];
        $paramsUser = [$nama, $nisn, $status];
        $sqlUser = "UPDATE users SET nama = ?, username = ?, is_active = ?";
        
        if (!empty($password)) {
            $sqlUser .= ", password = ?";
            $paramsUser[] = password_hash($password, PASSWORD_BCRYPT);
        }
        
        $sqlUser .= " WHERE id = ?";
        $paramsUser[] = $userId;
        
        $db->execute($sqlUser, $paramsUser);

        // B. Update Tabel SISWA_PROFILE
        $foto = $siswa['foto']; 
        
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $newFotoName = 'siswa_' . $id . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/';
            
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $newFotoName)) {
                if ($siswa['foto'] && file_exists($uploadDir . $siswa['foto'])) {
                    unlink($uploadDir . $siswa['foto']);
                }
                $foto = $newFotoName;
            }
        }

        $db->execute("
            UPDATE siswa_profile SET 
                nisn = ?, 
                kelas = ?, 
                kode_rumpun = ?, 
                asal_sekolah = ?, 
                foto = ?
            WHERE id = ?
        ", [$nisn, $kelas, $rumpun, $sekolah, $foto, $id]);

        $db->execute("COMMIT");
        setFlash('message', 'Data siswa berhasil diperbarui.', 'success');
        redirect("detail-siswa.php?id=$id");

    } catch (Exception $e) {
        $db->execute("ROLLBACK");
        setFlash('message', 'Gagal update: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../templates/header-admin.php';

// Style Input Seragam (Ring Biru saat Focus)
$inputStyle = "w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow";
$readonlyStyle = "w-full rounded-lg border-slate-200 bg-slate-100 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 p-2.5 text-sm focus:outline-none cursor-not-allowed";
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Edit Data Siswa</h2>
        <a href="detail-siswa.php?id=<?= $id ?>" class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 transition shadow-sm">
            <i class="fas fa-times mr-2"></i> Batal
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-8">
        
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4 border-b dark:border-slate-700 pb-2">Identitas & Akun</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nama Lengkap</label>
                    <input type="text" name="nama" value="<?= sanitize($siswa['nama']) ?>" class="<?= $inputStyle ?>" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">NISN (Username)</label>
                    <input type="text" name="nisn" value="<?= sanitize($siswa['username']) ?>" class="<?= $inputStyle ?>" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password Baru</label>
                    <input type="password" name="password" placeholder="(Kosongkan jika tidak diganti)" class="<?= $inputStyle ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Status Akun</label>
                    <select name="is_active" class="<?= $inputStyle ?>">
                        <option value="1" <?= $siswa['is_active'] == 1 ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= $siswa['is_active'] == 0 ? 'selected' : '' ?>>Non-Aktif</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Foto Profil</label>
                    <div class="flex items-center gap-4">
                        <?php if ($siswa['foto']): ?>
                            <img src="../uploads/<?= $siswa['foto'] ?>" class="w-12 h-12 rounded-full object-cover border dark:border-slate-600">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-400">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="foto" accept="image/*" class="block w-full text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-slate-700 dark:file:text-slate-300 cursor-pointer">
                    </div>
                </div>

            </div>
        </div>

        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4 border-b dark:border-slate-700 pb-2">Data Sekolah</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Kelas</label>
                    <input type="text" name="kelas" value="<?= sanitize($siswa['kelas']) ?>" class="<?= $inputStyle ?>" placeholder="XII MIPA 1">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Rumpun</label>
                    <input type="text" name="kode_rumpun" value="<?= sanitize($siswa['kode_rumpun']) ?>" class="<?= $inputStyle ?>" placeholder="A, B, C...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Asal Sekolah</label>
                    <input type="text" name="asal_sekolah" value="<?= sanitize($siswa['asal_sekolah']) ?>" class="<?= $inputStyle ?>">
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4 border-b dark:border-slate-700 pb-2">Minat & Rencana (Hanya Siswa yang Edit)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Minat Saintek</label>
                    <textarea readonly class="<?= $readonlyStyle ?>" rows="3"><?= sanitize($siswa['minat_saintek']) ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Minat Soshum</label>
                    <textarea readonly class="<?= $readonlyStyle ?>" rows="3"><?= sanitize($siswa['minat_soshum']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t dark:border-slate-700 flex justify-end">
            <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-lg shadow-blue-500/30 transition transform active:scale-95 flex items-center">
                <i class="fas fa-save mr-2"></i> Simpan Perubahan
            </button>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
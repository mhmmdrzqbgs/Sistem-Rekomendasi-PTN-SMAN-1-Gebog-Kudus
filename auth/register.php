<?php
/**
 * Register Page (Siswa Only)
 */
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/app.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect(APP_URL . '/siswa/dashboard.php');
}

$error = '';
$success = '';

if (isPost()) {
    $nama = post('nama');
    $email = post('email');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $data = [
        'nisn' => post('nisn'),
        'kelas' => post('kelas'),
        'jurusan_sma' => post('jurusan_sma'),
        'asal_sekolah' => post('asal_sekolah'),
        'tahun_lulus' => post('tahun_lulus'),
        'minat' => post('minat')
    ];

    // Validation
    if (empty($nama) || empty($email) || empty($password)) {
        $error = 'Nama, email, dan password wajib diisi';
    } elseif ($password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        $result = $auth->register($nama, $email, $password, $data);
        if ($result['success']) {
            setFlash('message', 'Registrasi berhasil! Silakan login.', 'success');
            redirect(APP_URL . '/auth/login.php');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
        }

        .auth-container {
            width: 100%;
            max-width: 520px;
        }

        .auth-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.75rem;
            color: white;
        }

        .auth-header h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .auth-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .auth-form .form-group {
            margin-bottom: 16px;
        }

        .auth-form .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .auth-form .form-control {
            padding: 12px 14px;
            font-size: 0.95rem;
        }

        .auth-form .btn-primary {
            width: 100%;
            padding: 14px;
            font-size: 1rem;
            font-weight: 600;
            margin-top: 8px;
        }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
            color: var(--text-secondary);
        }

        .auth-footer a {
            color: var(--primary);
            font-weight: 500;
        }

        .section-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: 24px 0 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }

        @media (max-width: 480px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>Daftar Akun Siswa</h1>
                <p>Isi data diri untuk mendaftar</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nama">Nama Lengkap *</label>
                        <input type="text" name="nama" id="nama" class="form-control" placeholder="Nama lengkap"
                            required value="<?= post('nama') ?>">
                    </div>
                    <div class="form-group">
                        <label for="nisn">NISN</label>
                        <input type="text" name="nisn" id="nisn" class="form-control" placeholder="10 digit NISN"
                            value="<?= post('nisn') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="email@contoh.com"
                        required value="<?= post('email') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="Min. 6 karakter" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password *</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                            placeholder="Ulangi password" required>
                    </div>
                </div>

                <div class="section-title">Informasi Sekolah</div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="asal_sekolah">Asal Sekolah</label>
                        <input type="text" name="asal_sekolah" id="asal_sekolah" class="form-control"
                            placeholder="Nama SMA/SMK" value="<?= post('asal_sekolah') ?>">
                    </div>
                    <div class="form-group">
                        <label for="kelas">Kelas</label>
                        <select name="kelas" id="kelas" class="form-control">
                            <option value="">Pilih Kelas</option>
                            <option value="X" <?= post('kelas') === 'X' ? 'selected' : '' ?>>Kelas X</option>
                            <option value="XI" <?= post('kelas') === 'XI' ? 'selected' : '' ?>>Kelas XI</option>
                            <option value="XII" <?= post('kelas') === 'XII' ? 'selected' : '' ?>>Kelas XII</option>
                            <option value="Alumni" <?= post('kelas') === 'Alumni' ? 'selected' : '' ?>>Alumni</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="jurusan_sma">Jurusan</label>
                        <select name="jurusan_sma" id="jurusan_sma" class="form-control">
                            <option value="IPA" <?= post('jurusan_sma') === 'IPA' ? 'selected' : '' ?>>IPA</option>
                            <option value="IPS" <?= post('jurusan_sma') === 'IPS' ? 'selected' : '' ?>>IPS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tahun_lulus">Tahun Lulus</label>
                        <select name="tahun_lulus" id="tahun_lulus" class="form-control">
                            <?php for ($y = date('Y') + 1; $y >= date('Y') - 3; $y--): ?>
                                <option value="<?= $y ?>" <?= post('tahun_lulus') == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="minat">Minat Bidang</label>
                    <input type="text" name="minat" id="minat" class="form-control"
                        placeholder="Contoh: Teknologi, Kesehatan, Bisnis" value="<?= post('minat') ?>">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Daftar Sekarang
                </button>
            </form>

            <div class="auth-footer">
                <p>Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
            </div>
        </div>
    </div>

    <script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>

</html>
# Sistem Pendukung Keputusan Rekomendasi Jurusan & PTN

Aplikasi web berbasis PHP native dan MySQL untuk membantu siswa SMA dalam menentukan jurusan dan perguruan tinggi negeri di Jawa Tengah dan DIY berdasarkan nilai rapor dan UTBK.

## Fitur Utama

### Untuk Siswa
- **Dashboard** - Ringkasan nilai dan rekomendasi
- **Input Nilai Rapor** - Semester 1-6 dengan mata pelajaran lengkap
- **Input Nilai Try Out** - Skor UTBK/SNBT
- **Rekomendasi Top 3** - Jurusan dan PTN berdasarkan kecocokan skor
- **Analisis Nilai** - Visualisasi perkembangan nilai

### Untuk Admin/Guru BK
- **Dashboard Admin** - Statistik siswa dan rekomendasi
- **Kelola Data Siswa** - Import via Excel, input manual
- **Kelola PTN & Prodi** - Master data perguruan tinggi
- **Import Benchmark** - Upload cutoff SNBP/SNBT via Excel
- **Laporan** - Analisis data siswa

## Algoritma Rekomendasi

### SNBP (Berdasarkan Nilai Rapor)
1. **Perhitungan Skor**: Menggunakan bobot mata pelajaran per prodi
2. **Jika ada bobot**: Skor = Σ(nilai_mapel × bobot) / Σ(bobot)
3. **Jika tidak ada bobot**: Skor = rata-rata rapor
4. **Estimasi Peluang**:
   - Tinggi: skor ≥ cutoff_avg
   - Sedang: cutoff_min ≤ skor < cutoff_avg
   - Rendah: skor < cutoff_min

### SNBT (Berdasarkan Nilai Try Out)
1. **Skor**: Total skor UTBK (TPS + Literasi Indo + Literasi Ing + Penalaran MTK)
2. **Estimasi Peluang**: Sama dengan SNBP

### Ranking
- Urutkan berdasarkan peluang (Tinggi > Sedang > Rendah)
- Kemudian berdasarkan skor tertinggi
- Ambil Top 3 per jalur (SNBP/SNBT)

## Struktur Database

### Tabel Utama
- `users` - Data login (siswa/admin)
- `siswa_profile` - Profil siswa
- `nilai_rapor` - Nilai rapor per semester
- `nilai_tryout` - Nilai try out UTBK
- `ptn` - Data perguruan tinggi
- `prodi` - Program studi
- `cutoff_prodi` - Benchmark cutoff per prodi
- `bobot_mapel` - Bobot mata pelajaran per prodi
- `rekomendasi` - Hasil rekomendasi

## Instalasi

1. **Clone Repository**
   ```bash
   git clone <repository-url>
   cd webdinda
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Setup Database**
   - Import `database/schema.sql`
   - Konfigurasi koneksi di `config/database.php`

4. **Konfigurasi Aplikasi**
   - Edit `config/app.php` untuk URL dan pengaturan

5. **Upload Benchmark**
   - Login sebagai admin
   - Pergi ke menu "Import Benchmark"
   - Upload file Excel dengan format:
     | PTN | Program Studi | Jalur | Cutoff Min | Cutoff Avg | Tahun |
     |-----|---------------|-------|------------|------------|-------|
     | UNDIP | Teknik Informatika | SNBP | 85.0 | 90.0 | 2024 |

## Penggunaan

### Login Default
- **Admin**: admin@sekolah.id / password
- **Siswa**: Buat akun baru atau import dari Excel

### Alur Kerja
1. **Admin** import data siswa dan benchmark cutoff
2. **Siswa** login dan lengkapi profil
3. **Siswa** input nilai rapor semester 1-6
4. **Siswa** input nilai try out (jika ada)
5. Sistem generate rekomendasi Top 3 jurusan + PTN
6. **Siswa** lihat rekomendasi dengan estimasi peluang

## Teknologi

- **Backend**: PHP 7.4+, MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Library**: PhpSpreadsheet (untuk import Excel)
- **UI Framework**: Custom CSS dengan Inter Font

## Browser Support

- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## Lisensi

Copyright © 2024 - Sistem Rekomendasi PTN
**Sistem Pendukung Keputusan Rekomendasi Jurusan & PTN SMAN 1 Gebog**
Aplikasi web berbasis PHP Native dan MySQL untuk membantu siswa SMA dalam menentukan jurusan dan Perguruan Tinggi Negeri (PTN) berdasarkan nilai Rapor (SNBP) dan skor Try Out UTBK (SNBT). Sistem ini dirancang untuk mendukung Kurikulum Merdeka (mengakomodasi peminatan/rumpun) dan menggunakan algoritma Rule-Based System dipadukan dengan Closest Match Heuristic.

**🚀 Fitur Utama**
**🧑‍🎓 Untuk Siswa**
Dashboard Interaktif - Ringkasan nilai, tren grafik rapor, dan highlight rekomendasi paling aman.

Profil & Minat - Pemilihan rumpun belajar dan input minat jurusan/PTN secara spesifik.

Input Nilai Terintegrasi:

Nilai Rapor: Semester 1-5 dengan detail mata pelajaran (mendukung logika Kurikulum Merdeka).

Nilai TKA: Matematika, Bahasa Indonesia, Bahasa Inggris, dan Mapel Pilihan.

Nilai Try Out: 7 Subtes UTBK/SNBT.

Rekomendasi Cerdas (5+5) - Menghasilkan maksimal 5 rekomendasi Saintek dan 5 Soshum per jalur evaluasi.

Cetak Dokumen - Laporan Hasil Analisis Akademik dan Rekomendasi Studi berformat PDF resmi bergaya profesional.

**👨‍🏫 Untuk Admin / Guru BK**
Dashboard Analitik - Statistik siswa, daftar rekomendasi tertinggi (Top Saintek/Soshum), dan tren mapel pilihan.

Manajemen Master Data - Kelola data PTN, Program Studi (beserta Daya Tampung & Passing Grade), dan Master Mapel.

Kelola Data Siswa - Pemantauan nilai, input/edit data siswa secara manual.

Import Massal (Excel) - Fitur Upload data via Excel terintegrasi (Siswa, Rapor, TKA, Tryout, dan Prodi) menggunakan PhpSpreadsheet.

Laporan & Filtering - Daftar siswa dengan filter cerdas berdasarkan peluang kelulusan (Tinggi, Sedang, Rendah) yang dapat dicetak (PDF).

**🧠 Algoritma Rekomendasi (Smart Priority)**
Sistem tidak menggunakan bobot mapel rumit, melainkan menggunakan logika Weighted Sum Model (WSM) dan Nearest Neighbor (Closest Match):

1. Perhitungan Skor Siswa
Jalur SNBP (Rapor & TKA):
Skor = ((Rata-rata Rapor Smt 1-5 × 70%) + (Rata-rata TKA × 30%)) × 7.5

Jalur SNBT (Tryout):
Skor = Rata-rata dari 7 subtes UTBK

2. Logika Penentuan Rekomendasi (Sistem Alternatif)
Sistem menyeleksi program studi dari database menggunakan urutan prioritas berikut:

Pemisahan Rumpun: Memisahkan prodi menjadi kategori Saintek dan Soshum menggunakan Keyword-Based Classification.

Smart Priority (Kasta Peluang):

Prioritas 1 (Aman/Target): Prodi yang Passing Grade-nya <= Skor Siswa.

Prioritas 2 (Tantangan): Prodi yang Passing Grade-nya > Skor Siswa.

Closest Match (Selisih Terkecil): Sistem mencari prodi yang selisih Passing Grade-nya paling dekat dengan Skor Siswa ABS(passing_grade - skor_siswa).

Batas Kuota: Mengambil maksimal 5 prodi terbaik untuk Saintek dan 5 untuk Soshum.

3. Estimasi Peluang (Indikator Kelulusan)
Rasio dihitung dari Skor Siswa / Passing Grade Prodi:

🟢 Tinggi (Aman): Rasio ≥ 1.10 (Skor jauh di atas PG)

🟡 Sedang (Target): Rasio ≥ 1.00 (Skor setara atau sedikit di atas PG)

🔴 Rendah (Tantangan): Rasio < 1.00 (Skor di bawah PG)

**🗄️ Struktur Database**
Tabel Utama
users - Autentikasi login (role: admin/siswa).

siswa_profile - Profil lengkap, asal sekolah, rumpun, dan minat.

master_mapel & paket_rumpun - Data mata pelajaran dan pengelompokan kurikulum.

siswa_mapel_pilihan - Pencatatan mapel pilihan bebas siswa.

nilai_rapor & nilai_rapor_detail - Header dan detail nilai rapor per semester.

nilai_tka - Nilai Tes Kemampuan Akademik.

nilai_tryout - Nilai skor Try Out UTBK/SNBT.

ptn & prodi - Direktori Perguruan Tinggi dan Program Studi (lengkap dengan Passing Grade & Daya Tampung).

rekomendasi - Menyimpan hasil generate algoritma.

**⚙️ Instalasi & Konfigurasi**
Clone Repository

Bash
git clone <repository-url>
cd webdinda
Setup Database

Buat database baru di MySQL (contoh: web_SPK).

Import file SQL dari database/schema.sql (jika ada) atau import database .sql yang telah disediakan.

Konfigurasi kredensial koneksi di includes/Database.php.

Pengaturan Server (PENTING untuk Import Excel)
Sistem ini menggunakan library PhpSpreadsheet. Pastikan ekstensi zip pada PHP sudah aktif:

Buka XAMPP Control Panel.

Klik Config pada Apache -> Pilih PHP (php.ini).

Cari baris ;extension=zip dan hapus tanda titik komanya menjadi extension=zip.

Restart Apache.

Install Dependencies (Opsional jika folder vendor belum ada)

Bash
composer install
**💻 Penggunaan (Alur Kerja)**
Login Default Admin
Username: admin@sekolah.id

Password: password

Langkah Penggunaan:
Admin melakukan import Master Data (PTN, Prodi, Passing Grade) menggunakan menu Import Data.

Admin menginput data siswa beserta nilainya secara massal via Excel, atau Siswa login secara mandiri.

Siswa melengkapi minat jurusan dan mengecek nilai Rapor/TKA/Tryout.

Buka menu Rekomendasi Prodi (Sistem akan secara otomatis menghapus rekomendasi lama dan menghitung ulang rekomendasi baru yang fresh).

Admin / Siswa mencetak hasil analisis dan rekomendasi dalam format PDF bergaya formal institusi.

**🛠️ Teknologi yang Digunakan**
Backend: PHP 7.4+ / PHP 8.x (Native OOP / PDO)

Database: MySQL / MariaDB

Frontend / UI:

Tailwind CSS (via CDN)

Font Inter & Times New Roman (untuk dokumen cetak)

FontAwesome (Icons)

Interaktivitas & Grafik:

Chart.js (Visualisasi Tren Rapor)

SweetAlert2 (Notifikasi & Pop-up Dialog)

Library Tambahan:

PhpSpreadsheet (Pembaca & Penulis Excel)

**📝 Lisensi**
Copyright ©2026 - Sistem Rekomendasi PTN SMA Negeri 1 Gebog Kudus.

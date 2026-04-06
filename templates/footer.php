<div id="global-loader" class="fixed inset-0 z-[9999] hidden opacity-0 bg-slate-900/60 backdrop-blur-[4px] flex items-center justify-center transition-opacity duration-300">
        <div class="bg-white/90 dark:bg-slate-800/90 rounded-2xl shadow-2xl p-8 flex flex-col items-center justify-center transform transition-transform scale-100 border border-white/20 dark:border-slate-700/50">
            <svg class="animate-spin h-12 w-12 text-indigo-600 dark:text-indigo-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-slate-600 dark:text-slate-300 font-medium text-sm tracking-wide animate-pulse">
                Memproses Data...
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const loader = document.getElementById('global-loader');
        let loaderTimeout;

        // --- FUNGSI KONTROL LOADER ---
        function showLoader() {
            clearTimeout(loaderTimeout);
            loaderTimeout = setTimeout(() => {
                if(loader) {
                    loader.classList.remove('hidden');
                    requestAnimationFrame(() => {
                        loader.classList.remove('opacity-0');
                    });
                }
            }, 500); // Delay 1 Detik
        }

        function hideLoader() {
            clearTimeout(loaderTimeout);
            if(loader && !loader.classList.contains('hidden')) {
                loader.classList.add('opacity-0');
                setTimeout(() => loader.classList.add('hidden'), 300);
            }
        }

        // =================================================================
        // 1. DETEKSI KONEKSI INTERNET (FITUR BARU)
        // =================================================================
        
        // Fungsi Alert Koneksi Hilang
        function showOfflineAlert() {
            hideLoader(); // Matikan loader jika sedang berputar
            Swal.fire({
                icon: 'error',
                title: 'Koneksi Terputus!',
                text: 'Gagal menghubungi server. Periksa koneksi internet Anda.',
                confirmButtonColor: '#ef4444',
                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#fff',
                color: document.documentElement.classList.contains('dark') ? '#fff' : '#000',
                customClass: { popup: 'rounded-xl shadow-xl' }
            });
        }

        // Event Listener: Jika tiba-tiba internet mati saat halaman aktif
        window.addEventListener('offline', () => {
            showOfflineAlert();
        });

        // =================================================================
        // 2. FIX BUG BUFFER (Back Button)
        // =================================================================
        window.addEventListener('pageshow', function(event) {
            hideLoader(); 
        });

        // =================================================================
        // 3. AUTO LOADER + VALIDASI + CEK INTERNET
        // =================================================================
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    
                    // A. Cek Koneksi Internet Dulu
                    if (!navigator.onLine) {
                        e.preventDefault(); // Batalkan submit
                        showOfflineAlert();
                        return;
                    }

                    // B. Cek Validitas Form (Kolom Kosong)
                    if (!this.checkValidity()) {
                        e.preventDefault(); 
                        hideLoader();       
                        
                        Swal.fire({
                            icon: 'warning',
                            title: 'Data Belum Lengkap',
                            text: 'Mohon isi semua kolom yang wajib diisi.',
                            confirmButtonColor: '#f59e0b',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#fff',
                            color: document.documentElement.classList.contains('dark') ? '#fff' : '#000',
                            customClass: { popup: 'rounded-xl shadow-xl' }
                        });

                    } else {
                        // C. Jika Aman Semua -> Jalankan Loader
                        showLoader(); 
                    }
                });
            });
        });

        // =================================================================
        // 4. FLASH MESSAGE (Respon PHP)
        // =================================================================
        <?php if ($msg = getFlash('message')): ?>
            Swal.fire({
                icon: '<?= $msg['type'] ?>',
                title: '<?= $msg['type'] == "success" ? "Berhasil!" : ($msg['type'] == "error" ? "Gagal!" : "Informasi") ?>',
                text: '<?= $msg['message'] ?>',
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true,
                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#fff',
                color: document.documentElement.classList.contains('dark') ? '#fff' : '#000',
                customClass: { popup: 'rounded-xl shadow-xl' }
            });
        <?php endif; ?>

        // =================================================================
        // 5. KONFIRMASI LOGOUT & DELETE
        // =================================================================
        document.querySelectorAll('a[href*="logout.php"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                confirmAction('Konfirmasi Keluar', 'Yakin ingin mengakhiri sesi?', this.getAttribute('href'));
            });
        });

        document.querySelectorAll('a[onclick*="confirm"]').forEach(link => {
            const text = link.getAttribute('onclick').match(/'([^']+)'/)?.[1] || "Lanjutkan aksi ini?";
            link.removeAttribute('onclick'); 
            link.addEventListener('click', function(e) {
                e.preventDefault();
                confirmAction('Konfirmasi', text, this.getAttribute('href'), 'warning', '#ef4444');
            });
        });

        function confirmAction(title, text, href, icon = 'question', confirmColor = '#3b82f6') {
            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: confirmColor,
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#fff',
                color: document.documentElement.classList.contains('dark') ? '#fff' : '#000',
                customClass: { popup: 'rounded-xl shadow-xl' }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Cek koneksi lagi sebelum aksi link
                    if (!navigator.onLine) {
                        showOfflineAlert();
                    } else {
                        showLoader(); 
                        window.location.href = href;
                    }
                }
            });
        }
    </script>
</body>
</html>
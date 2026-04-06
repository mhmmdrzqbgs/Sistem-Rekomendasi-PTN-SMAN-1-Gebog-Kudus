<div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col justify-between gap-3 hover:shadow-md transition-all relative overflow-hidden h-full group">
    
    <?php
        // 1. Logika Ribbon Strategi (Pojok Kanan Atas)
        $strategi = '';
        $ribbonColor = 'bg-slate-500';
        // Cek string di database untuk menentukan strategi
        if (strpos($rekom['alasan'], 'TARGET') !== false) { $strategi = 'TARGET'; $ribbonColor = 'bg-blue-600'; }
        elseif (strpos($rekom['alasan'], 'AMAN') !== false) { $strategi = 'AMAN'; $ribbonColor = 'bg-emerald-600'; }
        elseif (strpos($rekom['alasan'], 'TANTANGAN') !== false) { $strategi = 'TANTANGAN'; $ribbonColor = 'bg-amber-500'; }
        
        // 2. Logika Warna Badge Peluang
        $peluangClass = match($rekom['peluang']) {
            'Tinggi', 'Sangat Tinggi' => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800',
            'Sedang' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800',
            default => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800'
        };

        // 3. Daya Tampung
        $dt = ($rekom['jalur'] == 'SNBP') ? $rekom['daya_tampung_snbp'] : $rekom['daya_tampung_snbt'];
    ?>

    <?php if($strategi): ?>
        <div class="absolute top-0 right-0 rounded-bl-xl px-3 py-1 text-[10px] font-bold text-white shadow-sm z-10 <?= $ribbonColor ?>">
            <?= $strategi ?>
        </div>
    <?php endif; ?>

    <div>
        <h4 class="font-bold text-slate-800 dark:text-white text-base leading-tight pr-16 group-hover:text-blue-600 transition-colors">
            <?= sanitize($rekom['prodi_nama']) ?>
        </h4>
        
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5 font-medium">
            <i class="fas fa-university text-slate-400"></i>
            <?= sanitize($rekom['ptn_nama']) ?> (<?= $rekom['singkatan'] ?>)
        </p>

        <div class="flex flex-wrap gap-2 mt-3">
            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold border <?= $peluangClass ?>">
                Peluang: <?= $rekom['peluang'] ?>
            </span>

            <span class="px-2.5 py-1 rounded-md text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">
                DT: <?= $dt > 0 ? $dt : '-' ?>
            </span>
        </div>
    </div>
    
    <div class="flex items-center justify-between mt-2 pt-3 border-t border-dashed border-slate-200 dark:border-slate-700">
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Skor Kamu</span>
        <span class="text-sm font-mono font-bold <?= $rekom['jalur'] == 'SNBP' ? 'text-emerald-600' : 'text-indigo-600' ?>">
            <?= formatNumber($rekom['skor'], $rekom['jalur'] == 'SNBP' ? 1 : 0) ?>
        </span>
    </div>

</div>
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Akun Superadmin
    |--------------------------------------------------------------------------
    |
    | Kredensial Superadmin dibaca dari environment dan tidak pernah ditulis
    | sebagai nilai literal di seeder maupun kode (FR-23). Konsekuensinya,
    | mengubah kata sandi Superadmin cukup lewat `.env` — tanpa menyunting kode
    | dan tanpa deploy ulang.
    |
    | Ditempatkan di berkas konfigurasi tersendiri, bukan menumpang di
    | `config/app.php`, supaya seluruh setelan khas DMS berkumpul di satu tempat
    | dan tidak tercampur dengan setelan bawaan framework.
    |
    */

    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Administrator BPMA'),
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dokumen
    |--------------------------------------------------------------------------
    */

    'dokumen' => [

        /*
         * Jendela intip kartu "mendekati masa evaluasi" pada dasbor (FR-04),
         * dalam hari.
         *
         * PENTING — ini bukan properti dokumen. Masa berlaku tiap dokumen
         * ditentukan pengunggah lewat kolom `masa_berlaku`, dan perpindahan
         * status ke Kadaluarsa terjadi otomatis saat tanggal itu terlewat
         * (FR-53). Angka di bawah hanya menentukan seberapa jauh ke depan
         * kartu dasbor melihat saat menyaring dokumen mana yang perlu
         * diperingatkan.
         *
         * Pengguna memilih sendiri rentangnya di dasbor; nilai di bawah adalah
         * pilihan yang tersedia dan nilai awalnya. Lihat `Catatan_Audit.md`
         * isu #14.
         */
        'rentang_evaluasi_pilihan' => [7, 30, 90],
        'rentang_evaluasi_awal' => 30,

        /*
         * Jumlah baris per halaman pada daftar dan hasil pencarian (FR-22).
         */
        'per_halaman' => 20,

        /*
         * Batas ukuran unggahan, dalam kilobyte. Berlaku SAMA di mana pun —
         * laptop pengembangan maupun VPS.
         *
         * Angka tunggal ini disengaja. Batas yang berbeda-beda per mesin
         * membuat pengujian manual tidak dapat dipercaya: berkas yang lolos di
         * laptop bisa ditolak di server tanpa ada perubahan kode apa pun, dan
         * penguji tidak punya cara mengetahui batas mana yang sedang berlaku.
         *
         * Lingkungan WAJIB disetel agar sanggup memenuhinya — lihat README
         * bagian "Batas Ukuran Unggahan". Bila lingkungannya lebih ketat,
         * aplikasi tidak diam: `App\Support\BatasUnggah` mendeteksinya, dan
         * formulir unggah menampilkan peringatan beserta batas yang sebenarnya
         * berlaku.
         */
        'ukuran_maksimum_kb' => 1048576, // 1 GB

        /*
         * Ekstensi yang ditolak saat unggah — `PRD.md` §8.2.
         *
         * Memakai daftar-tolak, bukan daftar-izin, karena DMS ini memang
         * dirancang menerima berbagai tipe berkas (`Tentang_Project.md` §3.1),
         * bukan hanya dokumen perkantoran.
         */
        'ekstensi_terlarang' => [
            'exe', 'sh', 'bat', 'cmd', 'com', 'msi',
            'php', 'phtml', 'phar', 'jsp', 'jar', 'js',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ekstraksi Teks & OCR
    |--------------------------------------------------------------------------
    |
    | Rincian perilaku di `PRD.md` §4.6 dan `Struktur_Data.md` §8.6b.
    |
    */

    'ekstraksi' => [

        /*
         * Awalan tipe MIME yang memicu job ekstraksi. Tipe di luar daftar ini
         * langsung ditandai `not_applicable` tanpa job pernah dibuat (FR-32b).
         */
        'mime_didukung' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            // 'image/' sengaja ditunda ke FEAT-11b: OCR Tesseract belum
            // berjalan di 11a, dan menandai gambar `pending` tanpa job yang
            // pernah memprosesnya berarti statusnya macet selamanya
            // (Progres-dan-Lanjutan.md §6.1).
        ],

        /*
         * Dikecualikan meski awalannya cocok dengan `image/`. Tesseract tidak
         * mendukung HEIC — format bawaan kamera iPhone (FR-32d).
         */
        'mime_dikecualikan' => [
            'image/heic',
            'image/heif',
        ],

        /*
         * Bahasa OCR. Keduanya sekaligus, bukan pilih salah satu, supaya
         * dokumen campuran Indonesia–Inggris tetap terbaca.
         */
        'bahasa_ocr' => 'ind+eng',

        /*
         * Polling status ekstraksi di antarmuka. Tanpa batas percobaan, tab yang
         * ditinggalkan terbuka akan memanggil server tanpa henti.
         */
        'polling_jeda_ms' => 3000,
        'polling_maks_percobaan' => 40,
    ],

];

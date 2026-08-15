<?php

declare(strict_types=1);

namespace Database\Seeders\Data;

/**
 * Dokumen yang judul dan penempatannya disusun satu per satu.
 *
 * Inilah dokumen yang muncul di halaman pertama daftar dan dipakai saat demo,
 * jadi kualitasnya paling menentukan kesan. Sisanya dibangkitkan factory dengan
 * pola bertemplate.
 *
 * Lima entri pertama adalah dokumen skenario demo: kombinasi mekanisme aksesnya
 * ditetapkan eksplisit di `DocumentSeeder`, bukan dibagikan otomatis, karena
 * matriks pengujian otorisasi bergantung padanya.
 */
final class DokumenKurasi
{
    /**
     * @var list<array{judul: string, kategori: string, unit: string, berkas: string}>
     */
    public const DEMO = [
        [
            'judul' => 'Panduan Cuti Pegawai',
            'kategori' => 'SOP & Panduan Kerja',
            'unit' => 'Divisi SDM dan Umum',
            'berkas' => 'sop-pengendalian-dokumen.pdf',
        ],
        [
            'judul' => 'Notulen Rapat Divisi Manajemen Sistem TI Agustus 2026',
            'kategori' => 'Notulen Rapat',
            'unit' => 'Divisi Manajemen Sistem Teknologi Informasi',
            'berkas' => 'notulen-rapat-koordinasi.docx',
        ],
        [
            'judul' => 'Kebijakan Kerja Deputi Dukungan Bisnis 2026',
            'kategori' => 'Peraturan & Kebijakan',
            'unit' => 'Deputi Dukungan Bisnis',
            'berkas' => 'sop-pengendalian-dokumen.pdf',
        ],
        [
            'judul' => 'Laporan Strategi Keuangan BPMA 2027',
            'kategori' => 'Laporan Keuangan',
            'unit' => 'Deputi Keuangan dan Monetisasi',
            'berkas' => 'laporan-realisasi-anggaran.pdf',
        ],
        [
            'judul' => 'Laporan Evaluasi Proyek X',
            'kategori' => 'Dokumen Audit & Pengawasan',
            'unit' => 'Divisi Manajemen Sistem Teknologi Informasi',
            'berkas' => 'laporan-realisasi-anggaran.pdf',
        ],
    ];

    /**
     * @var list<array{judul: string, kategori: string, unit: string, berkas: string}>
     */
    public const UMUM = [
        // -- Peraturan & Kebijakan -------------------------------------------
        ['judul' => 'Peraturan Kepala BPMA tentang Tata Kelola Data Eksplorasi dan Eksploitasi', 'kategori' => 'Peraturan & Kebijakan', 'unit' => 'Deputi Perencanaan', 'berkas' => 'sop-pengendalian-dokumen.pdf'],
        ['judul' => 'Kebijakan Pengelolaan Keselamatan dan Kesehatan Kerja Wilayah Operasi', 'kategori' => 'Peraturan & Kebijakan', 'unit' => 'Divisi Formalitas, Hubungan Eksternal dan Sekuriti K3KS', 'berkas' => 'sop-pengendalian-dokumen.pdf'],
        ['judul' => 'Pedoman Klasifikasi dan Retensi Arsip Badan', 'kategori' => 'Peraturan & Kebijakan', 'unit' => 'Divisi Hukum, Program & Pelaporan', 'berkas' => 'nota-dinas-hasil-pindai.pdf'],
        ['judul' => 'Kebijakan Keamanan Informasi dan Perlindungan Data Internal', 'kategori' => 'Peraturan & Kebijakan', 'unit' => 'Divisi Manajemen Sistem Teknologi Informasi', 'berkas' => 'sop-pengendalian-dokumen.pdf'],

        // -- SOP & Panduan Kerja ---------------------------------------------
        ['judul' => 'SOP Pengendalian Dokumen Teknis Lapangan', 'kategori' => 'SOP & Panduan Kerja', 'unit' => 'Divisi Teknologi dan Pengembangan Lapangan', 'berkas' => 'sop-pengendalian-dokumen.pdf'],
        ['judul' => 'SOP Verifikasi Data Lifting Minyak dan Gas Bumi', 'kategori' => 'SOP & Panduan Kerja', 'unit' => 'Divisi Monetisasi Minyak dan Gas Bumi', 'berkas' => 'sop-pengendalian-dokumen.pdf'],
        ['judul' => 'SOP Penanganan Keadaan Darurat di Area Produksi', 'kategori' => 'SOP & Panduan Kerja', 'unit' => 'Divisi Operasi Produksi', 'berkas' => 'papan-informasi-lapangan.png'],
        ['judul' => 'Panduan Penggunaan Sistem Manajemen Dokumen Internal', 'kategori' => 'SOP & Panduan Kerja', 'unit' => 'Divisi Manajemen Sistem Teknologi Informasi', 'berkas' => 'daftar-inventaris-aset.txt'],

        // -- Kontrak & Perjanjian --------------------------------------------
        ['judul' => 'Kontrak Kerja Sama Jasa Survei Seismik Wilayah Kerja Blok A', 'kategori' => 'Kontrak & Perjanjian', 'unit' => 'Divisi Perencanaan Eksplorasi dan Eksploitasi', 'berkas' => 'nota-dinas-hasil-pindai.pdf'],
        ['judul' => 'Perjanjian Sewa Fasilitas Penyimpanan Sementara Hasil Produksi', 'kategori' => 'Kontrak & Perjanjian', 'unit' => 'Divisi Pengelolaan Aset dan Rantai Suplai', 'berkas' => 'nota-dinas-hasil-pindai.pdf'],
        ['judul' => 'Nota Kesepahaman Pertukaran Data Teknis Antarinstansi', 'kategori' => 'Kontrak & Perjanjian', 'unit' => 'Divisi Hukum, Program & Pelaporan', 'berkas' => 'nota-dinas-hasil-pindai.pdf'],
        ['judul' => 'Kontrak Pengadaan Perangkat Pemantauan Tekanan Sumur', 'kategori' => 'Kontrak & Perjanjian', 'unit' => 'Divisi Penunjang Operasi', 'berkas' => 'nota-dinas-hasil-pindai.pdf'],

        // -- Laporan Keuangan ------------------------------------------------
        ['judul' => 'Laporan Realisasi Anggaran Triwulan III Tahun 2026', 'kategori' => 'Laporan Keuangan', 'unit' => 'Divisi Keuangan Internal', 'berkas' => 'laporan-realisasi-anggaran.pdf'],
        ['judul' => 'Laporan Keuangan Tahunan Badan Tahun Anggaran 2025', 'kategori' => 'Laporan Keuangan', 'unit' => 'Divisi Akuntansi, Perpajakan dan Manajemen Risiko', 'berkas' => 'laporan-realisasi-anggaran.pdf'],
        ['judul' => 'Laporan Penerimaan Negara Bukan Pajak Sektor Hulu Migas', 'kategori' => 'Laporan Keuangan', 'unit' => 'Divisi Monetisasi Minyak dan Gas Bumi', 'berkas' => 'laporan-realisasi-anggaran.pdf'],
        ['judul' => 'Laporan Rekonsiliasi Perpajakan Kontraktor Kerja Sama', 'kategori' => 'Laporan Keuangan', 'unit' => 'Divisi Akuntansi, Perpajakan dan Manajemen Risiko', 'berkas' => 'nota-dinas-foto.jpg'],

        // -- Dokumen Perencanaan & Anggaran ----------------------------------
        ['judul' => 'Rencana Kerja dan Anggaran BPMA Tahun 2027', 'kategori' => 'Dokumen Perencanaan & Anggaran', 'unit' => 'Divisi Pengendalian Program dan Anggaran', 'berkas' => 'rencana-kerja-anggaran.docx'],
        ['judul' => 'Rencana Strategis Pengembangan Wilayah Kerja Lima Tahun', 'kategori' => 'Dokumen Perencanaan & Anggaran', 'unit' => 'Deputi Perencanaan', 'berkas' => 'rencana-kerja-anggaran.docx'],
        ['judul' => 'Usulan Revisi Anggaran Triwulan IV Tahun 2026', 'kategori' => 'Dokumen Perencanaan & Anggaran', 'unit' => 'Divisi Pengendalian Program dan Anggaran', 'berkas' => 'rencana-kerja-anggaran.docx'],
        ['judul' => 'Rencana Pengembangan Kompetensi Pegawai Tahun 2027', 'kategori' => 'Dokumen Perencanaan & Anggaran', 'unit' => 'Divisi SDM dan Umum', 'berkas' => 'rencana-kerja-anggaran.docx'],

        // -- Laporan Operasi & Produksi --------------------------------------
        ['judul' => 'Laporan Lifting Minyak dan Gas Bumi Semester I Tahun 2026', 'kategori' => 'Laporan Operasi & Produksi', 'unit' => 'Divisi Operasi Produksi', 'berkas' => 'laporan-realisasi-anggaran.pdf'],
        ['judul' => 'Laporan Kinerja Fasilitas Produksi Blok A Aceh Utara', 'kategori' => 'Laporan Operasi & Produksi', 'unit' => 'Divisi Perawatan Fasilitas dan Pengendalian Proyek', 'berkas' => 'foto-fasilitas-produksi.jpg'],
        ['judul' => 'Laporan Bulanan Kegiatan Operasi Lapangan Agustus 2026', 'kategori' => 'Laporan Operasi & Produksi', 'unit' => 'Divisi Operasi Produksi', 'berkas' => 'nota-dinas-foto.jpg'],
        ['judul' => 'Laporan Pemeliharaan Berkala Peralatan Produksi', 'kategori' => 'Laporan Operasi & Produksi', 'unit' => 'Divisi Perawatan Fasilitas dan Pengendalian Proyek', 'berkas' => 'daftar-inventaris-aset.txt'],

        // -- Data Teknis & Eksplorasi ----------------------------------------
        ['judul' => 'Kajian Teknis Pengembangan Lapangan Gas Bumi Lepas Pantai', 'kategori' => 'Data Teknis & Eksplorasi', 'unit' => 'Divisi Teknologi dan Pengembangan Lapangan', 'berkas' => 'rencana-kerja-anggaran.docx'],
        ['judul' => 'Laporan Hasil Pengeboran Sumur Eksplorasi Tahap Pertama', 'kategori' => 'Data Teknis & Eksplorasi', 'unit' => 'Divisi Perencanaan Eksplorasi dan Eksploitasi', 'berkas' => 'nota-dinas-hasil-pindai.pdf'],
        ['judul' => 'Interpretasi Data Seismik Wilayah Kerja Bagian Utara', 'kategori' => 'Data Teknis & Eksplorasi', 'unit' => 'Divisi Perencanaan Eksplorasi dan Eksploitasi', 'berkas' => 'foto-fasilitas-produksi.jpg'],
        ['judul' => 'Kajian Kelayakan Penerapan Teknologi Pemulihan Lanjut', 'kategori' => 'Data Teknis & Eksplorasi', 'unit' => 'Divisi Teknologi dan Pengembangan Lapangan', 'berkas' => 'rencana-kerja-anggaran.docx'],

        // -- Dokumen Audit & Pengawasan --------------------------------------
        ['judul' => 'Laporan Hasil Audit Kontraktor Kontrak Kerja Sama Blok B', 'kategori' => 'Dokumen Audit & Pengawasan', 'unit' => 'Divisi Audit Kontraktor Kontrak Kerja Sama Eksplorasi & Eksploitasi', 'berkas' => 'laporan-realisasi-anggaran.pdf'],
        ['judul' => 'Laporan Tindak Lanjut Temuan Pengawasan Semester I', 'kategori' => 'Dokumen Audit & Pengawasan', 'unit' => 'Divisi Audit Kontraktor Kontrak Kerja Sama Eksplorasi & Eksploitasi', 'berkas' => 'nota-dinas-foto.jpg'],
        ['judul' => 'Laporan Penilaian Risiko Operasional Tahun Berjalan', 'kategori' => 'Dokumen Audit & Pengawasan', 'unit' => 'Divisi Akuntansi, Perpajakan dan Manajemen Risiko', 'berkas' => 'laporan-realisasi-anggaran.pdf'],
        ['judul' => 'Berita Acara Pemeriksaan Kepatuhan Prosedur Keselamatan', 'kategori' => 'Dokumen Audit & Pengawasan', 'unit' => 'Divisi Formalitas, Hubungan Eksternal dan Sekuriti K3KS', 'berkas' => 'papan-informasi-lapangan.png'],

        // -- Notulen Rapat ---------------------------------------------------
        ['judul' => 'Notulen Rapat Koordinasi Deputi Operasi Agustus 2026', 'kategori' => 'Notulen Rapat', 'unit' => 'Deputi Operasi', 'berkas' => 'notulen-rapat-koordinasi.docx'],
        ['judul' => 'Notulen Rapat Pimpinan Pembahasan Rencana Kerja 2027', 'kategori' => 'Notulen Rapat', 'unit' => 'Sekretaris BPMA', 'berkas' => 'notulen-rapat-koordinasi.docx'],
        ['judul' => 'Notulen Rapat Teknis Pembahasan Data Sumur Produksi', 'kategori' => 'Notulen Rapat', 'unit' => 'Divisi Perencanaan Eksplorasi dan Eksploitasi', 'berkas' => 'notulen-rapat-koordinasi.docx'],
        ['judul' => 'Notulen Rapat Evaluasi Penyerapan Anggaran Triwulan II', 'kategori' => 'Notulen Rapat', 'unit' => 'Divisi Pengendalian Program dan Anggaran', 'berkas' => 'notulen-rapat-koordinasi.docx'],

        // -- Surat Menyurat --------------------------------------------------
        ['judul' => 'Nota Dinas Permohonan Data Dukung Penyusunan Anggaran 2027', 'kategori' => 'Surat Menyurat', 'unit' => 'Divisi Teknologi dan Pengembangan Lapangan', 'berkas' => 'nota-dinas-foto.jpg'],
        ['judul' => 'Surat Undangan Rapat Koordinasi Lintas Deputi', 'kategori' => 'Surat Menyurat', 'unit' => 'Sekretaris BPMA', 'berkas' => 'nota-dinas-hasil-pindai.pdf'],
        ['judul' => 'Surat Keterangan Penugasan Pengawasan Lapangan', 'kategori' => 'Surat Menyurat', 'unit' => 'Divisi SDM dan Umum', 'berkas' => 'nota-dinas-hasil-pindai.pdf'],
        ['judul' => 'Nota Dinas Penyampaian Rekapitulasi Aset Semester II', 'kategori' => 'Surat Menyurat', 'unit' => 'Divisi Pengelolaan Aset dan Rantai Suplai', 'berkas' => 'arsip-lampiran-pendukung.zip'],
    ];
}

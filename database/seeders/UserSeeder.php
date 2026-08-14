<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Empat puluh lima akun pengguna, tersebar wajar di dua puluh unit.
 *
 * Seluruhnya data dummy. Surel memakai domain `bpma.internal` yang tidak akan
 * pernah ada, dan kata sandinya seragam — khusus demo lokal, bukan praktik yang
 * boleh ditiru.
 *
 * Empat akun bertanda "skenario demo" wajib ada dengan jabatan dan unit persis
 * seperti tertulis: matriks pengujian otorisasi (`PRD.md` §4.2) bergantung
 * padanya.
 */
final class UserSeeder extends Seeder
{
    private const KATA_SANDI_DEMO = 'password';

    /**
     * Akun yang sengaja dinonaktifkan, sebagai bahan uji penolakan saat masuk.
     *
     * @var list<string>
     */
    private const NONAKTIF = [
        'teuku.fahmi@bpma.internal',
        'doni.saputra@bpma.internal',
        'yuni.kartika@bpma.internal',
    ];

    /**
     * @var list<array{nama: string, jabatan: string, unit: string|null}>
     */
    private const AKUN = [
        // -- Pimpinan tertinggi: tanpa unit, melihat seluruh dokumen ----------
        ['nama' => 'Budi Santoso', 'jabatan' => 'Kepala BPMA', 'unit' => null],
        ['nama' => 'Siti Aminah', 'jabatan' => 'Wakil Kepala BPMA', 'unit' => null],

        // -- Sekretaris & Deputi ---------------------------------------------
        ['nama' => 'Andi Wijaya', 'jabatan' => 'Sekretaris', 'unit' => 'Sekretaris BPMA'],
        ['nama' => 'Rina Kartika', 'jabatan' => 'Deputi', 'unit' => 'Deputi Perencanaan'],
        ['nama' => 'Teguh Prasetyo', 'jabatan' => 'Deputi', 'unit' => 'Deputi Operasi'],
        ['nama' => 'Nurul Hidayati', 'jabatan' => 'Deputi', 'unit' => 'Deputi Keuangan dan Monetisasi'],
        // Akun B skenario demo — akses lewat jenjang jabatan, bukan unit.
        ['nama' => 'Hasan Basri', 'jabatan' => 'Deputi', 'unit' => 'Deputi Dukungan Bisnis'],

        // -- Kepala Divisi: satu per divisi ----------------------------------
        ['nama' => 'Yusuf Maulana', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi SDM dan Umum'],
        ['nama' => 'Sri Wahyuni', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Keuangan Internal'],
        ['nama' => 'Bambang Iriawan', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Hukum, Program & Pelaporan'],
        ['nama' => 'Hendra Gunawan', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Perencanaan Eksplorasi dan Eksploitasi'],
        ['nama' => 'Lukman Hakim', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Teknologi dan Pengembangan Lapangan'],
        ['nama' => 'Ratna Dewi', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Pengendalian Program dan Anggaran'],
        ['nama' => 'Irfan Nurdin', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Operasi Produksi'],
        ['nama' => 'Zainal Abidin', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Perawatan Fasilitas dan Pengendalian Proyek'],
        ['nama' => 'Rahmat Hidayat', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Penunjang Operasi'],
        ['nama' => 'Dian Anggraini', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Akuntansi, Perpajakan dan Manajemen Risiko'],
        ['nama' => 'Muhammad Ridwan', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Audit Kontraktor Kontrak Kerja Sama Eksplorasi & Eksploitasi'],
        ['nama' => 'Cut Rahmawati', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Monetisasi Minyak dan Gas Bumi'],
        ['nama' => 'Fajar Setiawan', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Pengelolaan Aset dan Rantai Suplai'],
        ['nama' => 'Nurul Fajri', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Formalitas, Hubungan Eksternal dan Sekuriti K3KS'],
        ['nama' => 'Dedi Kurniawan', 'jabatan' => 'Kepala Divisi', 'unit' => 'Divisi Manajemen Sistem Teknologi Informasi'],

        // -- Anggota ---------------------------------------------------------
        ['nama' => 'Putri Amelia', 'jabatan' => 'Anggota', 'unit' => 'Divisi SDM dan Umum'],
        ['nama' => 'Teuku Fahmi', 'jabatan' => 'Anggota', 'unit' => 'Divisi SDM dan Umum'],
        // Akun C skenario demo — akses lewat namanya di document_shares.
        ['nama' => 'Maya Puspita', 'jabatan' => 'Anggota', 'unit' => 'Divisi Keuangan Internal'],
        ['nama' => 'Aulia Rahman', 'jabatan' => 'Anggota', 'unit' => 'Divisi Keuangan Internal'],
        ['nama' => 'Nadia Safitri', 'jabatan' => 'Anggota', 'unit' => 'Divisi Hukum, Program & Pelaporan'],
        ['nama' => 'Reza Fahlevi', 'jabatan' => 'Anggota', 'unit' => 'Divisi Perencanaan Eksplorasi dan Eksploitasi'],
        ['nama' => 'Salsabila Putri', 'jabatan' => 'Anggota', 'unit' => 'Divisi Perencanaan Eksplorasi dan Eksploitasi'],
        ['nama' => 'Arif Budiman', 'jabatan' => 'Anggota', 'unit' => 'Divisi Teknologi dan Pengembangan Lapangan'],
        ['nama' => 'Cut Nurhaliza', 'jabatan' => 'Anggota', 'unit' => 'Divisi Teknologi dan Pengembangan Lapangan'],
        ['nama' => 'Ilham Akbar', 'jabatan' => 'Anggota', 'unit' => 'Divisi Pengendalian Program dan Anggaran'],
        // Akun D skenario demo — kontrol negatif, tidak memenuhi mekanisme mana pun.
        ['nama' => 'Rizki Ananda', 'jabatan' => 'Anggota', 'unit' => 'Divisi Operasi Produksi'],
        ['nama' => 'Dewi Lestari', 'jabatan' => 'Anggota', 'unit' => 'Divisi Operasi Produksi'],
        ['nama' => 'Muhammad Iqbal', 'jabatan' => 'Anggota', 'unit' => 'Divisi Perawatan Fasilitas dan Pengendalian Proyek'],
        ['nama' => 'Anisa Rahmadani', 'jabatan' => 'Anggota', 'unit' => 'Divisi Perawatan Fasilitas dan Pengendalian Proyek'],
        ['nama' => 'Faisal Amri', 'jabatan' => 'Anggota', 'unit' => 'Divisi Penunjang Operasi'],
        ['nama' => 'Intan Permata', 'jabatan' => 'Anggota', 'unit' => 'Divisi Akuntansi, Perpajakan dan Manajemen Risiko'],
        ['nama' => 'Doni Saputra', 'jabatan' => 'Anggota', 'unit' => 'Divisi Akuntansi, Perpajakan dan Manajemen Risiko'],
        ['nama' => 'Yuni Kartika', 'jabatan' => 'Anggota', 'unit' => 'Divisi Audit Kontraktor Kontrak Kerja Sama Eksplorasi & Eksploitasi'],
        ['nama' => 'Taufik Hidayat', 'jabatan' => 'Anggota', 'unit' => 'Divisi Monetisasi Minyak dan Gas Bumi'],
        ['nama' => 'Rina Marlina', 'jabatan' => 'Anggota', 'unit' => 'Divisi Pengelolaan Aset dan Rantai Suplai'],
        ['nama' => 'Andri Kurniawan', 'jabatan' => 'Anggota', 'unit' => 'Divisi Formalitas, Hubungan Eksternal dan Sekuriti K3KS'],
        // Akun A skenario demo — akses lewat unit yang dituju dokumen.
        ['nama' => 'Fitri Handayani', 'jabatan' => 'Anggota', 'unit' => 'Divisi Manajemen Sistem Teknologi Informasi'],
        ['nama' => 'Agus Salim', 'jabatan' => 'Anggota', 'unit' => 'Divisi Manajemen Sistem Teknologi Informasi'],
    ];

    public function run(): void
    {
        // Dimuat sekali sebagai peta nama → id, supaya perulangan di bawah
        // tidak memicu satu query per akun.
        $jabatanIds = Jabatan::pluck('id', 'nama');
        $unitIds = Unit::pluck('id', 'nama');
        $kataSandi = Hash::make(self::KATA_SANDI_DEMO);

        foreach (self::AKUN as $akun) {
            $email = self::emailDari($akun['nama']);

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $akun['nama'],
                    'password' => $kataSandi,
                    'jabatan_id' => $jabatanIds[$akun['jabatan']],
                    'unit_id' => $akun['unit'] === null ? null : $unitIds[$akun['unit']],
                    'is_active' => ! in_array($email, self::NONAKTIF, true),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([User::ROLE_PENGGUNA]);
        }
    }

    /**
     * "Fitri Handayani" menjadi "fitri.handayani@bpma.internal".
     */
    private static function emailDari(string $nama): string
    {
        return Str::slug($nama, '.').'@bpma.internal';
    }
}

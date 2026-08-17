<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Unit;

/**
 * Menentukan unit mana yang benar-benar disimpan pada sebuah dokumen.
 *
 * **Yang tersimpan adalah persis yang dikirim** — tidak ada unit yang ditambahkan
 * diam-diam oleh server. Cascade "memilih Deputi berarti seluruh divisinya ikut"
 * (FR-39) diselesaikan di antarmuka: `UnitTreePicker` mencentang induk beserta
 * seluruh anaknya sekaligus, lalu mengirim daftar lengkapnya.
 *
 * Sebelumnya server yang menurunkan pohon itu, dan akibatnya menghapus satu
 * divisi menjadi mustahil: selama induknya masih tercentang, divisi yang baru
 * saja dibuang pengguna dipasang kembali saat menyimpan. Pengaturan manual
 * pengguna diabaikan tanpa satu pun pesan — persis yang dilarang FR-39 dan
 * `Catatan_Audit.md` isu #15.
 *
 * Dengan aturan sekarang, isi `document_units` selalu mencerminkan apa yang
 * dilihat pengguna di layar saat ia menekan simpan.
 */
final class DocumentUnitResolver
{
    /**
     * Menyaring pilihan unit menjadi bentuk yang layak disimpan.
     *
     * Hanya unit AKTIF yang boleh dipasang baru — unit nonaktif tidak lagi
     * menjadi sasaran baru, walau yang sudah tercatat di dokumen lama tetap utuh
     * (`Struktur_Data.md` §3.3). Duplikat dibuang supaya pivot tidak menerima
     * baris kembar.
     *
     * @param  list<int>  $unitIds
     * @return list<int>
     */
    public function untukDisimpan(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        return Unit::query()
            ->active()
            ->whereKey(array_unique($unitIds))
            ->pluck('id')
            ->map(intval(...))
            ->all();
    }
}

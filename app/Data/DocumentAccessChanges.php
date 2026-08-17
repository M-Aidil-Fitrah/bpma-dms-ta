<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Selisih akses dokumen setelah satu penyimpanan.
 *
 * Nama target disimpan saat perubahan masih berada di dalam transaksi. Dengan
 * begitu jejak pencabutan tetap menjawab “akses siapa/unit apa yang dicabut”,
 * tanpa harus menebak lagi dari pivot yang barisnya sudah dihapus.
 */
final readonly class DocumentAccessChanges
{
    /**
     * @param  list<array{id: int, nama: string}>  $unitDitambahkan
     * @param  list<array{id: int, nama: string}>  $unitDicabut
     * @param  list<array{id: int, nama: string}>  $penggunaDitambahkan
     * @param  list<array{id: int, nama: string}>  $penggunaDicabut
     */
    public function __construct(
        public array $unitDitambahkan,
        public array $unitDicabut,
        public array $penggunaDitambahkan,
        public array $penggunaDicabut,
    ) {}
}

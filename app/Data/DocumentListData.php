<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Support\Inisial;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Satu baris dokumen pada daftar, hasil pencarian, dan ringkasan dasbor.
 *
 * `extracted_text` SENGAJA tidak ada di sini, dan ketiadaannya bukan kelalaian
 * melainkan penegakan. Kolom itu bertipe `longText` dan dapat berukuran
 * megabyte per baris; ikut memuatnya untuk dua puluh baris berarti menyeret
 * puluhan megabyte ke memori demi data yang tidak pernah ditampilkan. Dengan
 * memisahkan DTO daftar dari DTO detail, aturan itu ditegakkan sistem tipe —
 * bukan bergantung pada ingatan orang yang menulis query berikutnya.
 */
#[TypeScript]
final class DocumentListData extends Data
{
    /**
     * @param  list<string>|null  $ringkasan_akses  null bila sengaja tidak dihitung
     */
    public function __construct(
        public int $id,
        public string $nomor,
        public string $judul,
        public ?string $kategori,
        public ?string $unit_asal,
        public string $tanggal,
        public ?string $masa_berlaku,
        public DocumentStatus $status,
        public ExtractionStatus $extraction_status,
        public string $tipe_berkas,
        public int $ukuran_berkas,
        public ?string $pengunggah,
        public string $inisial_pengunggah,
        public ?array $ringkasan_akses,
    ) {}

    /**
     * Bentuk lengkap, termasuk ringkasan mekanisme akses.
     *
     * Memerlukan relasi `targetUnits` dan `sharedUsers` sudah dimuat.
     */
    public static function fromModel(Document $document): self
    {
        return self::bentuk($document, $document->accessSummary());
    }

    /**
     * Bentuk ringkas untuk tempat yang tidak menampilkan ringkasan akses,
     * seperti kartu-kartu di dasbor.
     *
     * Dipisah supaya pemanggilnya tidak perlu memuat `targetUnits` dan
     * `sharedUsers` hanya untuk dibuang — dua relasi itu menambah dua query per
     * daftar, dan pada dasbor yang punya beberapa daftar biayanya berlipat.
     * Menghitung sesuatu yang tidak pernah ditampilkan adalah pemborosan yang
     * paling mudah luput dari perhatian, karena hasilnya tetap terlihat benar.
     */
    public static function ringkas(Document $document): self
    {
        return self::bentuk($document, null);
    }

    /**
     * @param  list<string>|null  $ringkasanAkses
     */
    private static function bentuk(Document $document, ?array $ringkasanAkses): self
    {
        return new self(
            id: $document->id,
            nomor: $document->nomor,
            judul: $document->judul,
            kategori: $document->category?->nama,
            unit_asal: $document->originUnit?->nama,
            tanggal: $document->tanggal->toDateString(),
            masa_berlaku: $document->masa_berlaku?->toDateString(),
            status: $document->status,
            extraction_status: $document->extraction_status,
            tipe_berkas: $document->file_mime_type,
            ukuran_berkas: $document->file_size,
            pengunggah: $document->uploader?->name,
            inisial_pengunggah: Inisial::dari($document->uploader?->name),
            ringkasan_akses: $ringkasanAkses,
        );
    }
}

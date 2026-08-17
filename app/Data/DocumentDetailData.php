<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DocumentEditScope;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Services\DocumentThumbnailService;
use App\Support\Inisial;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Dokumen tunggal beserta seluruh metadatanya (FR-07).
 *
 * Terpisah dari `DocumentListData` bukan karena kebetulan, melainkan karena
 * keduanya punya batasan yang berbeda. DTO daftar sengaja tidak boleh memuat
 * `extracted_text`; DTO ini justru wajib memuatnya, karena panel teks pada
 * pratinjau dokumen non-visual dibangun dari sana. Menyatukan keduanya berarti
 * kehilangan penjagaan yang membuat halaman daftar tetap ringan.
 */
#[TypeScript]
final class DocumentDetailData extends Data
{
    /**
     * @param  list<string>  $ringkasan_akses
     * @param  list<string>  $unit_tujuan
     * @param  list<string>  $orang_tertentu
     */
    public function __construct(
        public int $id,
        public string $nomor,
        public string $judul,
        public ?string $deskripsi,
        public ?string $kategori,
        public ?string $unit_asal,
        public string $tanggal,
        public ?string $masa_berlaku,
        public DocumentStatus $status,

        // -- Berkas -----------------------------------------------------------
        public string $nama_berkas,
        public string $tipe_berkas,
        public int $ukuran_berkas,
        public ExtractionStatus $extraction_status,
        public ?int $halaman_ekstraksi_total,
        public ?int $halaman_ekstraksi_selesai,
        public ?int $estimasi_ekstraksi_detik,
        public ?string $pesan_ekstraksi,
        public bool $preview_tersedia,
        /**
         * Berkas Office yang PDF konversinya belum jadi — antarmuka
         * menunggu, bukan langsung menyerah ke fallback unduh, karena job
         * `GenerateDocumentThumbnailJob` masih mungkin berjalan.
         */
        public bool $pratinjau_sedang_disiapkan,
        /** Isi teks hasil ekstraksi; dasar pratinjau untuk berkas non-visual. */
        public ?string $isi_teks,

        // -- Pengunggah -------------------------------------------------------
        public ?string $pengunggah,
        public ?string $jabatan_pengunggah,
        public ?string $unit_pengunggah,
        public string $inisial_pengunggah,
        public string $diunggah_pada,
        public string $diperbarui_pada,

        // -- Mekanisme akses --------------------------------------------------
        public array $ringkasan_akses,
        public bool $dibagikan_ke_semua,
        public ?int $min_tingkat_akses,
        public array $unit_tujuan,
        public array $orang_tertentu,
        public DocumentEditScope $edit_scope,
        public string $label_edit_scope,

        // -- Keadaan & wewenang pengguna yang sedang membuka ------------------
        /** Dokumen nonaktif hanya terlihat Superadmin (FR-10). */
        public bool $aktif,
        public bool $boleh_ubah,
        public bool $boleh_nonaktifkan,
        public bool $boleh_aktifkan,
    ) {}

    public static function fromModel(
        Document $document,
        bool $bolehUbah,
        bool $bolehAktifkan = false,
    ): self {
        return new self(
            id: $document->id,
            nomor: $document->nomor,
            judul: $document->judul,
            deskripsi: $document->deskripsi,
            kategori: $document->category?->nama,
            unit_asal: $document->originUnit?->nama,
            tanggal: $document->tanggal->toDateString(),
            masa_berlaku: $document->masa_berlaku?->toDateString(),
            status: $document->status,

            nama_berkas: $document->file_name_original,
            tipe_berkas: $document->file_mime_type,
            ukuran_berkas: $document->file_size,
            extraction_status: $document->extraction_status,
            halaman_ekstraksi_total: $document->extraction_pages_total,
            halaman_ekstraksi_selesai: $document->extraction_pages_processed,
            estimasi_ekstraksi_detik: $document->extraction_estimated_seconds,
            pesan_ekstraksi: $document->extraction_message,
            preview_tersedia: $document->preview_path !== null,
            pratinjau_sedang_disiapkan: $document->preview_path === null
                && in_array($document->file_mime_type, DocumentThumbnailService::MIME_OFFICE, true),
            isi_teks: $document->extracted_text,

            pengunggah: $document->uploader?->name,
            jabatan_pengunggah: $document->uploader?->jabatan?->nama,
            unit_pengunggah: $document->uploader?->unit?->nama,
            inisial_pengunggah: Inisial::dari($document->uploader?->name),
            diunggah_pada: $document->created_at->toIso8601String(),
            diperbarui_pada: $document->updated_at->toIso8601String(),

            ringkasan_akses: $document->accessSummary(),
            dibagikan_ke_semua: $document->is_shared_to_all,
            min_tingkat_akses: $document->min_tingkat_akses,
            unit_tujuan: $document->targetUnits->pluck('nama')->all(),
            orang_tertentu: $document->sharedUsers->pluck('name')->all(),
            edit_scope: $document->edit_scope,
            label_edit_scope: $document->edit_scope->label(),

            aktif: $document->is_active,
            boleh_ubah: $bolehUbah,
            boleh_nonaktifkan: $bolehUbah,
            boleh_aktifkan: $bolehAktifkan,
        );
    }
}

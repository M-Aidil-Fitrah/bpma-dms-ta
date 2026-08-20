<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Models\User;
use App\Support\Inisial;
use App\Support\PenyajianBerkas;
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
        public bool $thumbnail_tersedia,
        /** Hanya ditampilkan bila rute pratinjau dapat menyajikan berkas inline dengan aman. */
        public bool $bisa_pratinjau_di_tab_baru,
        public ?string $pengunggah,
        public ?string $jabatan_pengunggah,
        public string $inisial_pengunggah,
        public ?array $ringkasan_akses,
        /** Alasan dokumen ini terlihat bagi pengguna yang sedang membuka daftar (FEAT-12). Null bila sengaja tidak dihitung. */
        public ?string $alasan_terlihat,
        /** @var list<string>|null Kolom dokumen yang cocok dengan kueri; null bila daftar tidak sedang mencari. */
        public ?array $kecocokan_pencarian,
        /** Potongan isi terbatas dari projection pencarian; bukan `extracted_text` penuh. */
        public ?string $cuplikan_pencarian,
        /** Jumlah frasa persis dalam isi, null bila frasa tidak ditemukan. */
        public ?int $jumlah_frasa_pencarian,
    ) {}

    /**
     * Bentuk lengkap, termasuk ringkasan mekanisme akses dan alasan
     * dokumen ini terlihat bagi `$user`.
     *
     * Memerlukan relasi `targetUnits` dan `sharedUsers` sudah dimuat.
     */
    public static function fromModel(Document $document, User $user): self
    {
        return self::bentuk(
            $document,
            ringkasanAkses: $document->accessSummary(),
            alasanTerlihat: $document->alasanTerlihat($user),
            jabatanPengunggah: $document->uploader?->jabatan?->nama,
        );
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
        return self::bentuk($document, ringkasanAkses: null, alasanTerlihat: null, jabatanPengunggah: null);
    }

    /**
     * @param  list<string>|null  $ringkasanAkses
     */
    private static function bentuk(
        Document $document,
        ?array $ringkasanAkses,
        ?string $alasanTerlihat,
        ?string $jabatanPengunggah,
    ): self {
        $konteksPencarian = self::konteksPencarian($document);

        return new self(
            id: $document->id,
            nomor: $document->nomor,
            judul: $document->judul,
            kategori: $document->category?->nama,
            unit_asal: $document->originUnit?->nama ?? 'Pimpinan BPMA',
            tanggal: $document->tanggal->toDateString(),
            masa_berlaku: $document->masa_berlaku?->toDateString(),
            status: $document->status,
            extraction_status: $document->extraction_status,
            tipe_berkas: $document->file_mime_type,
            ukuran_berkas: $document->file_size,
            thumbnail_tersedia: $document->thumbnail_path !== null,
            bisa_pratinjau_di_tab_baru: $document->preview_path !== null
                || PenyajianBerkas::bolehInline($document->file_mime_type),
            pengunggah: $document->uploader?->name,
            jabatan_pengunggah: $jabatanPengunggah,
            inisial_pengunggah: Inisial::dari($document->uploader?->name),
            ringkasan_akses: $ringkasanAkses,
            alasan_terlihat: $alasanTerlihat,
            kecocokan_pencarian: $konteksPencarian['kecocokan_pencarian'],
            cuplikan_pencarian: $konteksPencarian['cuplikan_pencarian'],
            jumlah_frasa_pencarian: $konteksPencarian['jumlah_frasa_pencarian'],
        );
    }

    /**
     * Mengubah projection SQL yang kecil menjadi data presentasi. Tidak ada
     * jalur di sini yang pernah membaca `extracted_text` penuh.
     *
     * @return array{kecocokan_pencarian: list<string>|null, cuplikan_pencarian: string|null, jumlah_frasa_pencarian: int|null}
     */
    private static function konteksPencarian(Document $document): array
    {
        if (! array_key_exists('search_matches_nomor', $document->getAttributes())) {
            return [
                'kecocokan_pencarian' => null,
                'cuplikan_pencarian' => null,
                'jumlah_frasa_pencarian' => null,
            ];
        }

        $kecocokan = [];
        foreach ([
            'search_matches_nomor' => 'Nomor',
            'search_matches_judul' => 'Judul',
            'search_matches_deskripsi' => 'Deskripsi',
            'search_matches_isi' => 'Isi dokumen',
        ] as $atribut => $label) {
            if ((bool) $document->getAttribute($atribut)) {
                $kecocokan[] = $label;
            }
        }

        $cuplikan = $document->getAttribute('search_excerpt');
        $jumlahFrasa = (int) ($document->getAttribute('search_phrase_count') ?? 0);

        return [
            'kecocokan_pencarian' => $kecocokan,
            'cuplikan_pencarian' => is_string($cuplikan) && $cuplikan !== '' ? $cuplikan : null,
            'jumlah_frasa_pencarian' => $jumlahFrasa > 0 ? $jumlahFrasa : null,
        ];
    }
}

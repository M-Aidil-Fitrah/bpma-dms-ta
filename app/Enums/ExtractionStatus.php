<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Status ekstraksi isi berkas menjadi teks yang dapat dicari.
 *
 * Kolom ini ada untuk menutup ambiguitas lama: `extracted_text` bernilai null
 * dulu bisa berarti "masih diproses", "gagal", atau "tipe berkasnya memang tidak
 * mendukung" — tiga keadaan yang sangat berbeda bagi pengguna, tapi tidak dapat
 * dibedakan (`Catatan_Audit.md` isu #9).
 */
#[TypeScript]
enum ExtractionStatus: string
{
    /** Tipe berkas tidak mendukung ekstraksi. Tidak ada job yang pernah dibuat. */
    case NotApplicable = 'not_applicable';

    /** Job sudah dilempar ke antrian, menunggu diproses. */
    case Pending = 'pending';

    /** Ekstraksi selesai. Teksnya bisa saja kosong — mis. PDF hasil pindaian. */
    case Completed = 'completed';

    /** Ekstraksi gagal permanen setelah percobaan ulang habis. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotApplicable => 'Lampiran biasa',
            self::Pending => 'Memproses ekstraksi',
            self::Completed => 'Dapat dicari',
            self::Failed => 'Ekstraksi gagal',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NotApplicable => 'Tipe berkas ini tidak mendukung pencarian isi. Berkas tetap dapat diunduh.',
            self::Pending => 'Isi dokumen sedang dibaca di latar belakang. Perlu beberapa saat.',
            self::Completed => 'Isi dokumen sudah terbaca dan dapat ditemukan lewat pencarian.',
            self::Failed => 'Isi dokumen tidak dapat dibaca. Berkas tetap dapat diunduh seperti biasa.',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * Validasi penyuntingan dokumen (FR-08, FR-08b, FR-42).
 *
 * Aturannya sama persis dengan formulir unggah kecuali satu hal: **berkasnya
 * tidak dapat diganti**. Mengganti isi berkas sambil mempertahankan nomor,
 * riwayat, dan jejak aktivitas yang sama berarti dokumen yang pernah disetujui
 * seseorang diam-diam berubah isinya — dan tidak ada jejak bahwa itu terjadi.
 * Berkas baru berarti dokumen baru.
 */
final class UpdateDocumentRequest extends DocumentFormRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function aturanTambahan(): array
    {
        return [];
    }

    protected function periksaTambahan(Validator $v): void
    {
        // Bukan sekadar diabaikan diam-diam. Kalau ada berkas terkirim ke sini,
        // sesuatu di antarmuka salah — dan pengunggah berhak tahu berkasnya
        // TIDAK tersimpan alih-alih mengira penggantiannya berhasil.
        if ($this->hasFile('file')) {
            $v->errors()->add(
                'file',
                'Berkas dokumen tidak dapat diganti. Unggah sebagai dokumen baru '
                .'bila isinya berubah.',
            );
        }
    }
}

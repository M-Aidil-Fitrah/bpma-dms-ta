<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\BatasUnggah;
use Illuminate\Validation\Validator;

/**
 * Validasi penyimpanan dokumen baru (FR-06, FR-12, FR-37).
 *
 * Seluruh aturan metadata dan mekanisme akses diwarisi dari
 * `DocumentFormRequest`; yang khas di sini hanya berkasnya — wajib ada, dibatasi
 * ukurannya, dan ditolak bila ekstensinya berbahaya.
 */
final class StoreDocumentRequest extends DocumentFormRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function aturanTambahan(): array
    {
        return [
            // -- Berkas (FR-12) -----------------------------------------------
            'file' => [
                'required',
                'file',
                // Batas ukuran mengikuti lingkungan, bukan angka tetap di kode.
                // Kosong berarti tidak ada batas yang dapat ditegakkan aplikasi.
                ...BatasUnggah::aturanValidasi(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'file.required' => 'Berkas dokumen wajib dipilih.',
            'file.max' => 'Ukuran berkas melebihi batas yang berlaku ('.BatasUnggah::keterangan().').',
        ];
    }

    protected function periksaTambahan(Validator $v): void
    {
        $this->pastikanEkstensiTidakTerlarang($v);
    }

    /**
     * Menolak ekstensi berbahaya (`PRD.md` §8.2).
     *
     * Diperiksa terhadap ekstensi asli yang dikirim klien, bukan hasil tebakan
     * dari tipe MIME. Berkas `.php` yang dikirim dengan tipe `text/plain` tetap
     * berakhir sebagai `.php` di disk, dan itulah yang berbahaya.
     */
    private function pastikanEkstensiTidakTerlarang(Validator $v): void
    {
        $berkas = $this->file('file');

        if ($berkas === null || is_array($berkas)) {
            return;
        }

        $ekstensi = strtolower($berkas->getClientOriginalExtension());

        if (in_array($ekstensi, config('dms.dokumen.ekstensi_terlarang'), true)) {
            $v->errors()->add(
                'file',
                "Berkas berekstensi .{$ekstensi} tidak dapat diunggah karena alasan keamanan.",
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DocumentEditScope;
use App\Support\BatasUnggah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi penyimpanan dokumen baru (FR-06, FR-12, FR-37).
 *
 * Aturan yang paling menentukan ada di `withValidator()`: dokumen tidak boleh
 * disimpan tanpa satu pun mekanisme akses aktif. Tanpa itu, dokumen hanya
 * terlihat pengunggahnya sendiri, Superadmin, dan jabatan tingkat 1 — hampir
 * pasti bukan yang dimaksud orang yang baru saja mengunggahnya.
 */
final class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi sesungguhnya ditegakkan Policy di controller. Di sini cukup
        // memastikan permintaan datang dari pengguna yang sudah masuk.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // -- Metadata (FR-13) ---------------------------------------------
            'nomor' => ['required', 'string', 'max:50'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],

            // Hanya kategori dan unit AKTIF yang boleh dipilih. Pemeriksaannya
            // di sini, bukan hanya di dropdown: entitas bisa saja dinonaktifkan
            // Superadmin setelah halaman terbuka tapi sebelum formulir dikirim.
            'category_id' => [
                'required', 'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'origin_unit_id' => [
                'nullable', 'integer',
                Rule::exists('units', 'id')->where('is_active', true),
            ],

            'tanggal' => ['required', 'date'],
            'masa_berlaku' => ['nullable', 'date', 'after_or_equal:tanggal'],

            // -- Berkas (FR-12) -----------------------------------------------
            'file' => [
                'required',
                'file',
                // Batas ukuran mengikuti lingkungan, bukan angka tetap di kode.
                // Kosong berarti tidak ada batas yang dapat ditegakkan aplikasi.
                ...BatasUnggah::aturanValidasi(),
            ],

            // -- Mekanisme akses (FR-37 s.d. FR-41) ---------------------------
            'is_shared_to_all' => ['boolean'],

            'min_tingkat_akses' => [
                'nullable', 'integer',
                // Divalidasi terhadap jenjang yang benar-benar ada, bukan
                // rentang angka tetap — daftar jabatan bersifat dinamis dan
                // dapat bertambah tanpa menyentuh kode.
                Rule::exists('jabatans', 'tingkat_akses')->where('is_active', true),
            ],

            'unit_ids' => ['array'],
            'unit_ids.*' => [
                'integer',
                Rule::exists('units', 'id')->where('is_active', true),
            ],

            'shared_user_ids' => ['array'],
            'shared_user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('is_active', true),
            ],

            'edit_scope' => ['required', Rule::enum(DocumentEditScope::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Berkas dokumen wajib dipilih.',
            'file.max' => 'Ukuran berkas melebihi batas yang berlaku ('.BatasUnggah::keterangan().').',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists' => 'Kategori yang dipilih tidak tersedia lagi.',
            'origin_unit_id.exists' => 'Unit asal yang dipilih tidak tersedia lagi.',
            'masa_berlaku.after_or_equal' => 'Masa berlaku tidak boleh mendahului tanggal dokumen.',
            'min_tingkat_akses.exists' => 'Jenjang jabatan yang dipilih tidak tersedia lagi.',
            'unit_ids.*.exists' => 'Salah satu unit yang dipilih sudah tidak aktif.',
            'shared_user_ids.*.exists' => 'Salah satu pengguna yang dipilih sudah tidak aktif.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Checkbox yang tidak dicentang tidak ikut terkirim sama sekali.
        // Tanpa nilai bawaan ini, aturan `boolean` tidak pernah berjalan dan
        // kolomnya berakhir null alih-alih false.
        $this->merge([
            'is_shared_to_all' => $this->boolean('is_shared_to_all'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->pastikanAdaMekanismeAkses($v);
            $this->pastikanEkstensiTidakTerlarang($v);
        });
    }

    /**
     * Minimal satu dari empat mekanisme akses wajib aktif (FR-37).
     *
     * Kriteria Penerimaan #9 — dokumen tanpa mekanisme apa pun harus ditolak.
     */
    private function pastikanAdaMekanismeAkses(Validator $v): void
    {
        $adaSalahSatu = $this->boolean('is_shared_to_all')
            || $this->filled('min_tingkat_akses')
            || filled($this->input('unit_ids'))
            || filled($this->input('shared_user_ids'));

        if (! $adaSalahSatu) {
            $v->errors()->add(
                'akses',
                'Aktifkan minimal satu mekanisme akses. Tanpa itu, dokumen hanya '
                .'dapat dilihat oleh Anda sendiri.',
            );
        }
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

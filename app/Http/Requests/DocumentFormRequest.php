<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DocumentEditScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Aturan yang berlaku sama saat dokumen dibuat maupun disunting.
 *
 * Metadata dan mekanisme akses tunduk pada aturan yang persis sama di kedua
 * jalur — dan itu bukan kebetulan yang boleh dibiarkan menjadi dua salinan.
 * Menyalinnya berarti suatu saat salah satu diperketat dan yang lain tidak,
 * sehingga aturan yang ditolak di formulir unggah justru lolos lewat formulir
 * ubah. Yang membedakan hanyalah berkasnya: wajib saat mengunggah, dan sama
 * sekali tidak dapat diganti saat menyunting (FR-42).
 */
abstract class DocumentFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi sesungguhnya ditegakkan Policy di controller. Di sini cukup
        // memastikan permintaan datang dari pengguna yang sudah masuk.
        return $this->user() !== null;
    }

    /**
     * Aturan tambahan khusus turunan, mis. berkas pada formulir unggah.
     *
     * @return array<string, mixed>
     */
    abstract protected function aturanTambahan(): array;

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

            // Catatan hanya diwajibkan oleh jalur yang benar-benar membuat
            // revisi (UpdateDocumentRequest atau unggahan pengganti).
            'version_note' => ['nullable', 'string', 'max:500'],

            ...$this->aturanTambahan(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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
            $this->periksaTambahan($v);
        });
    }

    /**
     * Pemeriksaan lanjutan khusus turunan. Bawaannya tidak melakukan apa pun.
     */
    protected function periksaTambahan(Validator $v): void {}

    /**
     * Kolom yang boleh disimpan langsung ke kolom tabel `documents`.
     *
     * Dikumpulkan di sini supaya controller tidak perlu mengulang daftar yang
     * sama untuk simpan dan ubah — daftar yang berbeda di dua tempat berarti
     * suatu saat ada kolom yang bisa diubah tapi tidak bisa diisi, atau
     * sebaliknya.
     *
     * @return array<string, mixed>
     */
    public function kolomDokumen(): array
    {
        return $this->safe()->only([
            'nomor', 'judul', 'deskripsi', 'category_id',
            'origin_unit_id', 'tanggal', 'masa_berlaku',
            'is_shared_to_all', 'min_tingkat_akses', 'edit_scope',
        ]);
    }

    /**
     * Id unit yang dipilih, sudah menjadi integer.
     *
     * @return list<int>
     */
    public function unitIds(): array
    {
        return array_map(intval(...), $this->input('unit_ids', []));
    }

    /**
     * Id pengguna yang diberi akses perorangan.
     *
     * @return list<int>
     */
    public function penerimaIds(): array
    {
        return array_map(intval(...), $this->input('shared_user_ids', []));
    }

    public function catatanVersi(): string
    {
        return trim((string) $this->input('version_note'));
    }

    /**
     * Minimal satu dari empat mekanisme akses wajib aktif (FR-37).
     *
     * Kriteria Penerimaan #9 — dokumen tanpa mekanisme apa pun harus ditolak.
     * Berlaku sama saat menyunting: mencabut mekanisme terakhir sama saja
     * dengan menyembunyikan dokumen dari semua orang tanpa menonaktifkannya,
     * dan itu hampir pasti bukan yang dimaksud.
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
}

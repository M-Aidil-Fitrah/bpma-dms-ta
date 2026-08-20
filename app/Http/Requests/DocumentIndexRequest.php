<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Penyaring dan pengurutan pada daftar dokumen (FR-16 s.d. FR-21).
 *
 * Nilai pengurutan divalidasi terhadap daftar tertutup, bukan diteruskan apa
 * adanya ke `orderBy()`. Kolom yang datang dari query string tidak pernah
 * di-escape Eloquent seperti halnya nilai, sehingga menerimanya mentah-mentah
 * membuka jalan injeksi lewat nama kolom.
 */
final class DocumentIndexRequest extends FormRequest
{
    /**
     * Kolom yang boleh dipakai mengurutkan, beserta kolom sesungguhnya di
     * basis data.
     *
     * @var array<string, string>
     */
    public const URUTAN = [
        'tanggal' => 'documents.tanggal',
        'judul' => 'documents.judul',
        'nomor' => 'documents.nomor',
        'masa_berlaku' => 'documents.masa_berlaku',
        'terbaru' => 'documents.created_at',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cari' => ['nullable', 'string', 'max:150'],
            'kategori' => ['nullable', 'integer', 'exists:categories,id'],
            'unit' => ['nullable', 'integer', 'exists:units,id'],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'status_ekstraksi' => ['nullable', Rule::enum(ExtractionStatus::class)],
            'pengunggah' => ['nullable', 'integer', 'exists:users,id'],
            'tipe' => ['nullable', Rule::in(['pdf', 'gambar', 'word', 'teks', 'lainnya'])],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'urut' => ['nullable', Rule::in(array_keys(self::URUTAN))],
            'arah' => ['nullable', Rule::in(['asc', 'desc'])],
            // `urut` selalu ikut dalam state antarmuka agar ikon kolom stabil.
            // Flag ini membedakan nilai bawaan itu dari pilihan urut sadar
            // pengguna, sehingga pencarian normal tetap boleh memakai ranking.
            'urut_manual' => ['nullable', 'boolean'],
            'tampilan' => ['nullable', Rule::in(['tabel', 'grid'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh mendahului tanggal awal.',
        ];
    }

    public function kolomUrutan(): string
    {
        return self::URUTAN[$this->string('urut')->toString()] ?? self::URUTAN['tanggal'];
    }

    public function arahUrutan(): string
    {
        return $this->string('arah')->toString() === 'asc' ? 'asc' : 'desc';
    }

    /**
     * Penyaring yang sedang aktif, untuk dikirim balik ke antarmuka.
     *
     * Dikembalikan ke halaman supaya keadaan penyaring bertahan setelah muat
     * ulang dan ikut terbawa saat alamatnya dibagikan — bukan disimpan di state
     * komponen yang hilang begitu halaman disegarkan.
     *
     * @return array<string, mixed>
     */
    public function filterAktif(): array
    {
        return [
            'cari' => $this->string('cari')->toString() ?: null,
            'kategori' => $this->integer('kategori') ?: null,
            'unit' => $this->integer('unit') ?: null,
            'status' => $this->string('status')->toString() ?: null,
            'status_ekstraksi' => $this->string('status_ekstraksi')->toString() ?: null,
            'pengunggah' => $this->integer('pengunggah') ?: null,
            'tipe' => $this->string('tipe')->toString() ?: null,
            'dari' => $this->string('dari')->toString() ?: null,
            'sampai' => $this->string('sampai')->toString() ?: null,
            'urut' => $this->string('urut')->toString() ?: 'tanggal',
            'urut_manual' => $this->boolean('urut_manual'),
            'tampilan' => $this->string('tampilan')->toString() === 'grid' ? 'grid' : 'tabel',
            'arah' => $this->arahUrutan(),
        ];
    }
}

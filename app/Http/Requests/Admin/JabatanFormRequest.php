<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Aturan bersama tambah dan ubah jabatan (FR-29). */
abstract class JabatanFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<int, mixed> */
    abstract protected function aturanNamaUnik(): array;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100', ...$this->aturanNamaUnik()],
            // Nilai kecil berarti wewenang lebih tinggi. Batas 255 mengikuti
            // unsignedTinyInteger di skema, bukan angka arbitrer di UI.
            'tingkat_akses' => ['required', 'integer', 'min:1', 'max:255'],
        ];
    }

    /** @return array{nama: string, tingkat_akses: int} */
    public function kolomJabatan(): array
    {
        return [
            'nama' => $this->string('nama')->trim()->toString(),
            'tingkat_akses' => $this->integer('tingkat_akses'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Aturan bersama tambah dan ubah kategori (FR-14). */
abstract class CategoryFormRequest extends FormRequest
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
            'deskripsi' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array{nama: string, deskripsi: string|null} */
    public function kolomKategori(): array
    {
        $deskripsi = $this->string('deskripsi')->trim()->toString();

        return [
            'nama' => $this->string('nama')->trim()->toString(),
            'deskripsi' => $deskripsi === '' ? null : $deskripsi,
        ];
    }
}

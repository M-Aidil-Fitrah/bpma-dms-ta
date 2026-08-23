<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validasi semua setelan yang memang berada pada allowlist PengaturanService. */
final class UpdatePengaturanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Minimum 1 MB menjaga formulir tidak menghasilkan batas yang
            // secara praktis tidak bisa dipakai untuk unggah dokumen.
            'unggah_batas_kb' => ['nullable', 'integer', 'min:1024', 'max:1048576'],
            'dokumen_per_halaman' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
        ];
    }
}

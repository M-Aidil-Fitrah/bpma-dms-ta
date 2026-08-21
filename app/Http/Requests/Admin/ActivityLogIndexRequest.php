<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ActivityLogName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Penyaring halaman pemantauan aktivitas Superadmin (FEAT-15b). */
final class ActivityLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi sesungguhnya ditegakkan middleware `superadmin` pada
        // seluruh grup rute. Di sini cukup memastikan pengguna sudah masuk.
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cari' => ['nullable', 'string', 'max:150'],
            'jenis' => ['nullable', Rule::in(array_column(ActivityLogName::cases(), 'value'))],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'pelaku' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'unit' => ['nullable', 'integer', Rule::exists('units', 'id')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{cari: string|null, jenis: string|null, dari: string|null, sampai: string|null, pelaku: int|null, unit: int|null} */
    public function filterAktif(): array
    {
        return [
            'cari' => $this->string('cari')->toString() ?: null,
            'jenis' => $this->string('jenis')->toString() ?: null,
            'dari' => $this->string('dari')->toString() ?: null,
            'sampai' => $this->string('sampai')->toString() ?: null,
            'pelaku' => $this->integer('pelaku') ?: null,
            'unit' => $this->integer('unit') ?: null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ActivityLogName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Penyaring aman untuk halaman riwayat aktivitas (FR-52). */
final class ActivityLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
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
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{cari: string|null, jenis: string|null, dari: string|null, sampai: string|null} */
    public function filterAktif(): array
    {
        return [
            'cari' => $this->string('cari')->toString() ?: null,
            'jenis' => $this->string('jenis')->toString() ?: null,
            'dari' => $this->string('dari')->toString() ?: null,
            'sampai' => $this->string('sampai')->toString() ?: null,
        ];
    }
}

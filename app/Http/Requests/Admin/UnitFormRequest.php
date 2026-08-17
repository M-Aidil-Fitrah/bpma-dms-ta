<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Unit;
use App\Services\UnitHierarchy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Aturan bersama tambah dan ubah unit organisasi (FR-28). */
abstract class UnitFormRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:150', ...$this->aturanNamaUnik()],
            // Induk yang nonaktif tidak boleh dipilih untuk hubungan BARU.
            // Relasi lama tetap dibiarkan, sesuai soft-disable.
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where('is_active', true),
            ],
            'tipe' => ['required', 'string', Rule::in([
                Unit::TIPE_SEKRETARIS,
                Unit::TIPE_DEPUTI,
                Unit::TIPE_DIVISI,
            ])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $unit = $this->route('unit');
            $parentId = $this->input('parent_id');

            if (! $unit instanceof Unit || $parentId === null || $parentId === '') {
                return;
            }

            if (app(UnitHierarchy::class)->membentukSiklus($unit, (int) $parentId)) {
                $validator->errors()->add(
                    'parent_id',
                    'Unit induk tidak boleh berupa unit ini sendiri atau salah satu divisinya.',
                );
            }
        });
    }

    /** @return array{nama: string, parent_id: int|null, tipe: string} */
    public function kolomUnit(): array
    {
        return [
            'nama' => $this->string('nama')->trim()->toString(),
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
            'tipe' => $this->string('tipe')->toString(),
        ];
    }
}

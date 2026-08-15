<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

final class UpdateJabatanRequest extends JabatanFormRequest
{
    protected function aturanNamaUnik(): array
    {
        return [Rule::unique('jabatans', 'nama')->ignore($this->route('jabatan'))];
    }
}

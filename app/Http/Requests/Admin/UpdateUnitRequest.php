<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

final class UpdateUnitRequest extends UnitFormRequest
{
    protected function aturanNamaUnik(): array
    {
        return [Rule::unique('units', 'nama')->ignore($this->route('unit'))];
    }
}

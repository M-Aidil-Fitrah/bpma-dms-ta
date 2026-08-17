<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

final class UpdateCategoryRequest extends CategoryFormRequest
{
    protected function aturanNamaUnik(): array
    {
        return [Rule::unique('categories', 'nama')->ignore($this->route('category'))];
    }
}

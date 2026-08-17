<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

final class StoreCategoryRequest extends CategoryFormRequest
{
    protected function aturanNamaUnik(): array
    {
        return [Rule::unique('categories', 'nama')];
    }
}

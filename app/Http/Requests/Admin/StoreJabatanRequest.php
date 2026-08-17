<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

final class StoreJabatanRequest extends JabatanFormRequest
{
    protected function aturanNamaUnik(): array
    {
        return [Rule::unique('jabatans', 'nama')];
    }
}

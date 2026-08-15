<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Superadmin mengatur ulang kata sandi akun secara langsung (FEAT-13) —
 * bukan lewat tautan surel seperti alur lupa kata sandi biasa, karena
 * akunnya sendiri yang mungkin tidak lagi dapat mengakses surelnya.
 */
final class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}

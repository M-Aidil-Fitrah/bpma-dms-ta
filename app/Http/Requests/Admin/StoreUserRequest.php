<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validasi penambahan akun baru (FR-25).
 *
 * Tidak ada registrasi publik — akun `pengguna` hanya bisa terbentuk lewat
 * formulir ini, dan Superadmin yang menentukan kata sandi awalnya secara
 * langsung, bukan lewat tautan surel.
 */
final class StoreUserRequest extends UserFormRequest
{
    protected function aturanSurelUnik(): array
    {
        return [Rule::unique('users', 'email')];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}

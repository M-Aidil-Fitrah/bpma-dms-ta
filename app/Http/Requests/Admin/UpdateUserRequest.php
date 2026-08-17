<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/**
 * Validasi penyuntingan akun (FR-26).
 *
 * Kata sandi TIDAK ada di sini — atur ulang kata sandi adalah aksi
 * tersendiri (`ResetPasswordRequest`), bukan bagian formulir sunting profil.
 * Menyatukan keduanya berarti kolom kata sandi kosong yang dikirim tak
 * sengaja bisa saja diproses sebagai "kosongkan kata sandi".
 */
final class UpdateUserRequest extends UserFormRequest
{
    protected function aturanSurelUnik(): array
    {
        // Dikecualikan terhadap dirinya sendiri — menyunting akun tanpa
        // mengubah surelnya bukan pelanggaran keunikan.
        return [Rule::unique('users', 'email')->ignore($this->route('user'))];
    }
}

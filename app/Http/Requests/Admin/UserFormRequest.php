<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Aturan yang berlaku sama saat akun ditambah maupun disunting (FEAT-13).
 *
 * Sama seperti `DocumentFormRequest`: satu salinan aturan, bukan dua yang
 * bisa diam-diam menyimpang. Yang khas di sini hanya surel (unik per akun,
 * kecuali terhadap dirinya sendiri saat menyunting) dan kata sandi (wajib
 * saat menambah, opsional saat menyunting — lihat turunannya).
 */
abstract class UserFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi sesungguhnya ditegakkan middleware `superadmin` pada
        // seluruh grup rute. Di sini cukup memastikan pengguna sudah masuk.
        return $this->user() !== null;
    }

    /**
     * @return array<int, mixed>
     */
    abstract protected function aturanSurelUnik(): array;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', ...$this->aturanSurelUnik()],

            // Hanya jabatan dan unit AKTIF yang boleh dipilih — bisa saja
            // dinonaktifkan Superadmin setelah halaman terbuka tapi sebelum
            // formulir dikirim.
            'jabatan_id' => [
                'required', 'integer',
                Rule::exists('jabatans', 'id')->where('is_active', true),
            ],
            'unit_id' => [
                'required', 'integer',
                Rule::exists('units', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function kolomPengguna(): array
    {
        return $this->only(['name', 'email', 'jabatan_id', 'unit_id']);
    }
}

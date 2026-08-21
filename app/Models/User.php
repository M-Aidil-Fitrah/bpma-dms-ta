<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'jabatan_id', 'unit_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_PENGGUNA = 'pengguna';

    /**
     * Nilai bawaan di memori, menyalin `default` pada migrasi.
     *
     * Tanpa ini, akun yang baru dibuat tanpa menyebut `is_active` bernilai null
     * sampai ia dibaca ulang dari basis data — sementara barisnya di basis data
     * sudah bernilai true. Ketimpangan itu membuat pemeriksaan seperti
     * `DocumentPolicy::create()` melihat akun aktif sebagai bukan-boolean.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // -- Relasi ---------------------------------------------------------------

    /**
     * Jabatan pengguna. Null hanya untuk Superadmin, yang berada di luar
     * struktur organisasi.
     *
     * @return BelongsTo<Jabatan, $this>
     */
    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    /**
     * Unit kerja pengguna. Null untuk Superadmin dan jabatan tingkat 1.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    // -- Pemeriksaan wewenang -------------------------------------------------

    public function isSuperadmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPERADMIN);
    }

    /**
     * Pimpinan BPMA tingkat 1 melihat seluruh dokumen tanpa terikat mekanisme
     * akses mana pun (FR-44).
     */
    public function isPimpinanTertinggi(): bool
    {
        // Operator `?->` wajib: Superadmin tidak berjabatan, dan memanggil
        // properti pada null akan menghentikan aplikasi
        // (`Catatan_Audit.md` isu #16).
        return $this->jabatan?->tingkat_akses === 1;
    }

    /**
     * Melewati seluruh mekanisme akses dokumen.
     */
    public function bypassesDocumentAccess(): bool
    {
        return $this->isSuperadmin() || $this->isPimpinanTertinggi();
    }

    // -- Scope ----------------------------------------------------------------

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_active'), true);
    }
}

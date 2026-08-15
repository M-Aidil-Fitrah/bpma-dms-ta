<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\JabatanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenjang jabatan. Data dinamis dengan penonaktifan, bukan penghapusan.
 *
 * @property int $tingkat_akses 1 = tertinggi; makin besar makin rendah
 */
#[Fillable(['nama', 'tingkat_akses', 'is_active'])]
class Jabatan extends Model
{
    /** @use HasFactory<JabatanFactory> */
    use HasFactory;

    /** Jenjang tertinggi — melewati seluruh mekanisme akses dokumen (FR-44). */
    public const TINGKAT_TERTINGGI = 1;

    protected function casts(): array
    {
        return [
            'tingkat_akses' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Hanya jabatan aktif yang boleh muncul sebagai pilihan baru. Data lama
     * yang merujuk jabatan nonaktif tetap ditampilkan apa adanya.
     *
     * @param  Builder<Jabatan>  $query
     * @return Builder<Jabatan>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

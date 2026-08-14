<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit kerja BPMA. Pohon yang merujuk ke dirinya sendiri: Sekretaris dan Deputi
 * di tingkat atas, divisi di bawahnya.
 */
#[Fillable(['nama', 'parent_id', 'tipe', 'is_active'])]
class Unit extends Model
{
    public const TIPE_SEKRETARIS = 'sekretaris';

    public const TIPE_DEPUTI = 'deputi';

    public const TIPE_DIVISI = 'divisi';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Dokumen yang unit ini menjadi asalnya. Berbeda dari `documents()` pada
     * mekanisme akses — kolom `origin_unit_id` menandai kepemilikan, bukan hak
     * melihat.
     *
     * @return HasMany<Document, $this>
     */
    public function originatedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'origin_unit_id');
    }

    /**
     * Dokumen yang dibagikan ke unit ini lewat mekanisme akses "unit".
     *
     * @return BelongsToMany<Document, $this>
     */
    public function sharedDocuments(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_units');
    }

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * @param  Builder<Unit>  $query
     * @return Builder<Unit>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Unit>  $query
     * @return Builder<Unit>
     */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}

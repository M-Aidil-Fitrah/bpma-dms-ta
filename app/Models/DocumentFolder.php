<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_id', 'parent_id', 'name', 'name_normalized', 'trashed_at', 'trashed_by', 'purge_after', 'trash_token'])]
final class DocumentFolder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'trashed_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<DocumentFolder, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<DocumentFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<DocumentPlacement, $this> */
    public function placements(): HasMany
    {
        return $this->hasMany(DocumentPlacement::class, 'folder_id');
    }

    /** @param Builder<DocumentFolder> $query @return Builder<DocumentFolder> */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('document_folders.owner_id', $user->id);
    }

    /** @param Builder<DocumentFolder> $query @return Builder<DocumentFolder> */
    public function scopeNotTrashed(Builder $query): Builder
    {
        return $query->whereNull('document_folders.trashed_at');
    }
}

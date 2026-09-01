<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_id', 'parent_id', 'name', 'name_normalized', 'trashed_at', 'trashed_by', 'purge_after', 'trash_token'])]
final class DocumentFolder extends Model
{
    use HasFactory;

    /**
     * Batas kedalaman folder (root = level 1). Dipakai baik saat membuat
     * folder (`DocumentWorkspaceService::pastikanKedalaman()`) maupun saat
     * memeriksa warisan akses folder-share — kedua tempat itu wajib memakai
     * angka yang sama, jadi didefinisikan sekali di sini.
     */
    public const KEDALAMAN_MAKSIMAL = 5;

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

    /** @return BelongsToMany<Unit, $this> */
    public function targetUnits(): BelongsToMany
    {
        // Kunci pivot ditulis eksplisit: kolomnya `folder_id`, bukan
        // `document_folder_id` yang ditebak Eloquent dari nama model.
        return $this->belongsToMany(Unit::class, 'document_folder_units', 'folder_id', 'unit_id')
            ->withPivot('role', 'added_by', 'created_at');
    }

    /** @return BelongsToMany<User, $this> */
    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_folder_shares', 'folder_id', 'user_id')
            ->withPivot('role', 'granted_by', 'created_at');
    }

    /**
     * Folder terlihat bila milik sendiri, atau folder ini sendiri (atau salah
     * satu leluhurnya) punya baris akses untuk pengguna ini — berbagi folder
     * induk otomatis mencakup subfolder (perilaku Google Drive).
     */
    public function terlihatOleh(User $user): bool
    {
        if ($this->owner_id === $user->id) {
            return true;
        }

        $folder = $this;

        do {
            if ($folder->dibagikanLangsungKe($user)) {
                return true;
            }

            $folder = $folder->parent;
        } while ($folder !== null);

        return false;
    }

    /**
     * Ada baris akses LANGSUNG pada folder ini untuk pengguna ini atau
     * unitnya — tanpa menaiki rantai `parent`. Publik karena breadcrumb
     * penerima share memakai aturan yang sama persis untuk menentukan di mana
     * jejaknya berhenti; menyalinnya ke controller berarti batas breadcrumb
     * bisa menyimpang dari batas otorisasi tanpa ada yang menyadarinya.
     */
    public function dibagikanLangsungKe(User $user): bool
    {
        return $this->sharedUsers()->where('users.id', $user->id)->exists()
            || ($user->unit_id !== null && $this->targetUnits()->where('units.id', $user->unit_id)->exists());
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

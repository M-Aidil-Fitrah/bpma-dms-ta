<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu setelan aplikasi yang telah diubah dari nilai bawaannya.
 *
 * Baris hanya ada untuk setelan yang benar-benar disunting Superadmin. Ketiadaan
 * baris berarti setelan itu masih memakai bawaan dari `config/dms.php` — bukan
 * berarti nilainya kosong.
 */
#[Fillable(['kunci', 'nilai', 'diubah_oleh'])]
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    /**
     * @return BelongsTo<User, $this>
     */
    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Menulis daftar unit dan orang yang berhak melihat sebuah dokumen (FR-42).
 *
 * Dipakai jalur simpan maupun jalur ubah. Menyalin logikanya ke dua tempat akan
 * membuat salah satunya suatu saat memperlakukan pivot secara berbeda — dan
 * perbedaan pada tabel akses berarti perbedaan pada siapa yang dapat membaca
 * dokumen.
 *
 * Yang tidak boleh keliru di sini: **jejak pemberi akses tidak ditulis ulang.**
 * Baris yang sudah ada dibiarkan apa adanya; hanya yang benar-benar baru yang
 * dipasang, dan yang benar-benar dicabut yang dilepas. Memakai `sync()` biasa
 * akan menimpa `added_by`/`granted_by` seluruh baris dengan penyunting terakhir,
 * sehingga catatan siapa yang mula-mula membuka akses — satu-satunya hal yang
 * dicari saat menelusuri kebocoran dokumen — hilang tanpa jejak.
 */
final class DocumentAccessWriter
{
    public function __construct(private readonly DocumentUnitResolver $resolver) {}

    /**
     * Menyelaraskan kedua daftar akses dengan pilihan terbaru.
     *
     * @param  list<int>  $unitIds
     * @param  list<int>  $penerimaIds
     */
    public function sinkron(
        Document $document,
        array $unitIds,
        array $penerimaIds,
        User $oleh,
    ): void {
        $this->selaraskan(
            $document->targetUnits(),
            $document->targetUnits()->pluck('units.id')->map(intval(...))->all(),
            $this->resolver->untukDisimpan($unitIds),
            'added_by',
            $oleh,
        );

        $this->selaraskan(
            $document->sharedUsers(),
            $document->sharedUsers()->pluck('users.id')->map(intval(...))->all(),
            $this->saringPenggunaAktif($penerimaIds),
            'granted_by',
            $oleh,
        );
    }

    /**
     * @param  BelongsToMany<covariant \Illuminate\Database\Eloquent\Model, Document>  $relasi
     * @param  list<int>  $sekarang
     * @param  list<int>  $diminta
     */
    private function selaraskan(
        BelongsToMany $relasi,
        array $sekarang,
        array $diminta,
        string $kolomJejak,
        User $oleh,
    ): void {
        $dicabut = array_values(array_diff($sekarang, $diminta));
        $ditambah = array_values(array_diff($diminta, $sekarang));

        if ($dicabut !== []) {
            $relasi->detach($dicabut);
        }

        if ($ditambah !== []) {
            $relasi->attach(array_fill_keys($ditambah, [$kolomJejak => $oleh->id]));
        }
    }

    /**
     * Hanya akun aktif yang boleh diberi akses baru.
     *
     * @param  list<int>  $penerimaIds
     * @return list<int>
     */
    private function saringPenggunaAktif(array $penerimaIds): array
    {
        if ($penerimaIds === []) {
            return [];
        }

        return User::query()
            ->active()
            ->whereKey(array_unique($penerimaIds))
            ->pluck('id')
            ->map(intval(...))
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DocumentAccessChanges;
use App\Models\Document;
use App\Models\Unit;
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
    ): DocumentAccessChanges {
        $unitSekarang = $document->targetUnits()->pluck('units.id')->map(intval(...))->all();
        $unitDiminta = $this->resolver->untukDisimpan($unitIds);
        $penggunaSekarang = $document->sharedUsers()->pluck('users.id')->map(intval(...))->all();
        $penggunaDiminta = $this->saringPenggunaAktif($penerimaIds);

        [$unitDitambahkan, $unitDicabut] = $this->selaraskan(
            $document->targetUnits(),
            $unitSekarang,
            $unitDiminta,
            'added_by',
            $oleh,
        );

        [$penggunaDitambahkan, $penggunaDicabut] = $this->selaraskan(
            $document->sharedUsers(),
            $penggunaSekarang,
            $penggunaDiminta,
            'granted_by',
            $oleh,
        );

        return new DocumentAccessChanges(
            unitDitambahkan: $this->targetUnits($unitDitambahkan),
            unitDicabut: $this->targetUnits($unitDicabut),
            penggunaDitambahkan: $this->targetPengguna($penggunaDitambahkan),
            penggunaDicabut: $this->targetPengguna($penggunaDicabut),
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
    ): array {
        $dicabut = array_values(array_diff($sekarang, $diminta));
        $ditambah = array_values(array_diff($diminta, $sekarang));

        if ($dicabut !== []) {
            $relasi->detach($dicabut);
        }

        if ($ditambah !== []) {
            $relasi->attach(array_fill_keys($ditambah, [$kolomJejak => $oleh->id]));
        }

        return [$ditambah, $dicabut];
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

    /** @param list<int> $ids @return list<array{id: int, nama: string}> */
    private function targetUnits(array $ids): array
    {
        return Unit::query()
            ->whereKey($ids)
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn (Unit $unit): array => ['id' => $unit->id, 'nama' => $unit->nama])
            ->all();
    }

    /** @param list<int> $ids @return list<array{id: int, nama: string}> */
    private function targetPengguna(array $ids): array
    {
        return User::query()
            ->whereKey($ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'nama' => $user->name])
            ->all();
    }
}

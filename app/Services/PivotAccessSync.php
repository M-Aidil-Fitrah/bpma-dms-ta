<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DocumentAccessChanges;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mesin diff/sync generik di balik "sinkronkan akses" — dipakai
 * `DocumentAccessWriter` (akses dokumen) dan `FolderAccessWriter` (akses
 * folder). Diekstrak dari `DocumentAccessWriter` karena isinya sama sekali
 * tidak menyentuh apa pun yang spesifik ke `Document` — hanya beroperasi
 * pada relasi `BelongsToMany` unit/pengguna yang diberikan pemanggil.
 *
 * Yang tidak boleh keliru: **jejak pemberi akses tidak ditulis ulang.** Baris
 * yang sudah ada dibiarkan apa adanya; hanya yang benar-benar baru dipasang,
 * yang benar-benar dicabut dilepas. `sync()` biasa akan menimpa
 * `added_by`/`granted_by` seluruh baris dengan penyunting terakhir.
 */
final class PivotAccessSync
{
    public function __construct(private readonly DocumentUnitResolver $resolver) {}

    /**
     * @param  BelongsToMany<Unit, *>  $relasiUnit
     * @param  BelongsToMany<User, *>  $relasiPengguna
     * @param  list<int>  $unitIds
     * @param  list<int>  $penerimaIds
     */
    public function sinkron(
        BelongsToMany $relasiUnit,
        BelongsToMany $relasiPengguna,
        array $unitIds,
        array $penerimaIds,
        User $oleh,
    ): DocumentAccessChanges {
        $unitSekarang = $relasiUnit->pluck($relasiUnit->getRelated()->qualifyColumn('id'))->map(intval(...))->all();
        $unitDiminta = $this->resolver->untukDisimpan($unitIds);
        $penggunaSekarang = $relasiPengguna->pluck($relasiPengguna->getRelated()->qualifyColumn('id'))->map(intval(...))->all();
        $penggunaDiminta = $this->saringPenggunaAktif($penerimaIds);

        [$unitDitambahkan, $unitDicabut] = $this->selaraskan($relasiUnit, $unitSekarang, $unitDiminta, 'added_by', $oleh);
        [$penggunaDitambahkan, $penggunaDicabut] = $this->selaraskan($relasiPengguna, $penggunaSekarang, $penggunaDiminta, 'granted_by', $oleh);

        return new DocumentAccessChanges(
            unitDitambahkan: $this->targetUnits($unitDitambahkan),
            unitDicabut: $this->targetUnits($unitDicabut),
            penggunaDitambahkan: $this->targetPengguna($penggunaDitambahkan),
            penggunaDicabut: $this->targetPengguna($penggunaDicabut),
        );
    }

    /**
     * @param  BelongsToMany<*, *>  $relasi
     * @param  list<int>  $sekarang
     * @param  list<int>  $diminta
     * @return array{list<int>, list<int>}
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

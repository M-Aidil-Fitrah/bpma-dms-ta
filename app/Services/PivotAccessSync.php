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
     * `$unitRoles`/`$penggunaRoles` bersifat opsional (`id => 'viewer'|'editor'`).
     * Kosong keduanya = jalur pivot dokumen: perilaku persis seperti sebelum ada
     * kolom `role` — tidak ada `role` di payload `attach()`, tidak ada
     * `updateExistingPivot`, tidak ada pembacaan `pivot.role`. Bila sebuah map
     * diisi (jalur pivot folder), baris baru menyertakan `role`, dan baris yang
     * bertahan namun role-nya berubah di-`updateExistingPivot()` TANPA menyentuh
     * `added_by`/`granted_by`.
     *
     * @param  BelongsToMany<Unit, *>  $relasiUnit
     * @param  BelongsToMany<User, *>  $relasiPengguna
     * @param  list<int>  $unitIds
     * @param  list<int>  $penerimaIds
     * @param  array<int, string>  $unitRoles
     * @param  array<int, string>  $penggunaRoles
     */
    public function sinkron(
        BelongsToMany $relasiUnit,
        BelongsToMany $relasiPengguna,
        array $unitIds,
        array $penerimaIds,
        User $oleh,
        array $unitRoles = [],
        array $penggunaRoles = [],
    ): DocumentAccessChanges {
        $unitDiminta = $this->resolver->untukDisimpan($unitIds);
        $penggunaDiminta = $this->saringPenggunaAktif($penerimaIds);

        [$unitDitambahkan, $unitDicabut] = $this->selaraskan($relasiUnit, $unitDiminta, $unitRoles, 'added_by', $oleh);
        [$penggunaDitambahkan, $penggunaDicabut] = $this->selaraskan($relasiPengguna, $penggunaDiminta, $penggunaRoles, 'granted_by', $oleh);

        return new DocumentAccessChanges(
            unitDitambahkan: $this->targetUnits($unitDitambahkan),
            unitDicabut: $this->targetUnits($unitDicabut),
            penggunaDitambahkan: $this->targetPengguna($penggunaDitambahkan),
            penggunaDicabut: $this->targetPengguna($penggunaDicabut),
        );
    }

    /**
     * @param  BelongsToMany<*, *>  $relasi
     * @param  list<int>  $idDiminta
     * @param  array<int, string>  $peranDiminta  id => role; kosong = mode tanpa-role (pivot dokumen)
     * @return array{list<int>, list<int>} [ditambah, dicabut] — perubahan role tidak dihitung sebagai grant/revoke
     */
    private function selaraskan(
        BelongsToMany $relasi,
        array $idDiminta,
        array $peranDiminta,
        string $kolomJejak,
        User $oleh,
    ): array {
        $denganRole = $peranDiminta !== [];

        $peranSekarang = $denganRole ? $this->peranSekarang($relasi) : [];
        $idSekarang = $denganRole
            ? array_keys($peranSekarang)
            : $relasi->pluck($relasi->getRelated()->qualifyColumn('id'))->map(intval(...))->all();

        $dicabut = array_values(array_diff($idSekarang, $idDiminta));
        $ditambah = array_values(array_diff($idDiminta, $idSekarang));

        if ($dicabut !== []) {
            $relasi->detach($dicabut);
        }

        if ($ditambah !== []) {
            $relasi->attach(collect($ditambah)->mapWithKeys(fn (int $id): array => [
                $id => $denganRole
                    ? [$kolomJejak => $oleh->id, 'role' => $peranDiminta[$id] ?? 'viewer']
                    : [$kolomJejak => $oleh->id],
            ])->all());
        }

        if ($denganRole) {
            foreach (array_intersect($idSekarang, $idDiminta) as $id) {
                $peranBaru = $peranDiminta[$id] ?? 'viewer';

                if (($peranSekarang[$id] ?? 'viewer') !== $peranBaru) {
                    $relasi->updateExistingPivot($id, ['role' => $peranBaru]);
                }
            }
        }

        return [$ditambah, $dicabut];
    }

    /**
     * State role saat ini per id. Hanya dipanggil di cabang role-aware —
     * pivot dokumen tidak punya kolom `role` dan query-nya akan error.
     *
     * @param  BelongsToMany<*, *>  $relasi
     * @return array<int, string>
     */
    private function peranSekarang(BelongsToMany $relasi): array
    {
        return $relasi->get([$relasi->getRelated()->qualifyColumn('id')])
            ->mapWithKeys(fn ($model): array => [(int) $model->id => $model->pivot->role ?? 'viewer'])
            ->all();
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

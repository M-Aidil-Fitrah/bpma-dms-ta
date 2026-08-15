<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Data\ReferensiEditData;
use App\Data\ReferensiListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\UnitHierarchy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Manajemen pohon unit, dengan penjagaan siklus di Request (FR-28). */
final class UnitController extends Controller
{
    public function __construct(private readonly UnitHierarchy $hierarchy) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Units/Index', [
            'referensi' => $this->daftar($request),
            'filter' => $this->filter($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Units/Create', ['induk' => $this->opsiInduk()]);
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        Unit::create($request->kolomUnit());

        return redirect()->route('admin.units.index')->with('success', 'Unit kerja baru berhasil ditambahkan.');
    }

    public function edit(Unit $unit): Response
    {
        return Inertia::render('Units/Edit', [
            'referensi' => ReferensiEditData::dariUnit($unit),
            'induk' => $this->opsiInduk($unit),
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->kolomUnit());

        return redirect()->route('admin.units.index')->with('success', 'Perubahan unit kerja berhasil disimpan.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->update(['is_active' => false]);

        return redirect()->route('admin.units.index')->with('success', "Unit \"{$unit->nama}\" dinonaktifkan.");
    }

    public function restore(Unit $unit): RedirectResponse
    {
        $unit->update(['is_active' => true]);

        return redirect()->route('admin.units.index')->with('success', "Unit \"{$unit->nama}\" diaktifkan kembali.");
    }

    /** @return LengthAwarePaginator<int, ReferensiListData> */
    private function daftar(Request $request): LengthAwarePaginator
    {
        // Peta parent diambil sekali agar kedalaman tidak diquery per baris.
        $kedalaman = $this->hierarchy->kedalaman(Unit::query()->get(['id', 'parent_id']));

        return Unit::query()
            ->with('parent:id,nama')
            ->withCount(['users', 'originatedDocuments', 'sharedDocuments'])
            ->when($request->string('cari')->toString(), fn ($q, string $kata) => $q->where('nama', 'like', "%{$kata}%"))
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('is_active', $status === 'aktif'))
            ->orderByRaw('parent_id is not null')
            ->orderBy('parent_id')
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Unit $unit): ReferensiListData => ReferensiListData::dariUnit($unit, $kedalaman[$unit->id] ?? 0));
    }

    /**
     * @return list<array{id: int, nama: string, kedalaman: int}>
     */
    private function opsiInduk(?Unit $sedangDiubah = null): array
    {
        $units = Unit::query()->active()->orderBy('nama')->get(['id', 'nama', 'parent_id']);
        $kedalaman = $this->hierarchy->kedalaman($units);

        return $this->hierarchy->kandidatInduk($units, $sedangDiubah)
            ->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'nama' => $unit->nama,
                'kedalaman' => $kedalaman[$unit->id] ?? 0,
            ])
            ->values()
            ->all();
    }

    /** @return array{cari: string|null, status: string|null} */
    private function filter(Request $request): array
    {
        return [
            'cari' => $request->string('cari')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];
    }
}

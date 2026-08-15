<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Data\ReferensiEditData;
use App\Data\ReferensiListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJabatanRequest;
use App\Http\Requests\Admin\UpdateJabatanRequest;
use App\Models\Jabatan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Manajemen jabatan dinamis, termasuk soft-disable (FR-29). */
final class JabatanController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Positions/Index', [
            'referensi' => $this->daftar($request),
            'filter' => $this->filter($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Positions/Create');
    }

    public function store(StoreJabatanRequest $request): RedirectResponse
    {
        Jabatan::create($request->kolomJabatan());

        return redirect()->route('admin.jabatans.index')->with('success', 'Jabatan baru berhasil ditambahkan.');
    }

    public function edit(Jabatan $jabatan): Response
    {
        return Inertia::render('Positions/Edit', [
            'referensi' => ReferensiEditData::dariJabatan($jabatan),
        ]);
    }

    public function update(UpdateJabatanRequest $request, Jabatan $jabatan): RedirectResponse
    {
        $jabatan->update($request->kolomJabatan());

        return redirect()->route('admin.jabatans.index')->with('success', 'Perubahan jabatan berhasil disimpan.');
    }

    /** "Hapus" organisasi selalu berarti nonaktifkan, tidak pernah hard-delete. */
    public function destroy(Jabatan $jabatan): RedirectResponse
    {
        $jabatan->update(['is_active' => false]);

        return redirect()->route('admin.jabatans.index')->with('success', "Jabatan \"{$jabatan->nama}\" dinonaktifkan.");
    }

    public function restore(Jabatan $jabatan): RedirectResponse
    {
        $jabatan->update(['is_active' => true]);

        return redirect()->route('admin.jabatans.index')->with('success', "Jabatan \"{$jabatan->nama}\" diaktifkan kembali.");
    }

    /** @return LengthAwarePaginator<int, ReferensiListData> */
    private function daftar(Request $request): LengthAwarePaginator
    {
        return Jabatan::query()
            ->withCount('users')
            ->when($request->string('cari')->toString(), fn ($q, string $kata) => $q->where('nama', 'like', "%{$kata}%"))
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('is_active', $status === 'aktif'))
            ->orderBy('tingkat_akses')
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString()
            ->through(ReferensiListData::dariJabatan(...));
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

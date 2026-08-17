<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Data\ReferensiEditData;
use App\Data\ReferensiListData;
use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJabatanRequest;
use App\Http\Requests\Admin\UpdateJabatanRequest;
use App\Models\Jabatan;
use App\Services\ActivityLogService;
use App\Services\AuditAttributeChanges;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function store(StoreJabatanRequest $request, ActivityLogService $aktivitas): RedirectResponse
    {
        DB::transaction(function () use ($request, $aktivitas): void {
            $jabatan = Jabatan::create($request->kolomJabatan());

            $aktivitas->record(
                ActivityLogName::Jabatan,
                AuditEvent::Created,
                'Jabatan ditambahkan.',
                $jabatan,
                $request->user(),
            );
        });

        return redirect()->route('admin.jabatans.index')->with('success', 'Jabatan baru berhasil ditambahkan.');
    }

    public function edit(Jabatan $jabatan): Response
    {
        return Inertia::render('Positions/Edit', [
            'referensi' => ReferensiEditData::dariJabatan($jabatan),
        ]);
    }

    public function update(
        UpdateJabatanRequest $request,
        Jabatan $jabatan,
        AuditAttributeChanges $perubahan,
        ActivityLogService $aktivitas,
    ): RedirectResponse {
        $jabatan->fill($request->kolomJabatan());
        $perubahanAtribut = $perubahan->fromDirty($jabatan, [
            'nama' => 'Nama jabatan',
            'tingkat_akses' => 'Tingkat akses',
        ]);

        DB::transaction(function () use ($jabatan, $perubahanAtribut, $request, $aktivitas): void {
            $jabatan->save();

            if ($perubahanAtribut['before'] !== []) {
                $aktivitas->record(
                    ActivityLogName::Jabatan,
                    AuditEvent::Updated,
                    'Jabatan diperbarui.',
                    $jabatan,
                    $request->user(),
                    before: $perubahanAtribut['before'],
                    after: $perubahanAtribut['after'],
                );
            }
        });

        return redirect()->route('admin.jabatans.index')->with('success', 'Perubahan jabatan berhasil disimpan.');
    }

    /** "Hapus" organisasi selalu berarti nonaktifkan, tidak pernah hard-delete. */
    public function destroy(Request $request, Jabatan $jabatan, ActivityLogService $aktivitas): RedirectResponse
    {
        DB::transaction(function () use ($jabatan, $request, $aktivitas): void {
            $jabatan->update(['is_active' => false]);
            $aktivitas->record(ActivityLogName::Jabatan, AuditEvent::Deactivated, 'Jabatan dinonaktifkan.', $jabatan, $request->user());
        });

        return redirect()->route('admin.jabatans.index')->with('success', "Jabatan \"{$jabatan->nama}\" dinonaktifkan.");
    }

    public function restore(Request $request, Jabatan $jabatan, ActivityLogService $aktivitas): RedirectResponse
    {
        DB::transaction(function () use ($jabatan, $request, $aktivitas): void {
            $jabatan->update(['is_active' => true]);
            $aktivitas->record(ActivityLogName::Jabatan, AuditEvent::Restored, 'Jabatan diaktifkan kembali.', $jabatan, $request->user());
        });

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

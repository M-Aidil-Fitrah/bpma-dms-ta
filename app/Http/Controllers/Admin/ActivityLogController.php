<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityLogName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityLogIndexRequest;
use App\Models\User;
use App\Services\ActivityLogQuery;
use App\Support\UnitOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pemantauan aktivitas lintas pengguna — khusus Superadmin (FEAT-15b).
 *
 * Berbeda dari `ActivityLogController` biasa: halaman ini sengaja tidak
 * dibatasi `ActivityLogQuery::visibleTo()`, karena tujuannya memang melihat
 * jejak audit siapa pun. Gerbangnya middleware `superadmin` di
 * `routes/web.php`, sama seperti seluruh modul admin lain.
 */
final class ActivityLogController extends Controller
{
    public function index(ActivityLogIndexRequest $request, ActivityLogQuery $aktivitas): Response
    {
        $pelakuId = $request->integer('pelaku') ?: null;

        return Inertia::render('Admin/ActivityLog/Index', [
            'aktivitas' => $aktivitas->paginateAdmin($request),
            'filter' => $request->filterAktif(),
            'opsi' => [
                'jenis' => array_map(
                    static fn (ActivityLogName $jenis): array => ['value' => $jenis->value, 'label' => $jenis->label()],
                    ActivityLogName::cases(),
                ),
                'unit' => UnitOptions::berlabel(),
                'unit_pohon' => UnitOptions::pohon(),
                // Dikirim terpisah dari `filter.pelaku` (yang cuma berisi id)
                // supaya kontrol pencarian pengguna dapat menampilkan nama
                // yang sedang aktif tanpa menebaknya dari daftar hasil
                // pencarian sisi klien.
                'pelaku_terpilih' => $pelakuId !== null ? $this->pelakuTerpilih($pelakuId) : null,
            ],
        ]);
    }

    /**
     * Pencarian pengguna untuk filter pelaku (FEAT-15b).
     *
     * Sengaja TIDAK memakai ulang `DocumentController::cariPengguna()`:
     * endpoint itu mengecualikan diri sendiri dan akun nonaktif karena
     * dibuat untuk mekanisme berbagi dokumen. Riwayat audit sebaliknya harus
     * tetap bisa dicari meski pelakunya kini nonaktif, dan superadmin boleh
     * menyaring aktivitasnya sendiri.
     *
     * @return list<array{id: int, nama: string, jabatan: string|null, unit: string|null}>
     */
    public function cariPengguna(Request $request): array
    {
        $kata = trim((string) $request->string('cari'));

        if (mb_strlen($kata) < 2) {
            return [];
        }

        return User::query()
            ->where('name', 'like', "%{$kata}%")
            ->with(['jabatan:id,nama', 'unit:id,nama'])
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'jabatan_id', 'unit_id'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'nama' => $u->name,
                'jabatan' => $u->jabatan?->nama,
                'unit' => $u->unit?->nama,
            ])
            ->all();
    }

    /** @return array{id: int, nama: string, jabatan: string|null, unit: string|null}|null */
    private function pelakuTerpilih(int $id): ?array
    {
        $user = User::query()->with(['jabatan:id,nama', 'unit:id,nama'])->find($id, ['id', 'name', 'jabatan_id', 'unit_id']);

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'nama' => $user->name,
            'jabatan' => $user->jabatan?->nama,
            'unit' => $user->unit?->nama,
        ];
    }
}

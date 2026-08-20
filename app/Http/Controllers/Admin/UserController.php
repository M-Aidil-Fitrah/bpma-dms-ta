<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Data\UserEditData;
use App\Data\UserListData;
use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AuditAttributeChanges;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manajemen akun pengguna — khusus Superadmin (FR-25 s.d. FR-27, FR-31).
 *
 * Tidak ada registrasi publik (`PRD.md` §1); ini satu-satunya jalan sebuah
 * akun `pengguna` bisa terbentuk. Gerbangnya middleware `superadmin` di
 * `routes/web.php`, bukan pemeriksaan di sini — menyembunyikan tombol bukan
 * proteksi (FR-43).
 */
final class UserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'opsi' => $this->opsiFilter(),
        ]);
    }

    /**
     * Menyimpan akun baru (FR-25).
     *
     * Selalu berperan `pengguna` — akun `superadmin` satu-satunya di aplikasi
     * ini disediakan lewat `.env` dan perintah artisan (FEAT-02), bukan lewat
     * formulir ini, supaya tidak ada jalan tak sengaja membuat Superadmin
     * kedua.
     */
    public function store(StoreUserRequest $request, ActivityLogService $aktivitas): RedirectResponse
    {
        DB::transaction(function () use ($request, $aktivitas): void {
            $user = User::create([
                ...$request->kolomPengguna(),
                'password' => Hash::make($request->string('password')->toString()),
            ]);

            $user->assignRole(User::ROLE_PENGGUNA);

            $aktivitas->record(ActivityLogName::Pengguna, AuditEvent::Created, 'Pengguna ditambahkan.', $user, $request->user());
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna baru berhasil ditambahkan.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'pengguna' => UserEditData::fromModel($user),
            'opsi' => $this->opsiFilter(),
        ]);
    }

    /**
     * Menyunting profil akun (FR-26). Kata sandi TIDAK disentuh di sini —
     * lihat `resetPassword()`.
     */
    public function update(
        UpdateUserRequest $request,
        User $user,
        AuditAttributeChanges $perubahan,
        ActivityLogService $aktivitas,
    ): RedirectResponse {
        $user->fill($request->kolomPengguna());

        $jabatan = Jabatan::query()
            ->whereKey(array_filter([
                (int) ($user->getRawOriginal('jabatan_id') ?? 0),
                (int) ($user->getDirty()['jabatan_id'] ?? 0),
            ]))
            ->pluck('nama', 'id')
            ->all();
        $unit = Unit::query()
            ->whereKey(array_filter([
                (int) ($user->getRawOriginal('unit_id') ?? 0),
                (int) ($user->getDirty()['unit_id'] ?? 0),
            ]))
            ->pluck('nama', 'id')
            ->all();
        $perubahanAtribut = $perubahan->fromDirty($user, [
            'name' => 'Nama',
            'email' => 'Surel',
            'jabatan_id' => [
                'label' => 'Jabatan',
                'nilai' => static fn (mixed $id): string => $jabatan[(int) $id] ?? '—',
            ],
            'unit_id' => [
                'label' => 'Unit kerja',
                'nilai' => static fn (mixed $id): string => $unit[(int) $id] ?? '—',
            ],
        ]);

        DB::transaction(function () use ($user, $perubahanAtribut, $request, $aktivitas): void {
            $user->save();

            if ($perubahanAtribut['before'] !== []) {
                $aktivitas->record(
                    ActivityLogName::Pengguna,
                    AuditEvent::Updated,
                    'Pengguna diperbarui.',
                    $user,
                    $request->user(),
                    before: $perubahanAtribut['before'],
                    after: $perubahanAtribut['after'],
                );
            }
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Perubahan akun berhasil disimpan.');
    }

    /**
     * Menonaktifkan akun (FR-27). Barisnya TIDAK dihapus — riwayat aktivitas
     * dan dokumen yang pernah diunggahnya tetap utuh, sama seperti dokumen.
     *
     * Superadmin tidak dapat menonaktifkan dirinya sendiri: tanpa pengaman
     * ini, satu klik keliru mengunci seluruh sistem karena tidak ada jalan
     * lain membuat akun Superadmin selain lewat `.env` dan server sungguhan.
     */
    public function destroy(Request $request, User $user, ActivityLogService $aktivitas): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors([
                'user' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ]);
        }

        DB::transaction(function () use ($user, $request, $aktivitas): void {
            $user->update(['is_active' => false]);
            $aktivitas->record(ActivityLogName::Pengguna, AuditEvent::Deactivated, 'Pengguna dinonaktifkan.', $user, $request->user());
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Akun \"{$user->name}\" dinonaktifkan.");
    }

    public function restore(Request $request, User $user, ActivityLogService $aktivitas): RedirectResponse
    {
        DB::transaction(function () use ($user, $request, $aktivitas): void {
            $user->update(['is_active' => true]);
            $aktivitas->record(ActivityLogName::Pengguna, AuditEvent::Restored, 'Pengguna diaktifkan kembali.', $user, $request->user());
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Akun \"{$user->name}\" diaktifkan kembali.");
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user, ActivityLogService $aktivitas): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $aktivitas): void {
            $user->update(['password' => Hash::make($request->string('password')->toString())]);
            // Nilai kata sandi maupun hash-nya tidak boleh menjadi properti log.
            $aktivitas->record(ActivityLogName::Pengguna, AuditEvent::PasswordReset, 'Kata sandi pengguna diatur ulang.', $user, $request->user());
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Kata sandi \"{$user->name}\" berhasil diatur ulang.");
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Users/Index', [
            'pengguna' => $this->daftar($request),
            'filter' => [
                'cari' => $request->string('cari')->toString() ?: null,
                'jabatan' => $request->integer('jabatan') ?: null,
                'unit' => $request->integer('unit') ?: null,
                'status' => $request->string('status')->toString() ?: null,
            ],
            'opsi' => fn (): array => $this->opsiFilter(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, UserListData>
     */
    private function daftar(Request $request): LengthAwarePaginator
    {
        return User::query()
            ->with(['jabatan:id,nama', 'unit:id,nama'])
            ->when(
                $request->string('cari')->toString(),
                fn ($query, string $kata) => $query->where(
                    fn ($q) => $q
                        ->where('name', 'like', "%{$kata}%")
                        ->orWhere('email', 'like', "%{$kata}%"),
                ),
            )
            ->when(
                $request->integer('jabatan'),
                fn ($query, int $id) => $query->where('jabatan_id', $id),
            )
            ->when(
                $request->integer('unit'),
                fn ($query, int $id) => $query->where('unit_id', $id),
            )
            ->when(
                $request->string('status')->toString(),
                fn ($query, string $status) => $query->where('is_active', $status === 'aktif'),
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(UserListData::fromModel(...));
    }

    /**
     * @return array<string, mixed>
     */
    private function opsiFilter(): array
    {
        return [
            // Hanya jabatan dan unit aktif — dipakai bersama formulir tambah
            // pengguna, yang memang tidak boleh menawarkan pilihan usang.
            'jabatan' => Jabatan::query()
                ->active()
                ->orderBy('tingkat_akses')
                ->orderBy('nama')
                ->get(['id', 'nama', 'tingkat_akses']),
            'unit' => Unit::query()->active()->orderBy('nama')->get(['id', 'nama']),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\AuthUserData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            // Sudah final saat sampai di sini — `SetLocale` berjalan lebih
            // dulu di grup middleware `web`. Dibagikan apa adanya (bukan
            // dibaca ulang dari cookie/akun) supaya frontend dan backend
            // selalu sepakat pada bahasa yang sama untuk satu permintaan.
            'locale' => app()->getLocale(),

            'auth' => [
                // Relasi dimuat di sini sekali untuk seluruh aplikasi, bukan di
                // tiap controller. `roles` ikut dimuat karena setiap
                // pemeriksaan hak akses dokumen memanggil `isSuperadmin()` —
                // tanpanya Spatie menembakkan satu query tambahan di tiap
                // permintaan.
                'user' => $user === null
                    ? null
                    : AuthUserData::fromUser($user->loadMissing(['jabatan', 'unit', 'roles'])),
                'password_confirmed_until' => fn (): ?string => $this->passwordConfirmedUntil($request),
            ],

            // Pesan sekali-tampil setelah sebuah aksi. Dibungkus closure supaya
            // hanya dibaca saat props benar-benar dikirim.
            // Kunci di sini persis sama dengan status toast di antarmuka
            // (`Components/ui/Toast.tsx`). Menambah status baru berarti
            // menambahnya di kedua tempat — dan yang tidak terdaftar di sini
            // tidak akan pernah sampai ke layar.
            'flash' => [
                // ID dibuat per respons supaya antarmuka dapat membedakan
                // kunjungan baru dari pemasangan ulang komponen pada kunjungan
                // yang sama. Tanpanya satu flash dapat memunculkan dua toast.
                'id' => (string) Str::uuid(),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }

    private function passwordConfirmedUntil(Request $request): ?string
    {
        $terkonfirmasiPada = (int) $request->session()->get('auth.password_confirmed_at', 0);
        if ($terkonfirmasiPada === 0) {
            return null;
        }

        $berlakuSampai = $terkonfirmasiPada + (int) config('auth.password_timeout');

        return $berlakuSampai > now()->timestamp
            ? now()->setTimestamp($berlakuSampai)->toIso8601String()
            : null;
    }
}

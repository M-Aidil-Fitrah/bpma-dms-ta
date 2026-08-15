<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\AuthUserData;
use Illuminate\Http\Request;
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

            'auth' => [
                // Relasi dimuat di sini sekali untuk seluruh aplikasi, bukan di
                // tiap controller. `roles` ikut dimuat karena setiap
                // pemeriksaan hak akses dokumen memanggil `isSuperadmin()` —
                // tanpanya Spatie menembakkan satu query tambahan di tiap
                // permintaan.
                'user' => $user === null
                    ? null
                    : AuthUserData::fromUser($user->loadMissing(['jabatan', 'unit', 'roles'])),
            ],

            // Pesan sekali-tampil setelah sebuah aksi. Dibungkus closure supaya
            // hanya dibaca saat props benar-benar dikirim.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}

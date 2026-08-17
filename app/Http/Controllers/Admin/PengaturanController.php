<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Data\PengaturanFormData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePengaturanRequest;
use App\Services\PengaturanService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Halaman setelan yang memang diizinkan untuk diubah Superadmin. */
final class PengaturanController extends Controller
{
    public function __construct(private readonly PengaturanService $pengaturan) {}

    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'pengaturan' => PengaturanFormData::dariService($this->pengaturan),
        ]);
    }

    public function update(UpdatePengaturanRequest $request): RedirectResponse
    {
        $nilai = $request->validated();

        $this->pengaturan->simpan('unggah.batas_kb', $nilai['unggah_batas_kb'] ?? null, $request->user()->id);
        $this->pengaturan->simpan('dokumen.per_halaman', $nilai['dokumen_per_halaman'] ?? null, $request->user()->id);
        $this->pengaturan->simpan('dokumen.rentang_evaluasi_awal', $nilai['dokumen_rentang_evaluasi_awal'] ?? null, $request->user()->id);

        return redirect()->route('admin.settings.index')->with('success', 'Pengaturan berhasil disimpan.');
    }
}

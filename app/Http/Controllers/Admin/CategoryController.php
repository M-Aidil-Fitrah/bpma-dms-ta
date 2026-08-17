<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Data\ReferensiEditData;
use App\Data\ReferensiListData;
use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\ActivityLogService;
use App\Services\AuditAttributeChanges;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Manajemen kategori dokumen dinamis dan non-destruktif (FR-14). */
final class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Categories/Index', [
            'referensi' => $this->daftar($request),
            'filter' => $this->filter($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Categories/Create');
    }

    public function store(StoreCategoryRequest $request, ActivityLogService $aktivitas): RedirectResponse
    {
        DB::transaction(function () use ($request, $aktivitas): void {
            $category = Category::create($request->kolomKategori());
            $aktivitas->record(ActivityLogName::Kategori, AuditEvent::Created, 'Kategori ditambahkan.', $category, $request->user());
        });

        return redirect()->route('admin.categories.index')->with('success', 'Kategori baru berhasil ditambahkan.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Categories/Edit', [
            'referensi' => ReferensiEditData::dariKategori($category),
        ]);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        AuditAttributeChanges $perubahan,
        ActivityLogService $aktivitas,
    ): RedirectResponse {
        $category->fill($request->kolomKategori());
        $perubahanAtribut = $perubahan->fromDirty($category, [
            'nama' => 'Nama kategori',
            'deskripsi' => 'Deskripsi',
        ]);

        DB::transaction(function () use ($category, $perubahanAtribut, $request, $aktivitas): void {
            $category->save();

            if ($perubahanAtribut['before'] !== []) {
                $aktivitas->record(
                    ActivityLogName::Kategori,
                    AuditEvent::Updated,
                    'Kategori diperbarui.',
                    $category,
                    $request->user(),
                    before: $perubahanAtribut['before'],
                    after: $perubahanAtribut['after'],
                );
            }
        });

        return redirect()->route('admin.categories.index')->with('success', 'Perubahan kategori berhasil disimpan.');
    }

    public function destroy(Request $request, Category $category, ActivityLogService $aktivitas): RedirectResponse
    {
        DB::transaction(function () use ($category, $request, $aktivitas): void {
            $category->update(['is_active' => false]);
            $aktivitas->record(ActivityLogName::Kategori, AuditEvent::Deactivated, 'Kategori dinonaktifkan.', $category, $request->user());
        });

        return redirect()->route('admin.categories.index')->with('success', "Kategori \"{$category->nama}\" dinonaktifkan.");
    }

    public function restore(Request $request, Category $category, ActivityLogService $aktivitas): RedirectResponse
    {
        DB::transaction(function () use ($category, $request, $aktivitas): void {
            $category->update(['is_active' => true]);
            $aktivitas->record(ActivityLogName::Kategori, AuditEvent::Restored, 'Kategori diaktifkan kembali.', $category, $request->user());
        });

        return redirect()->route('admin.categories.index')->with('success', "Kategori \"{$category->nama}\" diaktifkan kembali.");
    }

    /** @return LengthAwarePaginator<int, ReferensiListData> */
    private function daftar(Request $request): LengthAwarePaginator
    {
        return Category::query()
            ->withCount('documents')
            ->when($request->string('cari')->toString(), fn ($q, string $kata) => $q->where('nama', 'like', "%{$kata}%"))
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('is_active', $status === 'aktif'))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString()
            ->through(ReferensiListData::dariKategori(...));
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

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentEditScope;
use App\Models\Category;
use App\Models\Document;
use App\Models\Unit;

/**
 * Menerjemahkan dirty attributes dokumen menjadi before/after yang layak audit.
 *
 * ID foreign key dan enum internal berguna bagi aplikasi, tetapi tidak cukup
 * menjawab perubahan bagi operator. Kelas ini menulis nama kategori/unit dan
 * label cakupan edit, sambil hanya memasukkan kolom yang memang berubah.
 */
final class DocumentMetadataChanges
{
    /**
     * Dipanggil setelah `fill()` dan sebelum `save()`.
     *
     * @return array{before: array<string, string>, after: array<string, string>}
     */
    public function fromDirty(Document $document): array
    {
        $dirty = $document->getDirty();

        if ($dirty === []) {
            return ['before' => [], 'after' => []];
        }

        $original = $document->getRawOriginal();
        $categoryNames = $this->categoryNames([
            $original['category_id'] ?? null,
            $dirty['category_id'] ?? null,
        ]);
        $unitNames = $this->unitNames([
            $original['origin_unit_id'] ?? null,
            $dirty['origin_unit_id'] ?? null,
        ]);

        $before = [];
        $after = [];

        foreach (array_keys($dirty) as $attribute) {
            [$label, $old, $new] = match ($attribute) {
                'nomor' => ['Nomor dokumen', (string) ($original['nomor'] ?? ''), (string) $dirty['nomor']],
                'judul' => ['Judul', (string) ($original['judul'] ?? ''), (string) $dirty['judul']],
                'deskripsi' => ['Deskripsi', $this->nullableText($original['deskripsi'] ?? null), $this->nullableText($dirty['deskripsi'])],
                'category_id' => ['Kategori', $categoryNames[(int) ($original['category_id'] ?? 0)] ?? '—', $categoryNames[(int) $dirty['category_id']] ?? '—'],
                'origin_unit_id' => ['Unit asal', $unitNames[(int) ($original['origin_unit_id'] ?? 0)] ?? '—', $unitNames[(int) ($dirty['origin_unit_id'] ?? 0)] ?? '—'],
                'tanggal' => ['Tanggal dokumen', (string) ($original['tanggal'] ?? ''), (string) $dirty['tanggal']],
                'masa_berlaku' => ['Masa berlaku', $this->nullableText($original['masa_berlaku'] ?? null), $this->nullableText($dirty['masa_berlaku'])],
                'is_shared_to_all' => ['Bagikan ke semua', $this->booleanLabel($original['is_shared_to_all'] ?? false), $this->booleanLabel($dirty['is_shared_to_all'])],
                'is_private' => ['Hanya saya', $this->booleanLabel($original['is_private'] ?? false), $this->booleanLabel($dirty['is_private'])],
                'min_tingkat_akses' => ['Jenjang jabatan', $this->jenjangLabel($original['min_tingkat_akses'] ?? null), $this->jenjangLabel($dirty['min_tingkat_akses'])],
                'edit_scope' => ['Cakupan ubah', $this->editScopeLabel($original['edit_scope'] ?? null), $this->editScopeLabel($dirty['edit_scope'])],
                default => [null, null, null],
            };

            if ($label !== null) {
                $before[$label] = $old;
                $after[$label] = $new;
            }
        }

        return ['before' => $before, 'after' => $after];
    }

    /** @param list<int|string|null> $ids @return array<int, string> */
    private function categoryNames(array $ids): array
    {
        return Category::query()
            ->whereKey(array_filter(array_map('intval', $ids)))
            ->pluck('nama', 'id')
            ->all();
    }

    /** @param list<int|string|null> $ids @return array<int, string> */
    private function unitNames(array $ids): array
    {
        return Unit::query()
            ->whereKey(array_filter(array_map('intval', $ids)))
            ->pluck('nama', 'id')
            ->all();
    }

    private function nullableText(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : (string) $value;
    }

    private function booleanLabel(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? 'Ya' : 'Tidak';
    }

    private function jenjangLabel(mixed $value): string
    {
        return $value === null || $value === '' ? 'Tidak diatur' : "Tingkat {$value} ke atas";
    }

    private function editScopeLabel(mixed $value): string
    {
        return $value === null ? '—' : DocumentEditScope::from((string) $value)->label();
    }
}

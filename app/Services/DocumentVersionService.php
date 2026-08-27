<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentVersionKind;
use App\Enums\PreviewStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Membuat revisi linear tanpa pernah menimpa snapshot dokumen sebelumnya. */
final class DocumentVersionService
{
    public function __construct(private readonly DocumentAccessWriter $akses) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $berkas
     * @param  list<int>  $unitIds
     * @param  list<int>  $penerimaIds
     */
    public function buatMajor(
        Document $versiTerbaru,
        array $metadata,
        array $berkas,
        array $unitIds,
        array $penerimaIds,
        User $oleh,
        string $catatan,
    ): Document {
        return $this->buatDariTerbaru(
            $versiTerbaru,
            [
                ...$metadata,
                ...$berkas,
                // Artefak ini milik byte versi lama. Berkas baru harus
                // diekstrak dan dikonversi ulang, tidak boleh sesaat pun
                // menampilkan gambar mini atau teks dari pendahulunya.
                'thumbnail_path' => null,
                'preview_path' => null,
                'preview_status' => PreviewStatus::NotApplicable,
                'preview_message' => null,
                'extracted_text' => null,
                'extraction_pages_total' => null,
                'extraction_pages_processed' => null,
                'extraction_estimated_seconds' => null,
                'extraction_message' => null,
                'extraction_started_at' => null,
            ],
            $unitIds,
            $penerimaIds,
            $oleh,
            DocumentVersionKind::Content,
            $catatan,
            major: true,
        );
    }

    /** @param array<string, mixed> $metadata @param list<int> $unitIds @param list<int> $penerimaIds */
    public function buatMinor(
        Document $versiTerbaru,
        array $metadata,
        array $unitIds,
        array $penerimaIds,
        User $oleh,
        string $catatan,
    ): Document {
        return $this->buatDariTerbaru(
            $versiTerbaru,
            $metadata,
            $unitIds,
            $penerimaIds,
            $oleh,
            DocumentVersionKind::Metadata,
            $catatan,
            major: false,
        );
    }

    public function pulihkan(Document $arsip, User $oleh, string $catatan): Document
    {
        return DB::transaction(function () use ($arsip, $oleh, $catatan): Document {
            [$terbaru, $akarId] = $this->kunciVersiTerbaru($arsip);
            $arsip->loadMissing(['targetUnits:id', 'sharedUsers:id']);

            $baru = $this->simpanRevisi(
                sumber: $arsip,
                penerus: $terbaru,
                akarId: $akarId,
                kind: DocumentVersionKind::Restoration,
                catatan: $catatan,
                major: true,
                atribut: [],
                oleh: $oleh,
            );
            $this->salinAksesArsip($arsip, $baru, $oleh);

            return $baru;
        });
    }

    /** @param array<string, mixed> $atribut @param list<int> $unitIds @param list<int> $penerimaIds */
    private function buatDariTerbaru(
        Document $diminta,
        array $atribut,
        array $unitIds,
        array $penerimaIds,
        User $oleh,
        DocumentVersionKind $kind,
        string $catatan,
        bool $major,
    ): Document {
        return DB::transaction(function () use ($diminta, $atribut, $unitIds, $penerimaIds, $oleh, $kind, $catatan, $major): Document {
            [$terbaru, $akarId] = $this->kunciVersiTerbaru($diminta);

            if ($terbaru->id !== $diminta->id || ! $terbaru->is_active) {
                throw ValidationException::withMessages([
                    'version' => 'Dokumen sudah memiliki versi terbaru. Muat ulang halaman sebelum membuat revisi.',
                ]);
            }

            $baru = $this->simpanRevisi(
                sumber: $terbaru,
                penerus: $terbaru,
                akarId: $akarId,
                kind: $kind,
                catatan: $catatan,
                major: $major,
                atribut: $atribut,
                oleh: $oleh,
            );
            $this->akses->sinkron($baru, $unitIds, $penerimaIds, $oleh);

            return $baru;
        });
    }

    /** @return array{0: Document, 1: int} */
    private function kunciVersiTerbaru(Document $dokumen): array
    {
        $akarId = $dokumen->version_root_id ?? $dokumen->id;
        $versi = Document::query()
            ->where('version_root_id', $akarId)
            ->orderBy('version_major')
            ->orderBy('version_minor')
            ->lockForUpdate()
            ->get();

        /** @var Document|null $terbaru */
        $terbaru = $versi->last();
        if ($terbaru === null) {
            throw ValidationException::withMessages(['version' => 'Rantai versi dokumen tidak ditemukan.']);
        }

        return [$terbaru, $akarId];
    }

    /** @param array<string, mixed> $atribut */
    private function simpanRevisi(
        Document $sumber,
        Document $penerus,
        int $akarId,
        DocumentVersionKind $kind,
        string $catatan,
        bool $major,
        array $atribut,
        User $oleh,
    ): Document {
        $berkas = [
            'file_path' => $sumber->file_path,
            'file_name_original' => $sumber->file_name_original,
            'file_mime_type' => $sumber->file_mime_type,
            'file_size' => $sumber->file_size,
            'thumbnail_path' => $sumber->thumbnail_path,
            'preview_path' => $sumber->preview_path,
            'preview_status' => $sumber->preview_status,
            'preview_message' => $sumber->preview_message,
            'extracted_text' => $sumber->extracted_text,
            'extraction_status' => $sumber->extraction_status,
            'extraction_pages_total' => $sumber->extraction_pages_total,
            'extraction_pages_processed' => $sumber->extraction_pages_processed,
            'extraction_estimated_seconds' => $sumber->extraction_estimated_seconds,
            'extraction_message' => $sumber->extraction_message,
            'extraction_started_at' => $sumber->extraction_started_at,
        ];

        $baru = Document::create([
            'nomor' => $sumber->nomor,
            'judul' => $sumber->judul,
            'category_id' => $sumber->category_id,
            'origin_unit_id' => $sumber->origin_unit_id,
            'tanggal' => $sumber->tanggal,
            'masa_berlaku' => $sumber->masa_berlaku,
            'status' => $sumber->status,
            'deskripsi' => $sumber->deskripsi,
            ...$berkas,
            'is_shared_to_all' => $sumber->is_shared_to_all,
            'min_tingkat_akses' => $sumber->min_tingkat_akses,
            'edit_scope' => $sumber->edit_scope,
            ...$atribut,
            'uploaded_by' => $oleh->id,
            'is_active' => true,
            'replaces_document_id' => $penerus->id,
            'version_root_id' => $akarId,
            'version_major' => $major ? $penerus->version_major + 1 : $penerus->version_major,
            'version_minor' => $major ? 0 : $penerus->version_minor + 1,
            'version_kind' => $kind,
            'version_note' => $catatan,
        ]);

        $penerus->update(['is_active' => false]);

        return $baru;
    }

    private function salinAksesArsip(Document $arsip, Document $baru, User $oleh): void
    {
        $unit = $arsip->targetUnits->pluck('id')->map(intval(...))->all();
        $pengguna = $arsip->sharedUsers->pluck('id')->map(intval(...))->all();

        $baru->targetUnits()->attach(array_fill_keys($unit, ['added_by' => $oleh->id]));
        $baru->sharedUsers()->attach(array_fill_keys($pengguna, ['granted_by' => $oleh->id]));
    }
}

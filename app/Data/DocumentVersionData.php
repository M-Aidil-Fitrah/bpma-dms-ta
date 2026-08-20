<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DocumentVersionKind;
use App\Models\Document;
use App\Support\Inisial;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Satu entri ringkas pada panel riwayat versi dokumen. */
#[TypeScript]
final class DocumentVersionData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public DocumentVersionKind $jenis,
        public string $catatan,
        public string $nama_berkas,
        public string $tipe_berkas,
        public bool $dipilih,
        public bool $terbaru,
        public ?string $pembuat,
        public string $inisial_pembuat,
        public string $dibuat_pada,
    ) {}

    public static function fromModel(Document $document, int $selectedId, int $latestId): self
    {
        return new self(
            id: $document->id,
            label: $document->versionLabel(),
            jenis: $document->version_kind,
            catatan: $document->version_note,
            nama_berkas: $document->file_name_original,
            tipe_berkas: $document->file_mime_type,
            dipilih: $document->id === $selectedId,
            terbaru: $document->id === $latestId,
            pembuat: $document->uploader?->name,
            inisial_pembuat: Inisial::dari($document->uploader?->name),
            dibuat_pada: $document->created_at->toIso8601String(),
        );
    }
}

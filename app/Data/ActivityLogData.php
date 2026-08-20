<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\Inisial;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Satu aktivitas siap-tampil, tanpa memuat relasi polimorfik per baris. */
#[TypeScript]
final class ActivityLogData extends Data
{
    public function __construct(
        public int $id,
        public string $log_name,
        public string $event,
        public string $deskripsi,
        public string $pelaku,
        public string $inisial_pelaku,
        public string $subjek,
        public ?int $document_id,
        public string $terjadi_pada,
        public ActivityAttributeChangesData $perubahan,
    ) {}

    public static function fromActivity(Activity $activity, ?string $namaPelaku): self
    {
        /** @var array{label?: string} $subjek */
        $subjek = $activity->getProperty('subjek', []);

        return new self(
            id: $activity->id,
            log_name: (string) $activity->log_name,
            event: (string) $activity->event,
            deskripsi: $activity->description,
            pelaku: $namaPelaku ?: 'Sistem',
            inisial_pelaku: $namaPelaku ? Inisial::dari($namaPelaku) : 'S',
            subjek: $subjek['label'] ?? 'Subjek tidak tersedia',
            document_id: $activity->getProperty('dokumen_id'),
            terjadi_pada: $activity->created_at->toIso8601String(),
            perubahan: ActivityAttributeChangesData::fromChanges($activity->attribute_changes?->all() ?? []),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Models\Document;
use App\Services\ActivityLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class PurgeTrashedDocuments extends Command
{
    protected $signature = 'documents:purge-trash';

    protected $description = 'Menghapus permanen dokumen yang melewati retensi Sampah';

    public function handle(ActivityLogService $activity): int
    {
        $rootIds = Document::query()
            ->whereNotNull('trash_token')
            ->whereNotNull('purge_after')
            ->where('purge_after', '<=', now())
            ->pluck('version_root_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($rootIds as $rootId) {
            $documents = Document::query()
                ->where('version_root_id', $rootId)
                ->whereNotNull('trash_token')
                ->get();

            if ($documents->isEmpty()) {
                continue;
            }

            $latest = $documents->sortByDesc('id')->firstOrFail();
            $activity->record(
                ActivityLogName::Dokumen,
                AuditEvent::DocumentPurged,
                'Dokumen dihapus permanen setelah retensi Sampah berakhir.',
                $latest,
                null,
            );

            $paths = $documents
                ->flatMap(fn (Document $document): array => array_filter([
                    $document->file_path,
                    $document->thumbnail_path,
                    $document->preview_path,
                ]))
                ->unique()
                ->values()
                ->all();
            Storage::disk('local')->delete($paths);
            $ids = $documents->pluck('id');

            // `version_root_id` pada versi pertama menunjuk dirinya sendiri.
            // MariaDB menolak DELETE bila foreign key internal itu masih ada,
            // bahkan saat seluruh rantai adalah target penghapusan yang sama.
            // Lepaskan seluruh pointer internal lebih dulu; jejak audit sudah
            // disimpan sebelum langkah ini dan tidak bergantung pada relasi.
            Document::query()
                ->whereIn('id', $ids)
                ->update(['version_root_id' => null, 'replaces_document_id' => null]);
            Document::query()->whereIn('id', $ids)->delete();
        }

        $this->components->info("{$rootIds->count()} rantai dokumen Sampah diperiksa.");

        return self::SUCCESS;
    }
}

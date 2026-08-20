<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentPlacement;
use App\Models\DocumentRecent;
use App\Models\DocumentStar;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Menyediakan keadaan Ruang Kerja yang langsung bermakna untuk Pimpinan BPMA.
 *
 * Seeder ini hanya bekerja pada set demo persis 220 dokumen. Dengan begitu
 * `db:seed` pada basis data kerja yang sudah memiliki dokumen tidak pernah
 * memindahkan, membintangi, atau membuang dokumen nyata.
 */
final class DocumentWorkspaceSeeder extends Seeder
{
    private const JUMLAH_DOKUMEN_DEMO = 220;

    public function run(): void
    {
        if (Document::query()->count() !== self::JUMLAH_DOKUMEN_DEMO) {
            return;
        }

        $pimpinan = User::query()->where('email', 'budi.santoso@bpma.internal')->firstOrFail();
        $documents = Document::query()->orderBy('id')->skip(5)->take(5)->get();

        if ($documents->count() !== 5) {
            return;
        }

        Document::query()->whereKey($documents->pluck('id'))->update(['uploaded_by' => $pimpinan->id]);
        $documents->each(fn (Document $document) => $document->setAttribute('uploaded_by', $pimpinan->id));

        $strategis = DocumentFolder::query()->updateOrCreate(
            ['owner_id' => $pimpinan->id, 'parent_id' => null, 'name_normalized' => 'dokumen strategis'],
            ['name' => 'Dokumen Strategis'],
        );
        $rapat = DocumentFolder::query()->updateOrCreate(
            ['owner_id' => $pimpinan->id, 'parent_id' => $strategis->id, 'name_normalized' => 'rapat pimpinan'],
            ['name' => 'Rapat Pimpinan'],
        );
        DocumentFolder::query()->updateOrCreate(
            ['owner_id' => $pimpinan->id, 'parent_id' => null, 'name_normalized' => 'arsip sementara'],
            [
                'name' => 'Arsip Sementara',
                'trashed_at' => now()->subDays(3),
                'trashed_by' => $pimpinan->id,
                'purge_after' => now()->addDays(27),
                'trash_token' => (string) Str::uuid(),
            ],
        );

        DocumentPlacement::query()->updateOrCreate(
            ['owner_id' => $pimpinan->id, 'document_id' => $documents[0]->id],
            ['folder_id' => $strategis->id],
        );
        DocumentPlacement::query()->updateOrCreate(
            ['owner_id' => $pimpinan->id, 'document_id' => $documents[1]->id],
            ['folder_id' => $rapat->id],
        );

        foreach ($documents->take(2) as $document) {
            DocumentStar::query()->firstOrCreate(['user_id' => $pimpinan->id, 'document_id' => $document->id]);
            DocumentRecent::query()->updateOrCreate(
                ['user_id' => $pimpinan->id, 'document_id' => $document->id],
                ['last_opened_at' => now()->subMinutes($document->id)],
            );
        }

        $trashed = $documents[4];
        $trashed->update([
            'trashed_at' => now()->subDays(2),
            'trashed_by' => $pimpinan->id,
            'purge_after' => now()->addDays(28),
            'trash_token' => (string) Str::uuid(),
        ]);
    }
}

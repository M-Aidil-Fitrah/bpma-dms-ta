<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Mengunci pembersihan Sampah sebagai operasi terjadwal yang aman.
 *
 * Rantai versi normal selalu dibuang dan dipulihkan sebagai satu kesatuan.
 * Skenario akar aktif + anak ter-trash sengaja diuji juga untuk melindungi
 * data lama atau impor yang pernah menghasilkan keadaan tidak seragam.
 */
final class PurgeTrashedDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_batas_retensi_tepat_sekarang_ikut_dihapus_dan_diaudit_sebagai_sistem(): void
    {
        $now = CarbonImmutable::parse('2026-08-29 16:00:00');
        CarbonImmutable::setTestNow($now);
        $document = $this->trashedDocument('documents/purge-boundary.pdf', $now);
        Storage::disk('local')->put($document->file_path, 'isi uji');

        $this->artisan('documents:purge-trash')->assertSuccessful();

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->file_path);

        $activity = Activity::query()->sole();
        $this->assertSame(ActivityLogName::Dokumen->value, $activity->log_name);
        $this->assertSame(AuditEvent::DocumentPurged->value, $activity->event);
        $this->assertSame($document->id, $activity->subject_id);
        $this->assertNull($activity->causer_id);
        $this->assertSame($document->id, $activity->getProperty('dokumen_id'));
    }

    public function test_rantai_versi_tertrash_yang_lewat_retensi_menghapus_seluruh_turunan_berkasnya(): void
    {
        $purgeAfter = now()->subSecond();
        $root = $this->trashedDocument('documents/purge-root.pdf', $purgeAfter, [
            'thumbnail_path' => 'documents/purge-root-thumb.jpg',
            'preview_path' => 'documents/purge-root-preview.pdf',
        ]);
        $replacement = $this->trashedDocument('documents/purge-replacement.pdf', $purgeAfter, [
            'replaces_document_id' => $root->id,
            'thumbnail_path' => 'documents/purge-replacement-thumb.jpg',
            'preview_path' => 'documents/purge-replacement-preview.pdf',
        ]);
        $this->samakanStatusSampah($purgeAfter, $root, $replacement);

        foreach ([$root, $replacement] as $document) {
            foreach ([$document->file_path, $document->thumbnail_path, $document->preview_path] as $path) {
                Storage::disk('local')->put($path, 'isi uji');
            }
        }

        $this->artisan('documents:purge-trash')->assertSuccessful();

        $this->assertDatabaseMissing('documents', ['id' => $root->id]);
        $this->assertDatabaseMissing('documents', ['id' => $replacement->id]);
        foreach ([$root, $replacement] as $document) {
            Storage::disk('local')->assertMissing($document->file_path);
            Storage::disk('local')->assertMissing($document->thumbnail_path);
            Storage::disk('local')->assertMissing($document->preview_path);
        }

        $this->assertSame(1, Activity::query()->count());
        $this->assertSame($replacement->id, Activity::query()->sole()->subject_id);
    }

    public function test_dokumen_yang_retensinya_belum_habis_tetap_utuh_tanpa_audit_purge(): void
    {
        $document = $this->trashedDocument('documents/purge-future.pdf', now()->addSecond());
        Storage::disk('local')->put($document->file_path, 'isi uji');

        $this->artisan('documents:purge-trash')->assertSuccessful();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame(0, Activity::query()->where('event', AuditEvent::DocumentPurged->value)->count());
    }

    public function test_rantai_yang_jatuh_tempo_tidak_menghapus_rantai_lain_yang_masih_dalam_retensi(): void
    {
        $due = $this->trashedDocument('documents/purge-due.pdf', now()->subSecond());
        $future = $this->trashedDocument('documents/purge-future.pdf', now()->addSecond());
        Storage::disk('local')->put($due->file_path, 'isi jatuh tempo');
        Storage::disk('local')->put($future->file_path, 'isi belum jatuh tempo');

        $this->artisan('documents:purge-trash')->assertSuccessful();

        $this->assertDatabaseMissing('documents', ['id' => $due->id]);
        Storage::disk('local')->assertMissing($due->file_path);
        $this->assertDatabaseHas('documents', ['id' => $future->id]);
        Storage::disk('local')->assertExists($future->file_path);
    }

    public function test_versi_tertrash_dari_rantai_lama_tidak_menghapus_akar_yang_masih_aktif(): void
    {
        $root = Document::factory()->create(['file_path' => 'documents/purge-active-root.pdf']);
        $child = $this->trashedDocument('documents/purge-orphaned-child.pdf', now()->subSecond(), [
            'replaces_document_id' => $root->id,
        ]);
        Storage::disk('local')->put($root->file_path, 'akar aktif');
        Storage::disk('local')->put($child->file_path, 'anak sampah');

        $this->artisan('documents:purge-trash')->assertSuccessful();

        $this->assertDatabaseHas('documents', ['id' => $root->id]);
        Storage::disk('local')->assertExists($root->file_path);
        $this->assertDatabaseMissing('documents', ['id' => $child->id]);
        Storage::disk('local')->assertMissing($child->file_path);
    }

    /** @param array<string, mixed> $attributes */
    private function trashedDocument(string $path, \DateTimeInterface $purgeAfter, array $attributes = []): Document
    {
        return Document::factory()->create([
            'file_path' => $path,
            'trashed_at' => now()->subDays(30),
            'purge_after' => $purgeAfter,
            'trash_token' => (string) str()->uuid(),
            ...$attributes,
        ]);
    }

    private function samakanStatusSampah(\DateTimeInterface $purgeAfter, Document ...$documents): void
    {
        $token = (string) str()->uuid();

        foreach ($documents as $document) {
            $document->update([
                'trashed_at' => now()->subDays(30),
                'purge_after' => $purgeAfter,
                'trash_token' => $token,
            ]);
        }
    }
}

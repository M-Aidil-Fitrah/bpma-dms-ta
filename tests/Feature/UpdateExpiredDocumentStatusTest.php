<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/** FR-53: hanya kandidat sah berubah, dan pengulangan tidak menduplikasi log. */
final class UpdateExpiredDocumentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_perintah_mengadaluarsakan_hanya_dokumen_yang_masa_berlakunya_lewat_dan_mencatat_sistem(): void
    {
        $lewat = Document::factory()->create([
            'status' => DocumentStatus::Berlaku,
            'masa_berlaku' => today()->subDay(),
        ]);
        $hariIni = Document::factory()->create([
            'status' => DocumentStatus::Berlaku,
            'masa_berlaku' => today(),
        ]);
        $masaDepan = Document::factory()->create([
            'status' => DocumentStatus::Berlaku,
            'masa_berlaku' => today()->addDay(),
        ]);
        $tanpaBatas = Document::factory()->create([
            'status' => DocumentStatus::Berlaku,
            'masa_berlaku' => null,
        ]);
        $sudahKadaluarsa = Document::factory()->kadaluarsa()->create();

        $this->artisan('documents:update-expired-status')
            ->expectsOutput('Dokumen diubah: 1.')
            ->assertExitCode(0);

        $this->assertSame(DocumentStatus::Kadaluarsa, $lewat->fresh()->status);
        $this->assertSame(DocumentStatus::Berlaku, $hariIni->fresh()->status);
        $this->assertSame(DocumentStatus::Berlaku, $masaDepan->fresh()->status);
        $this->assertSame(DocumentStatus::Berlaku, $tanpaBatas->fresh()->status);
        $this->assertSame(DocumentStatus::Kadaluarsa, $sudahKadaluarsa->fresh()->status);
        $this->assertSame(1, Document::query()
            ->where('version_root_id', $lewat->version_root_id)
            ->count(), 'Kadaluarsa otomatis tidak boleh membuat minor version.');

        $activity = Activity::query()->sole();
        $this->assertSame(ActivityLogName::Dokumen->value, $activity->log_name);
        $this->assertSame(AuditEvent::DocumentStatusChanged->value, $activity->event);
        $this->assertNull($activity->causer_id);
        $this->assertSame('otomatis', $activity->getProperty('trigger'));
        $this->assertSame('Berlaku', $activity->attribute_changes->get('old')['Status']);
        $this->assertSame('Kadaluarsa', $activity->attribute_changes->get('attributes')['Status']);
    }

    public function test_perintah_idempoten_dan_memproses_kandidat_lebih_dari_satu_potongan(): void
    {
        Document::factory()->count(101)->create([
            'status' => DocumentStatus::Berlaku,
            'masa_berlaku' => today()->subDay(),
        ]);

        $this->artisan('documents:update-expired-status')
            ->expectsOutput('Dokumen diubah: 101.')
            ->assertExitCode(0);
        $this->artisan('documents:update-expired-status')
            ->expectsOutput('Dokumen diubah: 0.')
            ->assertExitCode(0);

        $this->assertSame(101, Document::where('status', DocumentStatus::Kadaluarsa)->count());
        $this->assertSame(101, Document::count(), 'Scheduler hanya memperbarui status, bukan menambah snapshot.');
        $this->assertSame(101, Activity::count());
    }
}

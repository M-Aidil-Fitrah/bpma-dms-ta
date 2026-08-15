<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Models\Document;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/** Fondasi audit FEAT-15: bentuk setiap baris tidak boleh bergantung request. */
final class ActivityLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mencatat_subjek_pelaku_konteks_dan_before_after_secara_eksplisit(): void
    {
        $pelaku = User::factory()->create(['name' => 'Pelaku Audit']);
        $document = Document::factory()->create(['judul' => 'Pedoman Audit Internal']);

        app(ActivityLogService::class)->record(
            ActivityLogName::Dokumen,
            AuditEvent::DocumentUpdated,
            'Informasi dokumen diperbarui.',
            $document,
            $pelaku,
            ['sumber' => 'formulir_ubah'],
            ['judul' => 'Pedoman Lama'],
            ['judul' => 'Pedoman Audit Internal'],
        );

        $activity = Activity::query()->sole();

        $this->assertSame(ActivityLogName::Dokumen->value, $activity->log_name);
        $this->assertSame(AuditEvent::DocumentUpdated->value, $activity->event);
        $this->assertSame($document->id, $activity->subject_id);
        $this->assertSame(Document::class, $activity->subject_type);
        $this->assertSame($pelaku->id, $activity->causer_id);
        $this->assertSame(User::class, $activity->causer_type);
        $this->assertSame('Pedoman Audit Internal', $activity->getProperty('subjek.label'));
        $this->assertSame($document->id, $activity->getProperty('dokumen_id'));
        $this->assertSame('formulir_ubah', $activity->getProperty('sumber'));
        $this->assertSame('Pedoman Lama', $activity->attribute_changes->get('old')['judul']);
        $this->assertSame('Pedoman Audit Internal', $activity->attribute_changes->get('attributes')['judul']);
    }

    public function test_mencatat_aksi_sistem_secara_anonim_meski_ada_pengguna_terautentikasi(): void
    {
        $pelakuWeb = User::factory()->create();
        $document = Document::factory()->create();
        $this->actingAs($pelakuWeb);

        app(ActivityLogService::class)->record(
            ActivityLogName::Dokumen,
            AuditEvent::DocumentStatusChanged,
            'Status dokumen diperbarui otomatis.',
            $document,
            null,
            ['trigger' => 'otomatis'],
        );

        $activity = Activity::query()->sole();

        $this->assertNull($activity->causer_id);
        $this->assertNull($activity->causer_type);
        $this->assertSame('otomatis', $activity->getProperty('trigger'));
    }
}

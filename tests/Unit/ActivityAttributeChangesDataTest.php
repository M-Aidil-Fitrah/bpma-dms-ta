<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\ActivityAttributeChangesData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActivityAttributeChangesDataTest extends TestCase
{
    #[Test]
    public function menormalisasi_nilai_audit_agar_aman_untuk_kontrak_antarmuka(): void
    {
        $changes = ActivityAttributeChangesData::fromChanges([
            'old' => ['Status' => false, 'Tanggal' => null, 'Jumlah' => 4],
            'attributes' => ['Status' => true, 'Metadata' => ['sumber' => 'uji']],
        ]);

        $this->assertSame([
            'Status' => 'Tidak',
            'Tanggal' => '—',
            'Jumlah' => '4',
        ], $changes->lama);
        $this->assertSame([
            'Status' => 'Ya',
            'Metadata' => '{"sumber":"uji"}',
        ], $changes->baru);
    }

    #[Test]
    public function menerima_perubahan_kosong_tanpa_membuat_struktur_ambigu(): void
    {
        $changes = ActivityAttributeChangesData::fromChanges([]);

        $this->assertSame([], $changes->lama);
        $this->assertSame([], $changes->baru);
    }
}

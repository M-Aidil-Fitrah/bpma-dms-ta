<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Membuktikan skenario demo akses pada data seed sungguhan (FEAT-17).
 *
 * Tes akses berbasis factory tetap penting untuk menutup setiap cabang policy.
 * Tes ini melindungi kontrak yang berbeda: empat akun dan satu dokumen yang
 * akan dipakai saat demo harus terus menunjuk unit, jabatan, dan penerima yang
 * tepat—serta rute tidak boleh menjadi jalan pintas melewati scope `visibleTo`.
 */
final class DemoAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_skenario_demo_tiga_jalur_mengizinkan_tiga_akun_dan_menolak_kontrol_negatif(): void
    {
        config()->set('dms.superadmin.email', 'superadmin@bpma.internal');
        config()->set('dms.superadmin.password', 'kata-sandi-uji');

        $this->seed(DatabaseSeeder::class);

        $document = Document::query()
            ->with(['targetUnits:id,nama', 'sharedUsers:id,email'])
            ->where('judul', 'Laporan Evaluasi Proyek X')
            ->firstOrFail();

        $this->assertSame(2, $document->min_tingkat_akses);
        $this->assertSame(
            ['Divisi Manajemen Sistem Teknologi Informasi'],
            $document->targetUnits->pluck('nama')->all(),
        );
        $this->assertSame(
            ['maya.puspita@bpma.internal'],
            $document->sharedUsers->pluck('email')->all(),
        );

        foreach ([
            'fitri.handayani@bpma.internal' => 'unit',
            'hasan.basri@bpma.internal' => 'jenjang jabatan',
            'maya.puspita@bpma.internal' => 'orang tertentu',
        ] as $email => $mekanisme) {
            $user = User::firstWhere('email', $email);

            $this->assertNotNull($user);
            $this->assertTrue(
                Document::query()->visibleTo($user)->whereKey($document)->exists(),
                "Akun {$email} seharusnya mendapat akses lewat {$mekanisme}.",
            );

            $this->actingAs($user)
                ->get(route('documents.show', $document))
                ->assertOk();
        }

        $kontrolNegatif = User::firstWhere('email', 'rizki.ananda@bpma.internal');

        $this->assertNotNull($kontrolNegatif);
        $this->assertFalse(Document::query()->visibleTo($kontrolNegatif)->whereKey($document)->exists());

        $this->actingAs($kontrolNegatif)
            ->get(route('documents.show', $document))
            ->assertForbidden();

        $this->actingAs($kontrolNegatif)
            ->get(route('documents.file', $document))
            ->assertForbidden();

        $this->actingAs($kontrolNegatif)
            ->get(route('documents.preview', $document))
            ->assertForbidden();
    }
}

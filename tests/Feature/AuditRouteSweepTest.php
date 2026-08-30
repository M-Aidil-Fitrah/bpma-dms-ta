<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sapuan menyeluruh setiap rute GET terhadap beberapa persona akun.
 *
 * Tujuannya bukan menggantikan tes per-fitur, melainkan menjaring dua kelas
 * kegagalan yang mudah lolos: (1) halaman yang melempar 500 untuk peran
 * tertentu karena data relasi yang tidak diduga, dan (2) rute dokumen yang
 * memberi 200 padahal scope `visibleTo()` menyatakan pengguna tidak berhak —
 * yaitu controller menjadi jalan pintas melewati otorisasi terpusat.
 */
final class AuditRouteSweepTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, User> */
    private array $personas = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('dms.superadmin.email', 'superadmin@bpma.internal');
        config()->set('dms.superadmin.password', 'kata-sandi-uji');

        $this->seed(DatabaseSeeder::class);

        foreach ([
            'superadmin' => 'superadmin@bpma.internal',
            'pimpinan' => 'budi.santoso@bpma.internal',
            'deputi' => 'rina.kartika@bpma.internal',
            'kepala_divisi' => 'yusuf.maulana@bpma.internal',
            'anggota_unit' => 'fitri.handayani@bpma.internal',
            'anggota_share' => 'maya.puspita@bpma.internal',
            'anggota_kontrol' => 'rizki.ananda@bpma.internal',
        ] as $key => $email) {
            $this->personas[$key] = User::where('email', $email)->sole();
        }
    }

    public function test_setiap_rute_get_umum_tidak_pernah_500_untuk_persona_mana_pun(): void
    {
        $category = Category::query()->first();
        $jabatan = Jabatan::query()->first();
        $unit = Unit::query()->first();
        $someUser = User::query()->where('email', 'agus.salim@bpma.internal')->sole();
        $folder = DocumentFolder::query()->first();
        $document = Document::query()->where('is_shared_to_all', true)->firstOrFail();

        $routes = [
            ['GET', '/dashboard'],
            ['GET', '/activity-log'],
            ['GET', '/documents'],
            ['GET', '/documents?status=berlaku&sort=judul'],
            ['GET', '/documents?evaluasi=30'],
            ['GET', '/documents/mine'],
            ['GET', '/documents/starred'],
            ['GET', '/documents/recent'],
            ['GET', '/trash'],
            ['GET', '/documents/create'],
            ['GET', '/documents/cari-pengguna?q=budi'],
            ['GET', "/documents/{$document->id}"],
            ['GET', "/documents/{$document->id}/edit"],
            ['GET', "/documents/{$document->id}/file"],
            ['GET', "/documents/{$document->id}/preview"],
            ['GET', "/documents/{$document->id}/thumbnail"],
            ['GET', '/profile'],
            ['GET', '/admin/activity-log'],
            ['GET', '/admin/activity-log/cari-pengguna?q=budi'],
            ['GET', '/admin/users'],
            ['GET', '/admin/users?status=nonaktif&q=a'],
            ['GET', '/admin/users/create'],
            ['GET', "/admin/users/{$someUser->id}/edit"],
            ['GET', '/admin/jabatans'],
            ['GET', '/admin/jabatans/create'],
            ['GET', "/admin/jabatans/{$jabatan->id}/edit"],
            ['GET', '/admin/units'],
            ['GET', '/admin/units/create'],
            ['GET', "/admin/units/{$unit->id}/edit"],
            ['GET', '/admin/categories'],
            ['GET', '/admin/categories/create'],
            ['GET', "/admin/categories/{$category->id}/edit"],
            ['GET', '/admin/settings'],
        ];

        if ($folder !== null) {
            $routes[] = ['GET', "/folders/{$folder->id}"];
        }

        $failures = [];

        foreach ($this->personas as $personaName => $user) {
            foreach ($routes as [$method, $uri]) {
                $response = $this->actingAs($user)->call($method, $uri);
                $status = $response->getStatusCode();

                if ($status >= 500) {
                    $failures[] = "{$personaName} {$method} {$uri} -> {$status}";
                }
            }
        }

        $this->assertSame([], $failures, "Rute berikut melempar 5xx:\n".implode("\n", $failures));
    }

    public function test_admin_ditolak_untuk_semua_persona_non_superadmin(): void
    {
        $adminRoutes = [
            '/admin/activity-log',
            '/admin/users',
            '/admin/users/create',
            '/admin/jabatans',
            '/admin/units',
            '/admin/categories',
            '/admin/settings',
        ];

        foreach ($this->personas as $personaName => $user) {
            if ($personaName === 'superadmin') {
                continue;
            }

            foreach ($adminRoutes as $uri) {
                $status = $this->actingAs($user)->get($uri)->getStatusCode();

                $this->assertContains(
                    $status,
                    [403, 404],
                    "{$personaName} seharusnya tidak boleh membuka {$uri}, dapat {$status}.",
                );
            }
        }
    }

    public function test_akses_dokumen_lewat_rute_konsisten_dengan_scope_visible_to(): void
    {
        // Ambil satu dokumen per pola akses yang berbeda, termasuk kontrol
        // negatif yang hanya terlihat pengunggahnya.
        $samples = Document::query()
            ->with(['targetUnits:id', 'sharedUsers:id'])
            ->where('is_active', true)
            ->whereNull('trashed_at')
            ->get()
            ->groupBy(fn (Document $d): string => match (true) {
                $d->is_private => 'private',
                $d->is_shared_to_all => 'all',
                $d->min_tingkat_akses !== null => 'jenjang',
                $d->targetUnits->isNotEmpty() => 'unit',
                $d->sharedUsers->isNotEmpty() => 'orang',
                default => 'pengunggah',
            })
            ->map(fn ($group) => $group->first())
            ->values();

        $this->assertGreaterThanOrEqual(5, $samples->count(), 'Data seed tidak memuat cukup ragam pola akses.');

        $mismatches = [];

        foreach ($this->personas as $personaName => $user) {
            foreach ($samples as $document) {
                $expectedVisible = Document::query()->visibleTo($user)->whereKey($document->getKey())->exists();

                foreach (['show' => '', 'file' => '/file', 'preview' => '/preview'] as $label => $suffix) {
                    $status = $this->actingAs($user)
                        ->get("/documents/{$document->id}{$suffix}")
                        ->getStatusCode();

                    $ok = $expectedVisible
                        ? in_array($status, [200, 302], true)
                        : $status === 403;

                    if (! $ok) {
                        $mismatches[] = sprintf(
                            '%s %s doc#%d (visibleTo=%s) -> %d',
                            $personaName,
                            $label,
                            $document->id,
                            $expectedVisible ? 'true' : 'false',
                            $status,
                        );
                    }
                }
            }
        }

        $this->assertSame([], $mismatches, "Rute dokumen menyimpang dari scope visibleTo():\n".implode("\n", $mismatches));
    }

    public function test_kontrol_negatif_ditolak_pada_dokumen_demo(): void
    {
        $document = Document::query()->where('judul', 'Laporan Evaluasi Proyek X')->firstOrFail();
        $kontrol = $this->personas['anggota_kontrol'];

        foreach (['', '/file', '/preview', '/thumbnail', '/edit'] as $suffix) {
            $this->actingAs($kontrol)
                ->get("/documents/{$document->id}{$suffix}")
                ->assertForbidden();
        }
    }
}

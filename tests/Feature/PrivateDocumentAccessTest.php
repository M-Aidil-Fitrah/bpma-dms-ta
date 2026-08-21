<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class PrivateDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $pengunggah;

    private User $pimpinan;

    private User $superadmin;

    private Document $dokumen;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('superadmin');
        Role::findOrCreate('pengguna');
        $unit = Unit::factory()->create();
        $jabatanPimpinan = Jabatan::factory()->create(['tingkat_akses' => 1]);
        $jabatanAnggota = Jabatan::factory()->create(['tingkat_akses' => 4]);
        $this->pengunggah = User::factory()->create(['unit_id' => $unit->id, 'jabatan_id' => $jabatanAnggota->id]);
        $this->pengunggah->assignRole('pengguna');
        $this->pimpinan = User::factory()->create(['unit_id' => $unit->id, 'jabatan_id' => $jabatanPimpinan->id]);
        $this->pimpinan->assignRole('pengguna');
        $this->superadmin = User::factory()->create(['unit_id' => null, 'jabatan_id' => null]);
        $this->superadmin->assignRole('superadmin');

        $this->dokumen = Document::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'origin_unit_id' => $unit->id,
            'uploaded_by' => $this->pengunggah->id,
            'is_private' => true,
            'is_shared_to_all' => true,
        ]);
    }

    public function test_hanya_pengunggah_dan_superadmin_dapat_melihat_dokumen_pribadi(): void
    {
        $this->actingAs($this->pengunggah)->get(route('documents.show', $this->dokumen))->assertOk();
        $this->actingAs($this->superadmin)->get(route('documents.show', $this->dokumen))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('documents.show', $this->dokumen))->assertForbidden();
    }

    public function test_jabatan_tertinggi_tidak_melihat_dokumen_pribadi_di_daftar(): void
    {
        $this->actingAs($this->pimpinan)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertDontSee($this->dokumen->judul);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentEditScope;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentPlacement;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FolderSharedDocumentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function dokumenDiFolder(User $pemilik, DocumentFolder $folder, array $atribut = []): Document
    {
        $dokumen = Document::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'uploaded_by' => $pemilik->id,
            ...$atribut,
        ]);
        DocumentPlacement::create([
            'owner_id' => $pemilik->id,
            'document_id' => $dokumen->id,
            'folder_id' => $folder->id,
        ]);

        return $dokumen;
    }

    public function test_dokumen_di_folder_yang_dibagikan_muncul_bagi_penerima(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $folder = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Arsip', 'name_normalized' => 'arsip']);
        $folder->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $folder);

        $this->assertTrue(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
        $this->assertSame('Folder: Arsip', $dokumen->fresh(['targetUnits', 'sharedUsers'])->alasanTerlihat($penerima));
    }

    public function test_dokumen_privat_di_folder_yang_dibagikan_tetap_tersembunyi(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $folder = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Arsip', 'name_normalized' => 'arsip']);
        $folder->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $folder, ['is_private' => true]);

        $this->assertFalse(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
    }

    public function test_dokumen_di_subfolder_folder_yang_dibagikan_tetap_muncul(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $induk = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Induk', 'name_normalized' => 'induk']);
        $anak = DocumentFolder::create(['owner_id' => $pemilik->id, 'parent_id' => $induk->id, 'name' => 'Anak', 'name_normalized' => 'anak']);
        $induk->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $anak);

        $this->assertTrue(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
    }

    public function test_dokumen_di_folder_saudara_yang_tidak_dibagikan_tetap_tersembunyi(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $induk = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Induk', 'name_normalized' => 'induk']);
        $dibagikan = DocumentFolder::create(['owner_id' => $pemilik->id, 'parent_id' => $induk->id, 'name' => 'Dibagikan', 'name_normalized' => 'dibagikan']);
        $saudara = DocumentFolder::create(['owner_id' => $pemilik->id, 'parent_id' => $induk->id, 'name' => 'Saudara', 'name_normalized' => 'saudara']);
        $dibagikan->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $saudara);

        $this->assertFalse(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
    }

    /**
     * Penjaga arah self-join. Warisan akses folder hanya menaiki rantai
     * leluhur; membagikan SUBFOLDER tidak boleh ikut membuka dokumen yang
     * ditaruh di folder INDUKNYA. Kalau join Mekanisme 5 tertukar arah
     * (`f{i}.parent_id = f{i-1}.id`), tes inilah yang menangkapnya.
     */
    public function test_membagikan_subfolder_tidak_membuka_dokumen_di_folder_induk(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $induk = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Induk', 'name_normalized' => 'induk']);
        $anak = DocumentFolder::create(['owner_id' => $pemilik->id, 'parent_id' => $induk->id, 'name' => 'Anak', 'name_normalized' => 'anak']);
        $anak->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $induk);

        $this->assertFalse(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
    }

    /**
     * Rantai leluhur sepanjang `KEDALAMAN_MAKSIMAL` harus tercakup penuh:
     * dokumen di folder terdalam tetap terlihat ketika folder AKAR dibagikan.
     */
    public function test_dokumen_di_kedalaman_maksimal_terlihat_lewat_folder_akar(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();

        $folder = null;
        $rantai = [];
        for ($level = 1; $level <= DocumentFolder::KEDALAMAN_MAKSIMAL; $level++) {
            $folder = DocumentFolder::create([
                'owner_id' => $pemilik->id,
                'parent_id' => $folder?->id,
                'name' => "Level {$level}",
                'name_normalized' => "level{$level}",
            ]);
            $rantai[] = $folder;
        }

        $rantai[0]->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $rantai[DocumentFolder::KEDALAMAN_MAKSIMAL - 1]);

        $this->assertTrue(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
    }

    /**
     * Folder yang dibuang ke Sampah berhenti membagi aksesnya: pemilik yang
     * membuang folder wajar mengira akses lewat folder itu ikut berakhir.
     */
    public function test_folder_yang_dibuang_ke_sampah_berhenti_membagikan_dokumennya(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $folder = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Arsip', 'name_normalized' => 'arsip']);
        $folder->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $folder);

        $this->assertTrue(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());

        app(DocumentWorkspaceService::class)->trashFolder($folder, $pemilik);

        $this->assertFalse(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
    }

    /**
     * Varian lewat cascade: yang dibuang adalah LELUHUR folder tempat dokumen
     * ditaruh. `trashFolder()` menstempel `trashed_at` ke seluruh turunan,
     * jadi pemeriksaan pada folder dokumen itu sendiri sudah menangkapnya.
     */
    public function test_membuang_folder_induk_menghentikan_akses_dokumen_di_subfoldernya(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $induk = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Induk', 'name_normalized' => 'induk']);
        $anak = DocumentFolder::create(['owner_id' => $pemilik->id, 'parent_id' => $induk->id, 'name' => 'Anak', 'name_normalized' => 'anak']);
        $induk->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $anak);

        $this->assertTrue(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());

        app(DocumentWorkspaceService::class)->trashFolder($induk, $pemilik);

        $this->assertFalse(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
    }

    /**
     * Rantai campuran hidup/Sampah. `trashFolder()` memakai `notTrashed()`
     * pada UPDATE-nya, jadi anak yang sudah lebih dulu di Sampah TIDAK ikut
     * distempel token induknya — ia menyimpan tokennya sendiri dan bisa
     * dipulihkan sendirian, meninggalkan induk pemberi akses tetap di Sampah.
     * Memeriksa `trashed_at` pada folder dokumen saja akan meloloskan dokumen
     * ini; seluruh rantai sampai level pemberi akses harus ikut diperiksa.
     */
    public function test_memulihkan_anak_saja_tidak_menghidupkan_akses_dari_induk_yang_masih_di_sampah(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $induk = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Induk', 'name_normalized' => 'induk']);
        $anak = DocumentFolder::create(['owner_id' => $pemilik->id, 'parent_id' => $induk->id, 'name' => 'Anak', 'name_normalized' => 'anak']);
        $induk->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $anak);

        $workspace = app(DocumentWorkspaceService::class);
        $workspace->trashFolder($anak, $pemilik);
        $workspace->trashFolder($induk, $pemilik);
        $workspace->restoreFolder($anak->fresh(), $pemilik);

        // Anak hidup kembali, tetapi induk — satu-satunya pemberi akses —
        // masih di Sampah, jadi dokumen tidak boleh terlihat.
        $this->assertNull($anak->fresh()->trashed_at);
        $this->assertNotNull($induk->fresh()->trashed_at);
        $this->assertFalse(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
    }

    /** Memulihkan folder dari Sampah mengembalikan akses yang tadi berhenti. */
    public function test_memulihkan_folder_dari_sampah_mengembalikan_akses(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $folder = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Arsip', 'name_normalized' => 'arsip']);
        $folder->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $folder);

        $workspace = app(DocumentWorkspaceService::class);
        $workspace->trashFolder($folder, $pemilik);
        $this->assertFalse(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());

        $workspace->restoreFolder($folder->fresh(), $pemilik);

        $this->assertTrue(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
    }

    /**
     * Janji dialog Bagikan adalah "dapat melihat dan mengunduh". Membagikan
     * folder karena itu tidak boleh diam-diam ikut memberi hak ubah pada
     * dokumen ber-`edit_scope` "Sama seperti akses" yang kebetulan ada di
     * dalamnya — keputusan berbagi folder dibuat pada folder, bukan pada
     * dokumen itu, dan cakupannya pun berbeda.
     */
    public function test_folder_yang_dibagikan_tidak_memberi_hak_ubah_pada_dokumen_sama_seperti_akses(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $folder = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Arsip', 'name_normalized' => 'arsip']);
        $folder->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $folder, ['edit_scope' => DocumentEditScope::MatchVisibility]);

        $this->assertTrue($penerima->can('view', $dokumen));
        $this->assertFalse($penerima->can('update', $dokumen));
    }

    /**
     * Sisi sebaliknya: Mekanisme 1-4 tidak ikut terpotong. Dokumen yang sama,
     * tetapi kali ini dibagikan LANGSUNG ke orangnya pada dokumen itu sendiri
     * — pemberian eksplisit itulah yang memberi hak ubah, bukan foldernya.
     */
    public function test_berbagi_langsung_ke_orang_tetap_memberi_hak_ubah_walau_dokumennya_di_folder_yang_dibagikan(): void
    {
        $pemilik = User::factory()->create();
        $penerima = User::factory()->create();
        $folder = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Arsip', 'name_normalized' => 'arsip']);
        $folder->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $folder, ['edit_scope' => DocumentEditScope::MatchVisibility]);
        $dokumen->sharedUsers()->attach($penerima->id, ['granted_by' => $pemilik->id]);

        $this->assertTrue($penerima->can('view', $dokumen));
        $this->assertTrue($penerima->can('update', $dokumen));
    }

    public function test_dokumen_terlihat_lewat_folder_yang_dibagikan_ke_unit(): void
    {
        $pemilik = User::factory()->create();
        $unit = Unit::factory()->create();
        $penerima = User::factory()->create(['unit_id' => $unit->id]);
        $folder = DocumentFolder::create(['owner_id' => $pemilik->id, 'name' => 'Arsip', 'name_normalized' => 'arsip']);
        $folder->targetUnits()->attach($unit->id, ['added_by' => $pemilik->id]);
        $dokumen = $this->dokumenDiFolder($pemilik, $folder);

        $this->assertTrue(Document::query()->visibleTo($penerima)->whereKey($dokumen->id)->exists());
    }
}

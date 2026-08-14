<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjaga relasi pivot mekanisme akses tetap dapat ditulis.
 *
 * Tabel `document_units` dan `document_shares` sengaja hanya punya `created_at`
 * — barisnya adalah catatan kejadian ("unit ini diberi akses pada waktu ini"),
 * bukan data yang disunting, sehingga `updated_at` akan selalu identik dengan
 * `created_at` dan tidak membawa informasi apa pun.
 *
 * Konsekuensinya, relasi TIDAK BOLEH memakai `withTimestamps()`: helper itu
 * selalu ikut menulis `updated_at` dan membuat `attach()` gagal. Tes ini yang
 * menahan supaya pemanggilannya tidak ditambahkan kembali suatu saat.
 */
final class DocumentRelationTest extends TestCase
{
    use RefreshDatabase;

    private function buatDokumen(): Document
    {
        $jabatan = Jabatan::create(['nama' => 'Anggota', 'tingkat_akses' => 4]);
        $unit = Unit::create(['nama' => 'Divisi Uji', 'tipe' => Unit::TIPE_DIVISI]);
        $category = Category::create(['nama' => 'SOP']);

        $user = User::factory()->create([
            'jabatan_id' => $jabatan->id,
            'unit_id' => $unit->id,
        ]);

        return Document::create([
            'nomor' => '001/BPMA/UJI/I/2026',
            'judul' => 'Dokumen Uji Relasi',
            'category_id' => $category->id,
            'origin_unit_id' => $unit->id,
            'tanggal' => '2026-01-05',
            'status' => DocumentStatus::Berlaku,
            'file_path' => 'documents/2026/01/uji.pdf',
            'file_name_original' => 'uji.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_by' => $user->id,
        ]);
    }

    public function test_unit_dapat_dilekatkan_beserta_jejak_pemberi_akses(): void
    {
        $document = $this->buatDokumen();
        $unit = Unit::first();
        $pemberi = User::first();

        $document->targetUnits()->attach($unit->id, ['added_by' => $pemberi->id]);

        $terlampir = $document->targetUnits()->first();

        $this->assertSame($unit->id, $terlampir->id);
        $this->assertSame($pemberi->id, $terlampir->pivot->added_by);
        // Diisi otomatis oleh default `useCurrent()` pada skema, bukan Eloquent.
        $this->assertNotNull($terlampir->pivot->created_at);
    }

    public function test_pengguna_dapat_dilekatkan_beserta_jejak_pemberi_akses(): void
    {
        $document = $this->buatDokumen();
        $penerima = User::first();

        $document->sharedUsers()->attach($penerima->id, ['granted_by' => $penerima->id]);

        $terlampir = $document->sharedUsers()->first();

        $this->assertSame($penerima->id, $terlampir->id);
        $this->assertSame($penerima->id, $terlampir->pivot->granted_by);
        $this->assertNotNull($terlampir->pivot->created_at);
    }

    public function test_akses_dapat_dicabut_kembali(): void
    {
        $document = $this->buatDokumen();
        $unit = Unit::first();

        $document->targetUnits()->attach($unit->id);
        $this->assertSame(1, $document->targetUnits()->count());

        $document->targetUnits()->detach($unit->id);
        $this->assertSame(0, $document->targetUnits()->count());
    }

    public function test_unit_yang_sama_tidak_dapat_dilekatkan_dua_kali(): void
    {
        $document = $this->buatDokumen();
        $unit = Unit::first();

        $document->targetUnits()->attach($unit->id);

        // Batasan unik `(document_id, unit_id)` mencegah baris kembar yang akan
        // membuat dokumen terhitung ganda saat dijumlahkan.
        $this->expectException(UniqueConstraintViolationException::class);
        $document->targetUnits()->attach($unit->id);
    }

    public function test_kolom_daftar_tidak_pernah_memuat_extracted_text(): void
    {
        // Aturan performa terpenting proyek ini: `extracted_text` bertipe
        // longText dan tidak boleh ikut terambil pada query daftar.
        $this->assertNotContains('extracted_text', Document::KOLOM_DAFTAR);
    }
}

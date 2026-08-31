<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DocumentListData;
use App\Http\Requests\DocumentIndexRequest;
use App\Models\Document;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Membangun daftar dokumen terpaginasi: penyaring, pencarian, urutan, lalu
 * pemetaan ke `DocumentListData` — dipakai bersama oleh Jelajahi Dokumen dan
 * setiap halaman workspace (Dokumen Saya, Terbaru, Berbintang, Sampah).
 *
 * Diekstrak dari `DocumentController` supaya kelima halaman itu benar-benar
 * berperilaku sama (FEAT konsistensi tampilan dokumen) — menyalin logika
 * pencarian FULLTEXT yang rumit ke tempat lain akan membuatnya perlahan
 * menyimpang, dan pengguna akan menemukan dokumen yang sama dicari secara
 * berbeda tergantung halaman mana yang sedang dibuka.
 *
 * Pemanggil hanya perlu menyiapkan query DASAR (mis. `visibleTo($user)` untuk
 * Jelajahi Dokumen, atau `where('uploaded_by', $user->id)` untuk Dokumen
 * Saya) — kolom yang dipilih, eager load, penyaring, pencarian, urutan, dan
 * pemetaan hasil semuanya ditangani di sini.
 */
final class DocumentListingService
{
    public function __construct(private readonly PengaturanService $pengaturan) {}

    /**
     * @param  Builder<Document>  $queryDasar  Query dengan batasan visibilitas/kepemilikan sudah diterapkan.
     * @param  (Closure(Document): DocumentListData)|null  $pemetaan  Bawaan `DocumentListData::fromModel()`; halaman workspace mengirim bentuk `untukWorkspace()` untuk menyertakan status bintang/retensi Sampah.
     */
    public function paginasi(Builder $queryDasar, DocumentIndexRequest $request, User $user, ?Closure $pemetaan = null): LengthAwarePaginator
    {
        $pemetaan ??= fn (Document $document): DocumentListData => DocumentListData::fromModel($document, $user);
        $kata = trim($request->string('cari')->toString());

        $query = $queryDasar
            // Kolom dibatasi eksplisit. `extracted_text` bertipe longText dan
            // dapat berukuran megabyte per baris — memuatnya untuk dua puluh
            // baris berarti menyeret puluhan megabyte demi data yang tidak
            // ditampilkan sama sekali di daftar.
            ->select(Document::KOLOM_DAFTAR)
            ->with([
                'category:id,nama',
                'originUnit:id,nama',
                'uploader:id,name,jabatan_id',
                'uploader.jabatan:id,nama',
                // Dua relasi ini dimuat karena daftar memang menampilkan
                // ringkasan mekanisme akses di tiap baris.
                'targetUnits:id,nama',
                'sharedUsers:id',
            ])
            ->when(
                $request->integer('kategori'),
                fn ($query, int $id) => $query->where('documents.category_id', $id),
            )
            ->when(
                $request->integer('unit'),
                fn ($query, int $id) => $query->where('documents.origin_unit_id', $id),
            )
            ->when(
                $request->string('status')->toString(),
                fn ($query, string $status) => $query->where('documents.status', $status),
            )
            ->when($request->string('status_ekstraksi')->toString(), fn ($query, string $status) => $query->where('documents.extraction_status', $status))
            ->when($request->integer('pengunggah'), fn ($query, int $id) => $query->where('documents.uploaded_by', $id))
            ->when($request->string('tipe')->toString(), function ($query, string $tipe): void {
                match ($tipe) {
                    'pdf' => $query->where('documents.file_mime_type', 'application/pdf'),
                    'gambar' => $query->where('documents.file_mime_type', 'like', 'image/%'),
                    'word' => $query->where(function ($query): void {
                        $query->where('documents.file_mime_type', 'like', '%wordprocessingml%')
                            ->orWhere('documents.file_mime_type', 'like', '%msword%');
                    }),
                    'excel' => $query->where(function ($query): void {
                        $query->where('documents.file_mime_type', 'like', '%spreadsheetml%')
                            ->orWhere('documents.file_mime_type', 'like', '%ms-excel%');
                    }),
                    'ppt' => $query->where(function ($query): void {
                        $query->where('documents.file_mime_type', 'like', '%presentationml%')
                            ->orWhere('documents.file_mime_type', 'like', '%ms-powerpoint%');
                    }),
                    'teks' => $query->where('documents.file_mime_type', 'text/plain'),
                    default => $query->whereNotIn('documents.file_mime_type', ['application/pdf', 'text/plain'])
                        ->where('documents.file_mime_type', 'not like', 'image/%')
                        ->where('documents.file_mime_type', 'not like', '%wordprocessingml%')
                        ->where('documents.file_mime_type', 'not like', '%msword%')
                        ->where('documents.file_mime_type', 'not like', '%spreadsheetml%')
                        ->where('documents.file_mime_type', 'not like', '%ms-excel%')
                        ->where('documents.file_mime_type', 'not like', '%presentationml%')
                        ->where('documents.file_mime_type', 'not like', '%ms-powerpoint%'),
                };
            })
            ->when(
                $request->string('dari')->toString(),
                fn ($query, string $tanggal) => $query->whereDate('documents.tanggal', '>=', $tanggal),
            )
            ->when(
                $request->string('sampai')->toString(),
                fn ($query, string $tanggal) => $query->whereDate('documents.tanggal', '<=', $tanggal),
            )
            ->when(
                $request->integer('evaluasi'),
                fn ($query, int $hari) => $query->mendekatiMasaEvaluasi($hari),
            );

        $pencarianDenganRelevansi = false;
        if ($kata !== '') {
            $query->where(fn ($pencarian) => $this->terapkanPencarian($pencarian, $kata));
            $pencarianDenganRelevansi = $this->tambahkanKonteksPencarian($query, $kata);
        }

        if ($request->integer('evaluasi') && ! $request->boolean('urut_manual')) {
            // Paling dekat kedaluwarsa lebih dulu — itulah yang membuat
            // filter ini berguna, bukan urutan tanggal unggah bawaan.
            $query->orderBy('documents.masa_berlaku', 'asc');
        } elseif ($pencarianDenganRelevansi && ! $request->boolean('urut_manual')) {
            $query->orderByDesc('search_field_priority')
                ->orderByDesc('search_relevance');
        } else {
            $query->orderBy($request->kolomUrutan(), $request->arahUrutan());
        }

        return $query
            // Pengurutan kedua menjaga urutan tetap sama antar halaman. Tanpa
            // ini, baris dengan tanggal kembar dapat berpindah halaman di
            // antara dua permintaan — dokumen yang sama muncul dua kali, atau
            // hilang sama sekali.
            ->orderBy('documents.id', 'desc')
            ->paginate($this->pengaturan->integer('dokumen.per_halaman') ?? (int) config('dms.dokumen.per_halaman'))
            ->withQueryString()
            ->through($pemetaan);
    }

    /**
     * Pencarian isi dokumen lewat index FULLTEXT (FR-34).
     *
     * `nomor` sengaja ikut di DALAM index FULLTEXT yang sama dengan
     * judul/deskripsi/isi (migration `add_nomor_to_documents_fulltext_index`),
     * bukan dicocokkan terpisah lewat `LIKE` yang di-`OR`-kan. Percobaan
     * awal menggabungkan `MATCH...AGAINST` dengan `OR nomor LIKE` terbukti
     * lewat `EXPLAIN` membuat MariaDB berhenti memakai index FULLTEXT sama
     * sekali (`type` jatuh ke `ALL`, pemindaian tabel penuh) — kombinasi
     * MATCH dengan OR ke kolom lain mematikan optimasinya.
     *
     * Mode `boolean` dipakai, bukan mode alami bawaan: mode alami
     * menyingkirkan kata yang muncul di lebih dari separuh baris tabel
     * (ambang relevansi bawaan MySQL) begitu tabelnya punya cukup baris —
     * perilaku yang bisa membuat kata kunci yang jelas-jelas cocok tiba-tiba
     * tidak ditemukan, tanpa galat apa pun.
     *
     * @param  Builder<Document>  $query
     */
    private function terapkanPencarian(Builder $query, string $kata): void
    {
        $kata = trim($kata);
        $nomor = preg_replace('/[^a-z0-9]+/i', '', strtolower($kata)) ?? '';
        if ($this->adalahPencarianNomor($kata, $nomor)) {
            $query->where('documents.nomor_normalized', 'like', $nomor.'%');

            return;
        }

        // InnoDB (`innodb_ft_min_token_size`, bawaan 3) tidak mengindeks kata
        // di bawah 3 huruf sama sekali — mencarinya lewat FULLTEXT tidak akan
        // pernah menemukan apa pun walau kata itu ada berkali-kali di dalam
        // teks. Jaring pengaman `LIKE` untuk kasus ini sengaja dibatasi ke
        // judul dan nomor, tidak menyentuh `extracted_text`: memindai teks
        // sepanjang isi dokumen dengan `LIKE` untuk tiap baris meniadakan
        // keuntungan performa yang justru menjadi alasan FEAT-12 ada.
        if (mb_strlen($kata) < 3) {
            $query
                ->where('documents.judul', 'like', "%{$kata}%")
                ->orWhere('documents.nomor', 'like', "%{$kata}%");

            return;
        }

        $query->whereFullText(
            ['documents.nomor', 'documents.judul', 'documents.deskripsi', 'documents.extracted_text'],
            $this->kueriBoolean($kata),
            ['mode' => 'boolean'],
        );
    }

    /**
     * Menambahkan metadata hasil pencarian tanpa memilih `extracted_text`.
     *
     * Projection ini dihitung oleh SQL hanya untuk baris halaman aktif.
     * Cuplikannya maksimum 220 karakter, sehingga daftar dapat menjelaskan
     * "ditemukan di mana" tanpa menjadikan endpoint daftar sebagai API isi
     * dokumen penuh.
     *
     * @param  Builder<Document>  $query
     */
    private function tambahkanKonteksPencarian(Builder $query, string $kata): bool
    {
        $kata = trim($kata);
        $nomor = preg_replace('/[^a-z0-9]+/i', '', strtolower($kata)) ?? '';

        if ($this->adalahPencarianNomor($kata, $nomor)) {
            $query->selectRaw('1 AS search_matches_nomor')
                ->selectRaw('0 AS search_matches_judul')
                ->selectRaw('0 AS search_matches_deskripsi')
                ->selectRaw('0 AS search_matches_isi');

            return false;
        }

        $frasa = mb_strtolower($kata);
        $pola = '%'.$this->escapeLike($frasa).'%';
        $nomorDalamFrasa = $this->nomorDiDalamFrasa($kata);
        $query->selectRaw('CASE WHEN LOWER(documents.nomor) LIKE ? THEN 1 ELSE 0 END AS search_matches_nomor', [$pola])
            ->selectRaw('CASE WHEN LOWER(documents.judul) LIKE ? THEN 1 ELSE 0 END AS search_matches_judul', [$pola])
            ->selectRaw('CASE WHEN LOWER(COALESCE(documents.deskripsi, \'\')) LIKE ? THEN 1 ELSE 0 END AS search_matches_deskripsi', [$pola]);

        if (mb_strlen($kata) < 3) {
            $query->selectRaw('0 AS search_matches_isi');

            return false;
        }

        $kataCuplikan = $this->kataCuplikan($frasa);
        if ($kataCuplikan === '') {
            $query->selectRaw('0 AS search_matches_isi');

            return false;
        }

        $query->selectRaw(
            'MATCH(documents.nomor, documents.judul, documents.deskripsi, documents.extracted_text) AGAINST (? IN BOOLEAN MODE) AS search_relevance',
            [$this->kueriBoolean($kata)],
        )
            ->selectRaw(
                'CASE
                    WHEN LOWER(documents.judul) LIKE ? THEN 600
                    WHEN LOWER(documents.judul) LIKE ? THEN 500
                    WHEN ? <> \'\' AND documents.nomor_normalized LIKE ? THEN 400
                    WHEN LOWER(COALESCE(documents.deskripsi, \'\')) LIKE ? THEN 300
                    ELSE 0
                END AS search_field_priority',
                [
                    $pola,
                    '%'.$this->escapeLike($this->kataCuplikan($frasa)).'%',
                    $nomorDalamFrasa,
                    '%'.$this->escapeLike($nomorDalamFrasa).'%',
                    $pola,
                ],
            )
            ->selectRaw(
                'CASE WHEN LOCATE(?, LOWER(COALESCE(documents.extracted_text, \'\'))) > 0 THEN 1 ELSE 0 END AS search_matches_isi',
                [$kataCuplikan],
            )
            ->selectRaw(
                'CASE WHEN LOCATE(?, LOWER(COALESCE(documents.extracted_text, \'\'))) > 0 THEN SUBSTRING(documents.extracted_text, GREATEST(1, LOCATE(?, LOWER(COALESCE(documents.extracted_text, \'\'))) - 80), 220) END AS search_excerpt',
                [$kataCuplikan, $kataCuplikan],
            )
            ->selectRaw(
                'CASE WHEN LOCATE(?, LOWER(COALESCE(documents.extracted_text, \'\'))) > 0 THEN (CHAR_LENGTH(LOWER(documents.extracted_text)) - CHAR_LENGTH(REPLACE(LOWER(documents.extracted_text), ?, \'\'))) / NULLIF(CHAR_LENGTH(?), 0) ELSE 0 END AS search_phrase_count',
                [$frasa, $frasa, $frasa],
            );

        return true;
    }

    private function adalahPencarianNomor(string $kata, string $nomor): bool
    {
        // Hanya nomor dokumen MURNI yang memakai jalur prefix terindeks.
        // "notulen 002/BPMA" adalah pencarian campuran, bukan nomor, dan
        // wajib tetap mencari judul + nomor melalui FULLTEXT.
        return $nomor !== ''
            && preg_match('/^(?=.*\\d)[a-z0-9]+(?:[\\/-][a-z0-9]+)+$/i', $kata) === 1;
    }

    private function kueriBoolean(string $kata): string
    {
        $istilah = $this->istilahPencarian($kata);

        return $istilah === [] ? $kata : implode(' ', array_map(fn (string $istilah): string => "+{$istilah}", $istilah));
    }

    /** @return list<string> */
    private function istilahPencarian(string $kata): array
    {
        preg_match_all('/[\\p{L}\\p{N}]{3,}/u', mb_strtolower($kata), $hasil);

        return array_values(array_unique($hasil[0]));
    }

    private function nomorDiDalamFrasa(string $kata): string
    {
        if (preg_match('/\\b\\d{2,}(?:[\\/-][[:alnum:]]+)+/iu', $kata, $hasil) !== 1) {
            return '';
        }

        return preg_replace('/[^a-z0-9]+/i', '', strtolower($hasil[0])) ?? '';
    }

    private function escapeLike(string $nilai): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $nilai);
    }

    private function kataCuplikan(string $kata): string
    {
        return $this->istilahPencarian($kata)[0] ?? '';
    }
}

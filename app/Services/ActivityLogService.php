<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu-satunya pintu untuk menulis jejak audit aplikasi.
 *
 * Logging sengaja dipanggil dari aksi bisnis, bukan dari event Eloquent umum.
 * Aksi seperti unggah dan perubahan akses melintasi lebih dari satu tabel;
 * event model otomatis tidak mengetahui konteks transaksi atau siapa target
 * akses yang baru ditambahkan/dicabut. Dengan pintu ini, setiap baris memiliki
 * log name, kode aksi, subjek, pelaku, konteks, dan before/after yang seragam.
 */
final class ActivityLogService
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(
        ActivityLogName $logName,
        AuditEvent $event,
        string $description,
        Model $subject,
        ?User $causer,
        array $properties = [],
        array $before = [],
        array $after = [],
    ): void {
        $logger = activity($logName)
            ->event($event->value)
            ->performedOn($subject)
            ->withProperties([
                ...$properties,
                'subjek' => $this->subjectSummary($subject),
                // Dipakai oleh query riwayat biasa untuk menjamin kegiatan
                // non-dokumen tidak lolos ke pengguna yang bukan Superadmin.
                'dokumen_id' => $subject instanceof Document ? $subject->id : null,
            ]);

        if ($before !== [] || $after !== []) {
            $logger->withChanges([
                'old' => $before,
                'attributes' => $after,
            ]);
        }

        // `activity()` otomatis mengambil pengguna login. Itu benar untuk
        // request web, tetapi salah untuk scheduler/queue karena konteks worker
        // dapat membawa pelaku lama. Baris sistem harus eksplisit anonim agar
        // antarmuka dapat menampilkannya sebagai “Sistem”.
        if ($causer instanceof User) {
            $logger->causedBy($causer);
        } else {
            $logger->causedByAnonymous();
        }

        $logger->log($description);
    }

    /**
     * Ringkasan disimpan bersama aktivitas agar halaman riwayat tidak perlu
     * memuat morph subject satu per satu. Ini menjaga pagination stabil saat
     * log berisi ratusan baris dari beberapa tipe subjek.
     *
     * @return array{tipe: string, id: int|string, label: string}
     */
    private function subjectSummary(Model $subject): array
    {
        return match (true) {
            $subject instanceof Document => [
                'tipe' => 'Dokumen',
                'id' => $subject->id,
                'label' => $subject->judul,
            ],
            $subject instanceof User => [
                'tipe' => 'Pengguna',
                'id' => $subject->id,
                'label' => $subject->name,
            ],
            $subject instanceof Unit => [
                'tipe' => 'Unit kerja',
                'id' => $subject->id,
                'label' => $subject->nama,
            ],
            $subject instanceof Jabatan => [
                'tipe' => 'Jabatan',
                'id' => $subject->id,
                'label' => $subject->nama,
            ],
            $subject instanceof Category => [
                'tipe' => 'Kategori',
                'id' => $subject->id,
                'label' => $subject->nama,
            ],
            default => [
                'tipe' => class_basename($subject),
                'id' => $subject->getKey(),
                'label' => class_basename($subject).' #'.$subject->getKey(),
            ],
        };
    }
}

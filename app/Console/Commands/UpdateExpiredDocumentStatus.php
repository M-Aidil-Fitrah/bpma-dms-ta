<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\ActivityLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Memindahkan dokumen Berlaku yang lewat masa berlaku ke Kadaluarsa (FR-53).
 *
 * Setiap id dikunci dan diperiksa ulang di dalam transaksi. Selain membuat
 * pemanggilan berulang aman, ini menutup balapan bila jadwal manual dan worker
 * scheduler sempat berjalan bersamaan: hanya transaksi yang benar-benar
 * mengubah status yang boleh menulis satu aktivitas otomatis.
 */
final class UpdateExpiredDocumentStatus extends Command
{
    protected $signature = 'documents:update-expired-status';

    protected $description = 'Mengubah dokumen Berlaku yang masa berlakunya lewat menjadi Kadaluarsa';

    private const UKURAN_POTONGAN = 100;

    public function handle(ActivityLogService $aktivitas): int
    {
        $hariIni = today()->toDateString();
        $jumlahDiubah = 0;

        Document::query()
            ->where('status', DocumentStatus::Berlaku)
            ->whereNotNull('masa_berlaku')
            ->whereDate('masa_berlaku', '<', $hariIni)
            ->select('id')
            ->chunkById(self::UKURAN_POTONGAN, function ($documents) use ($hariIni, $aktivitas, &$jumlahDiubah): void {
                foreach ($documents as $candidate) {
                    if ($this->kadaluarsakan((int) $candidate->id, $hariIni, $aktivitas)) {
                        $jumlahDiubah++;
                    }
                }
            });

        // `line()` dipakai, bukan komponen terformat, supaya hasil perintah
        // tetap mudah dibaca manusia dan dapat diverifikasi persis oleh tes.
        $this->line("Dokumen diubah: {$jumlahDiubah}.");

        return self::SUCCESS;
    }

    private function kadaluarsakan(int $documentId, string $hariIni, ActivityLogService $aktivitas): bool
    {
        return DB::transaction(function () use ($documentId, $hariIni, $aktivitas): bool {
            $document = Document::query()->lockForUpdate()->find($documentId);

            if (
                $document === null
                || $document->status !== DocumentStatus::Berlaku
                || $document->masa_berlaku === null
                || $document->masa_berlaku->toDateString() >= $hariIni
            ) {
                return false;
            }

            $document->update(['status' => DocumentStatus::Kadaluarsa]);

            $aktivitas->record(
                ActivityLogName::Dokumen,
                AuditEvent::DocumentStatusChanged,
                'Status dokumen berubah otomatis menjadi Kadaluarsa.',
                $document,
                null,
                ['trigger' => 'otomatis'],
                before: ['Status' => DocumentStatus::Berlaku->label()],
                after: ['Status' => DocumentStatus::Kadaluarsa->label()],
            );

            return true;
        });
    }
}

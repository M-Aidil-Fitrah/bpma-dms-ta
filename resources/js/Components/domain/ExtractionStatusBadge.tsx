import { Badge } from '@/Components/ui/Badge';
import { CheckCircle2, CircleSlash, Loader2, TriangleAlert } from 'lucide-react';
import type { TFunction } from 'i18next';
import { useTranslation } from 'react-i18next';

/**
 * Status ekstraksi isi dokumen menjadi teks yang dapat dicari.
 *
 * Perhatikan `failed` memakai warna peringatan, bukan bahaya. Ekstraksi yang
 * gagal tidak merusak apa pun — berkasnya tetap utuh dan tetap dapat diunduh,
 * hanya isinya yang tidak ikut terindeks pencarian. Mewarnainya merah akan
 * membuat pengguna mengira dokumennya rusak.
 */
function buatPeta(t: TFunction) {
    return {
        not_applicable: {
            variant: 'neutral',
            label: t('documentBrowse:extractionStatus.notApplicable.label'),
            icon: CircleSlash,
            keterangan: t('documentBrowse:extractionStatus.notApplicable.keterangan'),
        },
        pending: {
            variant: 'info',
            label: t('documentBrowse:extractionStatus.pending.label'),
            icon: Loader2,
            keterangan: t('documentBrowse:extractionStatus.pending.keterangan'),
        },
        completed: {
            variant: 'success',
            label: t('documentBrowse:extractionStatus.completed.label'),
            icon: CheckCircle2,
            keterangan: t('documentBrowse:extractionStatus.completed.keterangan'),
        },
        review_required: {
            variant: 'warning',
            label: t('documentBrowse:extractionStatus.reviewRequired.label'),
            icon: TriangleAlert,
            keterangan: t('documentBrowse:extractionStatus.reviewRequired.keterangan'),
        },
        failed: {
            variant: 'warning',
            label: t('documentBrowse:extractionStatus.failed.label'),
            icon: TriangleAlert,
            keterangan: t('documentBrowse:extractionStatus.failed.keterangan'),
        },
    } as const;
}

export interface ExtractionStatusBadgeProps {
    status: App.Enums.ExtractionStatus;
    halamanTotal?: number | null;
    halamanSelesai?: number | null;
    estimasiDetik?: number | null;
    pesan?: string | null;
    size?: 'sm' | 'md';
    /** Sembunyikan ikon di ruang sempit seperti sel tabel. */
    tanpaIkon?: boolean;
}

export function ExtractionStatusBadge({
    status,
    halamanTotal = null,
    halamanSelesai = null,
    estimasiDetik = null,
    pesan = null,
    size = 'md',
    tanpaIkon = false,
}: ExtractionStatusBadgeProps) {
    const { t } = useTranslation('documentBrowse');
    const { variant, label, icon: Icon, keterangan } = buatPeta(t)[status];
    const progres = status === 'pending' && halamanTotal !== null
        ? (estimasiDetik === null
            ? t('documentBrowse:extractionStatus.progres', { selesai: halamanSelesai ?? 0, total: halamanTotal })
            : t('documentBrowse:extractionStatus.progresDenganEstimasi', { selesai: halamanSelesai ?? 0, total: halamanTotal, durasi: formatDurasi(estimasiDetik, t) }))
        : null;
    const penjelasan = progres ?? pesan ?? keterangan;

    return (
        <span className="inline-flex flex-col items-start gap-1" title={penjelasan}>
            <Badge variant={variant} size={size}>
                {!tanpaIkon && (
                    <Icon
                        className={status === 'pending' ? 'size-3 animate-spin' : 'size-3'}
                        aria-hidden
                    />
                )}
                {label}
            </Badge>
            {(progres !== null || pesan !== null) && (
                <span className="text-xs text-ink-muted" aria-live="polite">
                    {penjelasan}
                </span>
            )}
        </span>
    );
}

function formatDurasi(detik: number, t: TFunction): string {
    if (detik < 60) {
        return t('documentBrowse:extractionStatus.durasi.kurangSatuMenit');
    }

    const menit = Math.floor(detik / 60);
    const sisaDetik = detik % 60;

    return sisaDetik === 0
        ? t('documentBrowse:extractionStatus.durasi.menit', { menit })
        : t('documentBrowse:extractionStatus.durasi.menitDetik', { menit, detik: sisaDetik });
}

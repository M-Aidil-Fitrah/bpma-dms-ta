import { Badge } from '@/Components/ui/Badge';
import { CheckCircle2, CircleSlash, Loader2, TriangleAlert } from 'lucide-react';

/**
 * Status ekstraksi isi dokumen menjadi teks yang dapat dicari.
 *
 * Perhatikan `failed` memakai warna peringatan, bukan bahaya. Ekstraksi yang
 * gagal tidak merusak apa pun — berkasnya tetap utuh dan tetap dapat diunduh,
 * hanya isinya yang tidak ikut terindeks pencarian. Mewarnainya merah akan
 * membuat pengguna mengira dokumennya rusak.
 */
const PETA = {
    not_applicable: {
        variant: 'neutral',
        label: 'Lampiran biasa',
        icon: CircleSlash,
        keterangan: 'Tipe berkas ini tidak mendukung pencarian isi.',
    },
    pending: {
        variant: 'info',
        label: 'Memproses',
        icon: Loader2,
        keterangan: 'Isi dokumen sedang dibaca di latar belakang.',
    },
    completed: {
        variant: 'success',
        label: 'Dapat dicari',
        icon: CheckCircle2,
        keterangan: 'Isi dokumen sudah terbaca dan dapat ditemukan lewat pencarian.',
    },
    failed: {
        variant: 'warning',
        label: 'Ekstraksi gagal',
        icon: TriangleAlert,
        keterangan: 'Isi tidak terbaca. Berkas tetap dapat diunduh seperti biasa.',
    },
} as const;

export interface ExtractionStatusBadgeProps {
    status: App.Enums.ExtractionStatus;
    size?: 'sm' | 'md';
    /** Sembunyikan ikon di ruang sempit seperti sel tabel. */
    tanpaIkon?: boolean;
}

export function ExtractionStatusBadge({
    status,
    size = 'md',
    tanpaIkon = false,
}: ExtractionStatusBadgeProps) {
    const { variant, label, icon: Icon, keterangan } = PETA[status];

    return (
        <span title={keterangan}>
            <Badge variant={variant} size={size}>
                {!tanpaIkon && (
                    <Icon
                        className={status === 'pending' ? 'size-3 animate-spin' : 'size-3'}
                        aria-hidden
                    />
                )}
                {label}
            </Badge>
        </span>
    );
}

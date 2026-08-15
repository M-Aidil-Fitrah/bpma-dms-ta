import { Badge } from '@/Components/ui/Badge';

/**
 * Pemetaan status dokumen ke warna hanya hidup di sini.
 *
 * Halaman tidak boleh memilih warnanya sendiri — kalau boleh, "Kadaluarsa" bisa
 * tampil merah di satu halaman dan abu-abu di halaman lain, dan pengguna
 * kehilangan pegangan untuk membaca sekilas.
 */
const PETA = {
    berlaku: { variant: 'success', label: 'Berlaku' },
    kadaluarsa: { variant: 'danger', label: 'Kadaluarsa' },
} as const;

export interface DocumentStatusBadgeProps {
    status: App.Enums.DocumentStatus;
    size?: 'sm' | 'md';
}

export function DocumentStatusBadge({ status, size = 'md' }: DocumentStatusBadgeProps) {
    const { variant, label } = PETA[status];

    return (
        <Badge variant={variant} size={size}>
            {label}
        </Badge>
    );
}

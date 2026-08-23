import { Badge } from '@/Components/ui/Badge';
import { useTranslation } from 'react-i18next';

/**
 * Pemetaan status dokumen ke warna hanya hidup di sini.
 *
 * Halaman tidak boleh memilih warnanya sendiri — kalau boleh, "Kadaluarsa" bisa
 * tampil merah di satu halaman dan abu-abu di halaman lain, dan pengguna
 * kehilangan pegangan untuk membaca sekilas.
 */
const PETA = {
    berlaku: { variant: 'success', labelKey: 'common:status.berlaku' },
    kadaluarsa: { variant: 'danger', labelKey: 'common:status.kedaluwarsa' },
} as const;

export interface DocumentStatusBadgeProps {
    status: App.Enums.DocumentStatus;
    size?: 'sm' | 'md';
}

export function DocumentStatusBadge({ status, size = 'md' }: DocumentStatusBadgeProps) {
    const { t } = useTranslation(['documentBrowse', 'common']);
    const { variant, labelKey } = PETA[status];

    return (
        <Badge variant={variant} size={size}>
            {t(labelKey)}
        </Badge>
    );
}

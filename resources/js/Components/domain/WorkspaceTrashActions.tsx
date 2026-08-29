import { Button } from '@/Components/ui/Button';
import { router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';

export interface WorkspaceTrashActionsProps {
    document: App.Data.DocumentListData;
    className?: string;
}

/**
 * Aksi dokumen di Sampah — menggantikan `DocumentActions` baku lewat prop
 * `aksi`. Lihat/Unduh/Bintang tidak masuk akal untuk dokumen yang sudah
 * dibuang; satu-satunya aksi yang berarti di sini adalah Pulihkan, disertai
 * sisa waktu sebelum terhapus permanen supaya keputusannya tidak mendadak.
 */
export function WorkspaceTrashActions({ document, className }: WorkspaceTrashActionsProps) {
    const { t } = useTranslation('workspace');

    function restore() {
        router.patch(`/documents/${document.id}/restore-trash`, {}, { preserveScroll: true });
    }

    return (
        <div className={className}>
            <p className="mb-1.5 whitespace-nowrap text-xs text-ink-muted">
                {sisaRetensi(document.purge_after, t)}
            </p>
            <Button variant="secondary" size="sm" icon={RotateCcw} onClick={restore}>
                {t('trash.tombolPulihkan.label')}
            </Button>
        </div>
    );
}

function sisaRetensi(purgeAfter: string | null, t: TFunction): string {
    if (!purgeAfter) return t('trash.retensi.belumTersedia');

    const days = Math.max(0, Math.ceil((new Date(purgeAfter).getTime() - Date.now()) / 86_400_000));

    return days === 0 ? t('trash.retensi.hariIni') : t('trash.retensi.tersisa', { hari: days });
}

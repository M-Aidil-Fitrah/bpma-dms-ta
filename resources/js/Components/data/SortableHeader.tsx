import { cn } from '@/lib/cn';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export interface SortableHeaderProps {
    label: string;
    /** Kunci pengurutan yang dikirim ke server. */
    kunci: string;
    kunciAktif: string;
    arah: 'asc' | 'desc';
    onSort: (kunci: string, arah: 'asc' | 'desc') => void;
    className?: string;
}

/**
 * Kepala kolom yang dapat diurutkan.
 *
 * Menekan kolom yang sedang aktif membalik arahnya; menekan kolom lain memulai
 * dari menurun — untuk tanggal dan nomor, yang terbaru hampir selalu yang
 * dicari lebih dulu.
 */
export function SortableHeader({
    label,
    kunci,
    kunciAktif,
    arah,
    onSort,
    className,
}: SortableHeaderProps) {
    const { t } = useTranslation('common');
    const aktif = kunci === kunciAktif;
    const Icon = aktif ? (arah === 'asc' ? ArrowUp : ArrowDown) : ChevronsUpDown;

    return (
        <th scope="col" className={cn('px-4 py-3 text-left', className)}>
            <button
                type="button"
                onClick={() => onSort(kunci, aktif && arah === 'desc' ? 'asc' : 'desc')}
                aria-label={t('ui.urutkanBerdasarkan', { label })}
                className={cn(
                    'inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider transition-colors',
                    aktif ? 'text-ink' : 'text-ink-subtle hover:text-ink-muted',
                )}
            >
                {label}
                <Icon className="size-3.5" aria-hidden />
            </button>
        </th>
    );
}

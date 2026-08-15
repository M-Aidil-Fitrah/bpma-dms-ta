import { cn } from '@/lib/cn';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

export interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    /**
     * Wajib. Keadaan kosong tanpa penjelasan membuat pengguna tidak tahu apakah
     * sistemnya rusak, datanya memang belum ada, atau penyaringnya terlalu
     * sempit — tiga hal yang butuh tindakan berbeda.
     */
    description: string;
    action?: ReactNode;
    className?: string;
}

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div className={cn('flex flex-col items-center px-6 py-12 text-center', className)}>
            <span className="mb-4 inline-flex size-12 items-center justify-center rounded-full bg-surface-sunken text-ink-subtle">
                <Icon className="size-6" aria-hidden />
            </span>

            <h3 className="text-base font-semibold text-ink">{title}</h3>
            <p className="mt-1 max-w-sm text-sm text-ink-muted">{description}</p>

            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}

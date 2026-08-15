import { Badge } from '@/Components/ui/Badge';
import { cn } from '@/lib/cn';
import { formatAngka } from '@/lib/format';
import { type LucideIcon } from 'lucide-react';

const TONES = {
    brand: 'bg-brand-50 text-brand-700',
    success: 'bg-success-soft text-success-strong',
    warning: 'bg-warning-soft text-warning-strong',
    danger: 'bg-danger-soft text-danger-strong',
} as const;

export interface StatCardProps {
    label: string;
    value: number;
    icon: LucideIcon;
    tone?: keyof typeof TONES;
    /** Keterangan singkat di bawah angka, mis. "dalam 30 hari". */
    caption?: string;
    className?: string;
}

/**
 * Kartu satu angka statistik pada dasbor.
 *
 * Angka diformat sesuai kaidah Indonesia (1.245, bukan 1,245) lewat helper
 * bersama, supaya tidak ada halaman yang memformatnya dengan cara berbeda.
 */
export function StatCard({
    label,
    value,
    icon: Icon,
    tone = 'brand',
    caption,
    className,
}: StatCardProps) {
    return (
        <div
            className={cn(
                'rounded-card border border-line bg-surface p-4 shadow-card sm:p-5',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm text-ink-muted">{label}</p>
                <span
                    className={cn(
                        'inline-flex size-8 shrink-0 items-center justify-center rounded-lg',
                        TONES[tone],
                    )}
                >
                    <Icon className="size-4" aria-hidden />
                </span>
            </div>

            <p className="mt-3 text-3xl font-semibold tabular-nums text-ink">
                {formatAngka(value)}
            </p>

            {caption && (
                <Badge variant={tone === 'brand' ? 'neutral' : tone} size="sm" className="mt-3">
                    {caption}
                </Badge>
            )}
        </div>
    );
}

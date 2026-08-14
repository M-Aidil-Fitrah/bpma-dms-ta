import { cn } from '@/lib/cn';
import { type ReactNode } from 'react';

/**
 * Warna diambil dari makna, bukan dari rupa. Pemetaan status aplikasi ke varian
 * di sini terjadi di komponen domain (`DocumentStatusBadge`,
 * `ExtractionStatusBadge`), bukan di halaman — supaya satu status selalu tampil
 * dengan warna yang sama di mana pun ia muncul.
 */
const VARIANTS = {
    neutral: 'bg-surface-sunken text-ink-muted ring-line',
    success: 'bg-success-soft text-success-strong ring-success/20',
    warning: 'bg-warning-soft text-warning-strong ring-warning/20',
    danger: 'bg-danger-soft text-danger-strong ring-danger/20',
    info: 'bg-info-soft text-info-strong ring-info/20',
    brand: 'bg-brand-50 text-brand-700 ring-brand-700/20',
} as const;

const SIZES = {
    sm: 'px-1.5 py-0.5 text-xs',
    md: 'px-2 py-1 text-xs',
} as const;

export interface BadgeProps {
    variant?: keyof typeof VARIANTS;
    size?: keyof typeof SIZES;
    className?: string;
    children: ReactNode;
}

export function Badge({
    variant = 'neutral',
    size = 'md',
    className,
    children,
}: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 whitespace-nowrap rounded-md font-medium ring-1 ring-inset',
                VARIANTS[variant],
                SIZES[size],
                className,
            )}
        >
            {children}
        </span>
    );
}

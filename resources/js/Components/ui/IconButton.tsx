import { cn } from '@/lib/cn';
import { type LucideIcon } from 'lucide-react';
import { forwardRef, type ButtonHTMLAttributes } from 'react';

const VARIANTS = {
    default: 'border border-line bg-surface text-ink-muted hover:bg-surface-sunken hover:text-ink',
    ghost: 'text-ink-muted hover:bg-surface-sunken hover:text-ink',
    danger: 'border border-line bg-surface text-ink-muted hover:bg-danger-soft hover:text-danger hover:border-danger/30',
} as const;

const SIZES = {
    sm: 'size-8 min-h-touch min-w-touch sm:size-8 sm:min-h-0 sm:min-w-0',
    md: 'size-10 min-h-touch min-w-touch sm:size-10 sm:min-h-0 sm:min-w-0',
} as const;

export interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    icon: LucideIcon;
    /** Kelas khusus SVG, misalnya ikon bintang yang perlu terisi penuh. */
    iconClassName?: string;
    /** Wajib — tombol tanpa teks tidak dapat dikenali pembaca layar. */
    label: string;
    variant?: keyof typeof VARIANTS;
    size?: keyof typeof SIZES;
}

export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(
    function IconButton(
        { icon: Icon, iconClassName, label, variant = 'default', size = 'md', className, ...props },
        ref,
    ) {
        return (
            <button
                ref={ref}
                aria-label={label}
                title={label}
                className={cn(
                    'inline-flex shrink-0 items-center justify-center rounded-lg transition-colors',
                    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700',
                    'disabled:cursor-not-allowed disabled:opacity-50',
                    VARIANTS[variant],
                    SIZES[size],
                    className,
                )}
                {...props}
            >
                <Icon className={cn('size-4', iconClassName)} aria-hidden />
            </button>
        );
    },
);

import { cn } from '@/lib/cn';
import { Loader2, type LucideIcon } from 'lucide-react';
import { forwardRef, type ButtonHTMLAttributes } from 'react';

const VARIANTS = {
    primary: 'bg-brand-700 text-white hover:bg-brand-800 focus-visible:outline-brand-700',
    secondary: 'bg-white text-ink border border-line hover:bg-surface-sunken focus-visible:outline-brand-700',
    danger: 'bg-danger text-white hover:bg-danger-strong focus-visible:outline-danger',
    ghost: 'bg-transparent text-ink-muted hover:bg-surface-sunken hover:text-ink focus-visible:outline-brand-700',
} as const;

const SIZES = {
    // `min-h-touch` menjaga target sentuh tetap 44px di layar sentuh, walau
    // secara visual tombolnya terlihat lebih ramping di desktop.
    sm: 'h-9 min-h-touch px-3 text-sm gap-1.5 sm:min-h-0',
    md: 'h-10 min-h-touch px-4 text-sm gap-2 sm:min-h-0',
    lg: 'h-11 min-h-touch px-5 text-base gap-2',
} as const;

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: keyof typeof VARIANTS;
    size?: keyof typeof SIZES;
    loading?: boolean;
    icon?: LucideIcon;
    iconPosition?: 'left' | 'right';
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
    {
        variant = 'primary',
        size = 'md',
        loading = false,
        icon: Icon,
        iconPosition = 'left',
        disabled,
        className,
        children,
        ...props
    },
    ref,
) {
    const isDisabled = disabled === true || loading;

    return (
        <button
            ref={ref}
            disabled={isDisabled}
            // Menahan pengiriman ganda saat sedang memuat: tombol yang terlihat
            // "sedang bekerja" tapi masih bisa diklik adalah penyebab umum data
            // terkirim dua kali.
            aria-busy={loading}
            className={cn(
                'inline-flex items-center justify-center rounded-lg font-medium transition-colors',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
                'disabled:cursor-not-allowed disabled:opacity-50',
                VARIANTS[variant],
                SIZES[size],
                className,
            )}
            {...props}
        >
            {loading && <Loader2 className="size-4 shrink-0 animate-spin" aria-hidden />}
            {!loading && Icon && iconPosition === 'left' && (
                <Icon className="size-4 shrink-0" aria-hidden />
            )}
            {children}
            {!loading && Icon && iconPosition === 'right' && (
                <Icon className="size-4 shrink-0" aria-hidden />
            )}
        </button>
    );
});

import { cn } from '@/lib/cn';
import { type LucideIcon } from 'lucide-react';
import { forwardRef, type InputHTMLAttributes } from 'react';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    /** Ikon di sisi kiri, mis. kaca pembesar pada kolom pencarian. */
    icon?: LucideIcon;
    invalid?: boolean;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
    { icon: Icon, invalid = false, className, ...props },
    ref,
) {
    return (
        <div className="relative">
            {Icon && (
                <Icon
                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-subtle"
                    aria-hidden
                />
            )}

            <input
                ref={ref}
                className={cn(
                    'block w-full rounded-lg border bg-surface text-sm text-ink shadow-none',
                    'h-10 min-h-touch px-3 sm:min-h-0',
                    'placeholder:text-ink-subtle',
                    'focus:border-brand-700 focus:ring-1 focus:ring-brand-700',
                    'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-muted',
                    Icon && 'pl-9',
                    invalid ? 'border-danger focus:border-danger focus:ring-danger' : 'border-line',
                    className,
                )}
                {...props}
            />
        </div>
    );
});

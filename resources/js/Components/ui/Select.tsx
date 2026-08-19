import { cn } from '@/lib/cn';
import { ChevronDown } from 'lucide-react';
import { forwardRef, type SelectHTMLAttributes } from 'react';

export interface SelectOption {
    value: string | number;
    label: string;
}

export interface SelectProps extends Omit<SelectHTMLAttributes<HTMLSelectElement>, 'children'> {
    options: readonly SelectOption[];
    /** Pilihan kosong di posisi teratas, mis. "Semua kategori". */
    placeholder?: string;
    invalid?: boolean;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
    { options, placeholder, invalid = false, className, ...props },
    ref,
) {
    return (
        <div className="relative">
            <select
                ref={ref}
                className={cn(
                    'block w-full appearance-none rounded-lg border bg-surface text-sm text-ink',
                    'h-10 min-h-touch pl-3 pr-9 sm:min-h-0',
                    'focus:border-brand-700 focus:ring-1 focus:ring-brand-700',
                    'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-muted',
                    invalid ? 'border-danger focus:border-danger focus:ring-danger' : 'border-line',
                    className,
                )}
                {...props}
            >
                {placeholder && <option value="">{placeholder}</option>}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>

            <ChevronDown
                className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-ink-subtle"
                aria-hidden
            />
        </div>
    );
});

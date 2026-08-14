import { cn } from '@/lib/cn';
import { useId, type ReactNode } from 'react';

export interface FieldProps {
    label: string;
    /** Penjelasan tambahan di bawah label, sebelum kendali. */
    hint?: string;
    error?: string;
    required?: boolean;
    className?: string;
    /**
     * Menerima fungsi supaya `id` dan atribut aksesibilitas dapat diteruskan ke
     * kendali di dalamnya tanpa pemanggil perlu mengurusnya sendiri.
     */
    children: (props: {
        id: string;
        'aria-describedby': string | undefined;
        'aria-invalid': boolean;
    }) => ReactNode;
}

/**
 * Pembungkus label, kendali, dan pesan galat.
 *
 * Ini SATU-SATUNYA tempat tata letak label diatur. Menuliskan `<label>` langsung
 * di halaman akan membuat jarak dan ukuran huruf berbeda-beda antar formulir,
 * dan mudah lupa menghubungkan pesan galat ke kendalinya lewat
 * `aria-describedby`.
 */
export function Field({
    label,
    hint,
    error,
    required = false,
    className,
    children,
}: FieldProps) {
    const id = useId();
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cn('space-y-1.5', className)}>
            <label htmlFor={id} className="block text-sm font-medium text-ink">
                {label}
                {required && (
                    <span className="ml-1 text-danger" aria-hidden>
                        *
                    </span>
                )}
            </label>

            {hint && (
                <p id={hintId} className="text-sm text-ink-muted">
                    {hint}
                </p>
            )}

            {children({
                id,
                'aria-describedby': describedBy,
                'aria-invalid': Boolean(error),
            })}

            {error && (
                <p id={errorId} role="alert" className="text-sm text-danger">
                    {error}
                </p>
            )}
        </div>
    );
}

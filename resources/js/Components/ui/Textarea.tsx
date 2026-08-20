import { cn } from '@/lib/cn';
import { forwardRef, type TextareaHTMLAttributes } from 'react';

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    invalid?: boolean;
}

/** Input multi-baris dengan perilaku visual dan aksesibilitas setara `Input`. */
export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
    { invalid = false, className, ...props },
    ref,
) {
    return (
        <textarea
            ref={ref}
            className={cn(
                'block w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink',
                'placeholder:text-ink-subtle focus:border-brand-700 focus:ring-1 focus:ring-brand-700',
                'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-muted',
                invalid ? 'border-danger focus:border-danger focus:ring-danger' : 'border-line',
                className,
            )}
            {...props}
        />
    );
});

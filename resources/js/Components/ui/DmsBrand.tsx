import { Logo } from '@/Components/ui/Logo';
import { cn } from '@/lib/cn';

interface DmsBrandProps {
    /** Dipakai saat identitas ditempatkan pada latar gelap. */
    onDark?: boolean;
    className?: string;
}

/** Identitas aplikasi yang melengkapi wordmark resmi BPMA. */
export function DmsBrand({ onDark = false, className }: DmsBrandProps) {
    return (
        <div className={cn('inline-flex flex-col items-center text-center', className)}>
            {onDark ? (
                <div className="rounded-lg bg-surface px-3 py-2 shadow-sm">
                    <Logo size="lg" />
                </div>
            ) : (
                <Logo size="lg" />
            )}
            <p
                className={cn(
                    'mt-1 text-[10px] font-semibold uppercase leading-none tracking-[0.14em]',
                    onDark ? 'text-brand-100' : 'text-ink-muted',
                )}
            >
                Data Management System
            </p>
        </div>
    );
}

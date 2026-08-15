import { cn } from '@/lib/cn';
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';
import { type ReactNode } from 'react';

const VARIANTS = {
    info: { wrapper: 'bg-info-soft text-info-strong', icon: Info },
    success: { wrapper: 'bg-success-soft text-success-strong', icon: CheckCircle2 },
    warning: { wrapper: 'bg-warning-soft text-warning-strong', icon: AlertTriangle },
    danger: { wrapper: 'bg-danger-soft text-danger-strong', icon: XCircle },
} as const;

export interface AlertProps {
    variant?: keyof typeof VARIANTS;
    title?: string;
    className?: string;
    action?: ReactNode;
    children: ReactNode;
}

export function Alert({
    variant = 'info',
    title,
    className,
    action,
    children,
}: AlertProps) {
    const { wrapper, icon: Icon } = VARIANTS[variant];

    return (
        <div
            // `status` bukan `alert`: pembaca layar mengumumkannya tanpa
            // menyela apa yang sedang dibaca pengguna.
            role="status"
            className={cn(
                'flex flex-col gap-3 rounded-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between',
                wrapper,
                className,
            )}
        >
            <div className="flex gap-3">
                <Icon className="mt-0.5 size-5 shrink-0" aria-hidden />
                <div className="text-sm">
                    {title && <p className="font-semibold">{title}</p>}
                    <div className={cn(title && 'mt-0.5')}>{children}</div>
                </div>
            </div>

            {action && <div className="shrink-0 sm:ml-4">{action}</div>}
        </div>
    );
}

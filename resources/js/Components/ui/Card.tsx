import { cn } from '@/lib/cn';
import { type ReactNode } from 'react';

export interface CardProps {
    className?: string;
    children: ReactNode;
}

export function Card({ className, children }: CardProps) {
    return (
        <div
            className={cn(
                'rounded-card border border-line bg-surface shadow-card',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function CardHeader({ className, children }: CardProps) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5 sm:py-4',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function CardTitle({ className, children }: CardProps) {
    return (
        <h2 className={cn('text-base font-semibold text-ink', className)}>{children}</h2>
    );
}

export function CardBody({ className, children }: CardProps) {
    return <div className={cn('p-4 sm:p-5', className)}>{children}</div>;
}

export function CardFooter({ className, children }: CardProps) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 sm:px-5',
                className,
            )}
        >
            {children}
        </div>
    );
}

import { cn } from '@/lib/cn';

export interface SkeletonProps {
    className?: string;
}

/**
 * Penanda tempat saat data sedang dimuat.
 *
 * Dipakai alih-alih pemutar berputar untuk daftar dan kartu, karena bentuknya
 * sudah menyerupai isi yang akan datang — tata letak tidak melompat saat data
 * tiba.
 */
export function Skeleton({ className }: SkeletonProps) {
    return (
        <div
            aria-hidden
            className={cn('animate-pulse rounded-md bg-surface-sunken', className)}
        />
    );
}

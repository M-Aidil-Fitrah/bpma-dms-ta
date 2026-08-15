import { cn } from '@/lib/cn';

const SIZES = {
    sm: 'size-7 text-xs',
    md: 'size-9 text-sm',
    lg: 'size-11 text-base',
} as const;

export interface AvatarProps {
    /** Inisial, mis. "FH". Dihitung backend di `AuthUserData`. */
    initials: string;
    name?: string;
    size?: keyof typeof SIZES;
    className?: string;
}

/**
 * Avatar berbasis inisial, tanpa gambar.
 *
 * Prototype ini tidak menyimpan foto pengguna, jadi tidak ada berkas gambar
 * yang perlu dimuat — sekaligus menghindari permintaan jaringan tambahan untuk
 * tiap baris pada daftar yang panjang.
 */
export function Avatar({ initials, name, size = 'md', className }: AvatarProps) {
    return (
        <span
            title={name}
            aria-hidden={name === undefined}
            aria-label={name}
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-full',
                'bg-brand-700 font-semibold uppercase text-white',
                SIZES[size],
                className,
            )}
        >
            {initials}
        </span>
    );
}

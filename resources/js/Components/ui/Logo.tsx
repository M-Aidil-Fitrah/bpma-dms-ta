import { type ImgHTMLAttributes } from 'react';

/**
 * Rasio asli berkas logo BPMA: 638 x 168 px.
 *
 * Tinggi yang dipakai tata letak ditetapkan lewat `size`, lebarnya dibiarkan
 * mengikuti rasio secara otomatis. Dengan begitu, mengganti berkas logo tidak
 * menuntut penyesuaian lebar di mana pun — selama `viewBox`-nya dipertahankan.
 */
const LOGO_RATIO = 638 / 168;

const LOGO_SRC = '/images/logo-bpma.svg';

const SIZE_HEIGHT = {
    sm: 24,
    md: 32,
    lg: 44,
} as const;

interface LogoProps extends Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'alt' | 'width' | 'height'> {
    /** Tinggi logo. Lebar mengikuti rasio asli secara otomatis. */
    size?: keyof typeof SIZE_HEIGHT;
}

/**
 * Logo penuh BPMA (lambang + wordmark).
 *
 * Memakai SVG supaya tetap tajam di layar high-DPI — `PRD.md` §9, NFR
 * Ketajaman Aset Visual.
 */
export function Logo({ size = 'md', ...props }: LogoProps) {
    const height = SIZE_HEIGHT[size];

    return (
        <img
            {...props}
            src={LOGO_SRC}
            alt="BPMA"
            height={height}
            width={Math.round(height * LOGO_RATIO)}
            style={{ height, width: 'auto', ...props.style }}
        />
    );
}

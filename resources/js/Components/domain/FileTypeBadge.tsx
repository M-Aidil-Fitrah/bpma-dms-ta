import { Badge } from '@/Components/ui/Badge';
import {
    File,
    FileArchive,
    FileAudio,
    FileSpreadsheet,
    FileText,
    FileType,
    FileVideo,
    Image,
    Presentation,
    type LucideIcon,
} from 'lucide-react';

interface TipeBerkas {
    label: string;
    variant: 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';
    icon: LucideIcon;
}

/**
 * Warna mengikuti kebiasaan yang sudah dikenal orang dari aplikasi perkantoran
 * — PDF merah, Word biru, Excel hijau, PowerPoint jingga. Memakai warna yang
 * sudah tertanam di kepala pengguna membuat tipe berkas terbaca sekilas tanpa
 * perlu membaca tulisannya.
 */
const BAWAAN: TipeBerkas = { label: 'Berkas', variant: 'neutral', icon: File };

function petakan(mime: string): TipeBerkas {
    if (mime === 'application/pdf') {
        return { label: 'PDF', variant: 'danger', icon: FileText };
    }
    if (mime.includes('wordprocessingml') || mime === 'application/msword') {
        return { label: 'Word', variant: 'info', icon: FileText };
    }
    if (mime.includes('spreadsheetml') || mime === 'application/vnd.ms-excel') {
        return { label: 'Excel', variant: 'success', icon: FileSpreadsheet };
    }
    if (mime.includes('presentationml') || mime === 'application/vnd.ms-powerpoint') {
        return { label: 'PPT', variant: 'warning', icon: Presentation };
    }
    if (mime.startsWith('image/')) {
        return { label: 'Gambar', variant: 'brand', icon: Image };
    }
    if (mime.startsWith('video/')) {
        return { label: 'Video', variant: 'brand', icon: FileVideo };
    }
    if (mime.startsWith('audio/')) {
        return { label: 'Audio', variant: 'brand', icon: FileAudio };
    }
    if (mime === 'text/plain') {
        return { label: 'Teks', variant: 'neutral', icon: FileType };
    }
    if (mime.includes('zip') || mime.includes('compressed') || mime.includes('tar')) {
        // Bukan "Arsip". Di sistem manajemen dokumen, kata itu jauh lebih kuat
        // berarti "dokumen lama yang disimpan" ketimbang "berkas terkompresi" —
        // dua hal yang sama sekali berbeda, dan salah satunya adalah fitur
        // aplikasi ini sendiri.
        return { label: 'ZIP', variant: 'warning', icon: FileArchive };
    }

    return BAWAAN;
}

export interface FileTypeBadgeProps {
    mime: string;
    size?: 'sm' | 'md';
}

export function FileTypeBadge({ mime, size = 'sm' }: FileTypeBadgeProps) {
    const { label, variant, icon: Icon } = petakan(mime);

    return (
        <Badge variant={variant} size={size}>
            <Icon className="size-3 shrink-0" aria-hidden />
            {label}
        </Badge>
    );
}

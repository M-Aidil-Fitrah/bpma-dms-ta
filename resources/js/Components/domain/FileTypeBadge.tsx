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
import type { TFunction } from 'i18next';
import { useTranslation } from 'react-i18next';

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
function petakan(mime: string, t: TFunction): TipeBerkas {
    const bawaan: TipeBerkas = { label: t('documentBrowse:fileType.berkas'), variant: 'neutral', icon: File };

    if (mime === 'application/pdf') {
        return { label: t('documentBrowse:fileType.pdf'), variant: 'danger', icon: FileText };
    }
    if (mime.includes('wordprocessingml') || mime.includes('msword')) {
        return { label: t('documentBrowse:fileType.word'), variant: 'info', icon: FileText };
    }
    if (mime.includes('spreadsheetml') || mime.includes('ms-excel')) {
        return { label: t('documentBrowse:fileType.excel'), variant: 'success', icon: FileSpreadsheet };
    }
    if (mime.includes('presentationml') || mime.includes('ms-powerpoint')) {
        return { label: t('documentBrowse:fileType.ppt'), variant: 'warning', icon: Presentation };
    }
    if (mime.startsWith('image/')) {
        return { label: t('documentBrowse:fileType.gambar'), variant: 'brand', icon: Image };
    }
    if (mime.startsWith('video/')) {
        return { label: t('documentBrowse:fileType.video'), variant: 'brand', icon: FileVideo };
    }
    if (mime.startsWith('audio/')) {
        return { label: t('documentBrowse:fileType.audio'), variant: 'brand', icon: FileAudio };
    }
    if (mime === 'text/plain') {
        return { label: t('documentBrowse:fileType.teks'), variant: 'neutral', icon: FileType };
    }
    if (mime.includes('zip') || mime.includes('compressed') || mime.includes('tar')) {
        // Bukan "Arsip". Di sistem manajemen dokumen, kata itu jauh lebih kuat
        // berarti "dokumen lama yang disimpan" ketimbang "berkas terkompresi" —
        // dua hal yang sama sekali berbeda, dan salah satunya adalah fitur
        // aplikasi ini sendiri.
        return { label: t('documentBrowse:fileType.zip'), variant: 'warning', icon: FileArchive };
    }

    return bawaan;
}

export interface FileTypeBadgeProps {
    mime: string;
    size?: 'sm' | 'md';
}

export function FileTypeBadge({ mime, size = 'sm' }: FileTypeBadgeProps) {
    const { t } = useTranslation('documentBrowse');
    const { label, variant, icon: Icon } = petakan(mime, t);

    return (
        <Badge variant={variant} size={size}>
            <Icon className="size-3 shrink-0" aria-hidden />
            {label}
        </Badge>
    );
}

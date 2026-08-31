import { useTampakDiLayar } from '@/hooks/useTampakDiLayar';
import { cn } from '@/lib/cn';
import { jenisBerkas } from '@/lib/fileType';
import { gambarHalamanPertama } from '@/lib/pdf';
import {
    File,
    FileArchive,
    FileAudio,
    FileSpreadsheet,
    FileText,
    FileType,
    Loader2,
    Presentation,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface DocumentThumbnailProps {
    id: number;
    mime: string;
    namaBerkas?: string;
    judul: string;
    tersedia: boolean;
    className?: string;
}

/**
 * Gambar mini isi dokumen untuk kartu pada tampilan grid.
 *
 * Bila server sudah menyiapkan gambar mini, kartu memakainya untuk semua tipe
 * dokumen yang dapat divisualkan. Jika perkakas server gagal atau tipe memang
 * tidak punya wujud visual, perilaku lama menjadi fallback yang aman.
 *
 * Seluruh pemuatan ditunda sampai kartunya masuk ke area pandang. Tanpa itu,
 * membuka halaman grid berarti mengunduh dua puluh berkas sekaligus — sebagian
 * besar untuk kartu yang bahkan belum tergulir ke layar.
 */
export function DocumentThumbnail({ id, mime, namaBerkas, judul, tersedia, className }: DocumentThumbnailProps) {
    const { ref, tampak } = useTampakDiLayar<HTMLDivElement>();
    const url = `/documents/${id}/preview`;

    return (
        <div
            ref={ref}
            className={cn(
                'flex h-32 items-center justify-center overflow-hidden rounded-t-card bg-surface-sunken',
                className,
            )}
        >
            {tampak ? (
                <Isi id={id} mime={mime} namaBerkas={namaBerkas} url={url} judul={judul} thumbnailTersedia={tersedia} />
            ) : (
                <IkonBerkas mime={mime} namaBerkas={namaBerkas} />
            )}
        </div>
    );
}

function Isi({
    id,
    mime,
    namaBerkas,
    url,
    judul,
    thumbnailTersedia,
}: {
    id: number;
    mime: string;
    namaBerkas?: string;
    url: string;
    judul: string;
    thumbnailTersedia: boolean;
}) {
    const [thumbnailGagal, setThumbnailGagal] = useState(false);

    if (thumbnailTersedia && !thumbnailGagal) {
        return (
            <img
                src={`/documents/${id}/thumbnail`}
                alt={`Pratinjau ${judul}`}
                loading="lazy"
                decoding="async"
                className="size-full object-cover"
                onError={() => setThumbnailGagal(true)}
            />
        );
    }

    if (mime.startsWith('image/')) {
        return (
            <img
                src={url}
                alt={`Pratinjau ${judul}`}
                loading="lazy"
                decoding="async"
                className="size-full object-cover"
            />
        );
    }

    if (mime.startsWith('video/')) {
        return (
            // `preload="metadata"` cukup untuk memunculkan bingkai pertama tanpa
            // mengunduh seluruh berkas video.
            <video
                src={url}
                preload="metadata"
                muted
                playsInline
                aria-label={`Pratinjau ${judul}`}
                className="size-full object-cover"
            />
        );
    }

    if (mime === 'application/pdf') {
        return <PratinjauPdf url={url} judul={judul} />;
    }

    return <IkonBerkas mime={mime} namaBerkas={namaBerkas} />;
}

function PratinjauPdf({ url, judul }: { url: string; judul: string }) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [keadaan, setKeadaan] = useState<'memuat' | 'siap' | 'gagal'>('memuat');

    useEffect(() => {
        let dibatalkan = false;

        async function render() {
            if (canvasRef.current === null) return;

            try {
                await gambarHalamanPertama(url, canvasRef.current, 320);
                if (!dibatalkan) setKeadaan('siap');
            } catch {
                // PDF rusak atau terproteksi bukan kegagalan aplikasi. Kartunya
                // cukup kembali menampilkan ikon — berkasnya tetap dapat
                // diunduh seperti biasa.
                if (!dibatalkan) setKeadaan('gagal');
            }
        }

        void render();

        return () => {
            dibatalkan = true;
        };
    }, [url]);

    if (keadaan === 'gagal') {
        return <IkonBerkas mime="application/pdf" />;
    }

    return (
        <div className="relative size-full">
            {keadaan === 'memuat' && (
                <span className="absolute inset-0 flex items-center justify-center">
                    <Loader2 className="size-5 animate-spin text-ink-subtle" aria-hidden />
                </span>
            )}

            <canvas
                ref={canvasRef}
                aria-label={`Halaman pertama ${judul}`}
                className={cn(
                    'size-full object-cover object-top transition-opacity',
                    keadaan === 'siap' ? 'opacity-100' : 'opacity-0',
                )}
            />
        </div>
    );
}

function IkonBerkas({ mime, namaBerkas }: { mime: string; namaBerkas?: string }) {
    const { icon: Icon, warna } = petakan(mime, namaBerkas);

    return <Icon className={cn('size-10', warna)} aria-hidden />;
}

function petakan(mime: string, namaBerkas?: string): { icon: LucideIcon; warna: string } {
    const jenis = jenisBerkas(mime, namaBerkas);

    if (jenis === 'pdf') return { icon: FileText, warna: 'text-danger' };
    if (jenis === 'word') {
        return { icon: FileText, warna: 'text-info' };
    }
    if (jenis === 'excel') {
        return { icon: FileSpreadsheet, warna: 'text-success' };
    }
    if (jenis === 'ppt') {
        return { icon: Presentation, warna: 'text-warning' };
    }
    if (jenis === 'audio') return { icon: FileAudio, warna: 'text-brand-600' };
    if (jenis === 'teks') return { icon: FileType, warna: 'text-ink-subtle' };
    if (jenis === 'zip') {
        return { icon: FileArchive, warna: 'text-warning' };
    }

    return { icon: File, warna: 'text-ink-subtle' };
}

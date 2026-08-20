import { Button } from '@/Components/ui/Button';
import { IconButton } from '@/Components/ui/IconButton';
import { formatUkuranBerkas } from '@/lib/format';
import { Download, FileQuestion, Loader2, Maximize, Minimize, ZoomIn, ZoomOut } from 'lucide-react';
import { lazy, Suspense, useEffect, useRef, useState, type PointerEvent as ReactPointerEvent, type ReactNode } from 'react';

/**
 * pdf.js dimuat hanya saat berkasnya memang PDF.
 *
 * Pustakanya sekitar satu megabyte — membiarkannya ikut di bundel berarti
 * pengguna yang membuka dokumen Word pun harus mengunduhnya lebih dulu.
 */
const PdfViewer = lazy(() =>
    import('@/Components/domain/PdfViewer').then((m) => ({ default: m.PdfViewer })),
);

export interface DocumentPreviewProps {
    dokumen: App.Data.DocumentDetailData;
    /**
     * Job konversi Office masih mungkin berjalan (dalam jendela waktu wajar
     * sejak unggah) — ditampilkan alih-alih langsung lompat ke fallback teks
     * atau unduh, karena tab ini sedang di-polling dan akan otomatis berganti
     * begitu `preview_tersedia` jadi true.
     */
    sedangMenyiapkanPratinjau?: boolean;
}

/**
 * Menampilkan isi dokumen sesuai tipe berkasnya (FR-09b).
 *
 * Dokumen Office memakai PDF hasil konversi bila server berhasil membuatnya.
 * Bila tidak, panel teks hasil ekstraksi tetap menjadi fallback yang lebih
 * berguna daripada ikon kosong.
 */
export function DocumentPreview({ dokumen, sedangMenyiapkanPratinjau = false }: DocumentPreviewProps) {
    const url = `/documents/${dokumen.id}/preview`;
    const mime = dokumen.tipe_berkas;

    return (
        <BingkaiPratinjau>
            {({ layarPenuh, onUbahLayarPenuh }) => {
                if (dokumen.preview_tersedia || mime === 'application/pdf') {
                    return (
                        <Suspense fallback={<Memuat />}>
                            <PdfViewer url={url} judul={dokumen.judul} layarPenuh={layarPenuh} onUbahLayarPenuh={onUbahLayarPenuh} />
                        </Suspense>
                    );
                }

                if (mime.startsWith('image/')) {
                    return <PratinjauGambar url={url} dokumen={dokumen} layarPenuh={layarPenuh} onUbahLayarPenuh={onUbahLayarPenuh} />;
                }

                if (mime.startsWith('video/')) {
                    return <PratinjauVideo url={url} />;
                }

                if (mime.startsWith('audio/')) {
                    return <PratinjauAudio url={url} layarPenuh={layarPenuh} onUbahLayarPenuh={onUbahLayarPenuh} />;
                }

                if (sedangMenyiapkanPratinjau) return <MenyiapkanPratinjau />;
                if (dokumen.isi_teks) return <PanelTeks teks={dokumen.isi_teks} mime={mime} layarPenuh={layarPenuh} onUbahLayarPenuh={onUbahLayarPenuh} />;

                return <TanpaPratinjau dokumen={dokumen} />;
            }}
        </BingkaiPratinjau>
    );
}

function BingkaiPratinjau({ children }: { children: (opsi: KendaliLayarPenuh) => ReactNode }) {
    const bingkaiRef = useRef<HTMLDivElement>(null);
    const [layarPenuh, setLayarPenuh] = useState(false);

    useEffect(() => {
        function perbarui() {
            setLayarPenuh(document.fullscreenElement === bingkaiRef.current);
        }

        document.addEventListener('fullscreenchange', perbarui);

        return () => document.removeEventListener('fullscreenchange', perbarui);
    }, []);

    async function ubahLayarPenuh() {
        try {
            if (document.fullscreenElement === bingkaiRef.current) {
                await document.exitFullscreen();
            } else {
                await bingkaiRef.current?.requestFullscreen();
            }
        } catch {
            // Browser dapat menolak layar penuh, misalnya saat iframe atau
            // kebijakan perangkat tidak mengizinkannya.
        }
    }

    return <div ref={bingkaiRef} className="h-full bg-surface">{children({ layarPenuh, onUbahLayarPenuh: () => void ubahLayarPenuh() })}</div>;
}

type KendaliLayarPenuh = { layarPenuh: boolean; onUbahLayarPenuh: () => void };

function MenyiapkanPratinjau() {
    return (
        <div className="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
            <Loader2 className="size-6 animate-spin text-ink-subtle" aria-hidden />
            <p className="text-sm text-ink-muted">Pratinjau sedang disiapkan di latar belakang…</p>
        </div>
    );
}

/**
 * Sebagian tipe gambar (mis. HEIC/HEIF foto kamera iPhone) lolos pengecekan
 * awalan `image/` di sini tapi tidak ada di daftar-boleh inline server
 * (`PenyajianBerkas::AMAN_INLINE`) — server menyajikannya sebagai unduhan,
 * bukan gambar. Tanpa `onError`, itu tampil sebagai ikon gambar rusak yang
 * diam selamanya, bukan fallback "Unduh Berkas" yang sudah ada untuk tipe
 * tak-terdukung lain.
 */
function PratinjauGambar({ url, dokumen, layarPenuh, onUbahLayarPenuh }: { url: string; dokumen: App.Data.DocumentDetailData } & KendaliLayarPenuh) {
    const [gagal, setGagal] = useState(false);
    const [skala, setSkala] = useState(1);
    const areaGambar = useRef<HTMLDivElement>(null);
    const geser = useRef<{ pointerId: number; x: number; y: number; kiri: number; atas: number } | null>(null);

    function ubahSkala(nilai: number) {
        setSkala(nilai);

        if (nilai <= 1) {
            requestAnimationFrame(() => areaGambar.current?.scrollTo({ left: 0, top: 0 }));
        }
    }

    function mulaiGeser(event: ReactPointerEvent<HTMLDivElement>) {
        if (skala <= 1) return;

        const area = event.currentTarget;
        area.setPointerCapture(event.pointerId);
        geser.current = {
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY,
            kiri: area.scrollLeft,
            atas: area.scrollTop,
        };
    }

    function geserGambar(event: ReactPointerEvent<HTMLDivElement>) {
        const mulai = geser.current;
        if (!mulai || mulai.pointerId !== event.pointerId) return;

        event.currentTarget.scrollTo({
            left: mulai.kiri - (event.clientX - mulai.x),
            top: mulai.atas - (event.clientY - mulai.y),
        });
    }

    function selesaiGeser(event: ReactPointerEvent<HTMLDivElement>) {
        if (geser.current?.pointerId !== event.pointerId) return;

        if (event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.releasePointerCapture(event.pointerId);
        }
        geser.current = null;
    }

    if (gagal) {
        return <TanpaPratinjau dokumen={dokumen} />;
    }

    return (
        <div className="flex h-full min-h-0 flex-col">
            <KendaliPratinjau skala={skala} onUbahSkala={ubahSkala} layarPenuh={layarPenuh} onUbahLayarPenuh={onUbahLayarPenuh} />
            <div
                ref={areaGambar}
                className={`flex min-h-0 flex-1 items-center justify-center overflow-auto bg-surface-sunken p-4 ${skala > 1 ? 'cursor-grab touch-none active:cursor-grabbing' : ''}`}
                onPointerDown={mulaiGeser}
                onPointerMove={geserGambar}
                onPointerUp={selesaiGeser}
                onPointerCancel={selesaiGeser}
            >
                <img src={url} alt={dokumen.judul} draggable={false} className="max-w-none rounded shadow-card" style={{ width: `${skala * 100}%` }} onError={() => setGagal(true)} />
            </div>
        </div>
    );
}

function PratinjauVideo({ url }: { url: string }) {
    return (
        <div className="flex h-full min-h-0 items-center justify-center bg-ink p-4">
            <video src={url} controls className="max-h-full w-full rounded">Peramban Anda tidak mendukung pemutaran video.</video>
        </div>
    );
}

function PratinjauAudio({ url, layarPenuh, onUbahLayarPenuh }: { url: string } & KendaliLayarPenuh) {
    return (
        <div className="flex h-full min-h-0 flex-col">
            <KendaliPratinjau layarPenuh={layarPenuh} onUbahLayarPenuh={onUbahLayarPenuh} />
            <div className="flex min-h-0 flex-1 items-center justify-center p-8">
                <audio src={url} controls className="w-full max-w-md">Peramban Anda tidak mendukung pemutaran audio.</audio>
            </div>
        </div>
    );
}

function PanelTeks({ teks, mime, layarPenuh, onUbahLayarPenuh }: { teks: string; mime: string } & KendaliLayarPenuh) {
    const dariEkstraksi = mime !== 'text/plain';

    return (
        <div className="flex h-full min-h-0 flex-col">
            <KendaliPratinjau layarPenuh={layarPenuh} onUbahLayarPenuh={onUbahLayarPenuh} />
            {dariEkstraksi && (
                <p className="border-b border-line bg-warning-soft px-4 py-2 text-xs text-warning-strong">
                    Yang ditampilkan adalah isi teks hasil pembacaan berkas, bukan tata
                    letak aslinya. Unduh berkas untuk melihat format sesungguhnya.
                </p>
            )}

            <div className="flex-1 overflow-auto bg-surface p-5">
                <pre className="whitespace-pre-wrap break-words font-mono text-sm leading-relaxed text-ink">
                    {teks}
                </pre>
            </div>
        </div>
    );
}

function KendaliPratinjau({ skala, onUbahSkala, layarPenuh, onUbahLayarPenuh }: Partial<{ skala: number; onUbahSkala: (skala: number) => void }> & KendaliLayarPenuh) {
    return (
        <div className="flex items-center justify-end gap-1 border-b border-line bg-surface px-3 py-2">
            {skala !== undefined && onUbahSkala && (
                <>
                    <IconButton icon={ZoomOut} label="Perkecil" size="sm" disabled={skala <= 0.5} onClick={() => onUbahSkala(Math.max(0.5, skala - 0.25))} />
                    <span className="w-12 text-center font-mono text-xs text-ink-muted">{Math.round(skala * 100)}%</span>
                    <IconButton icon={ZoomIn} label="Perbesar" size="sm" disabled={skala >= 3} onClick={() => onUbahSkala(Math.min(3, skala + 0.25))} />
                </>
            )}
            <IconButton icon={layarPenuh ? Minimize : Maximize} label={layarPenuh ? 'Keluar dari layar penuh' : 'Layar penuh'} size="sm" onClick={onUbahLayarPenuh} />
        </div>
    );
}

function TanpaPratinjau({ dokumen }: { dokumen: App.Data.DocumentDetailData }) {
    return (
        <div className="flex h-full flex-col items-center justify-center gap-4 p-8 text-center">
            <span className="inline-flex size-14 items-center justify-center rounded-full bg-surface-sunken text-ink-subtle">
                <FileQuestion className="size-7" aria-hidden />
            </span>

            <div>
                <p className="text-sm font-medium text-ink">
                    Tipe berkas ini tidak dapat ditampilkan di peramban
                </p>
                <p className="mt-1 text-sm text-ink-muted">
                    {dokumen.nama_berkas} · {formatUkuranBerkas(dokumen.ukuran_berkas)}
                </p>
            </div>

            <a href={`/documents/${dokumen.id}/file`} download>
                <Button icon={Download}>Unduh Berkas</Button>
            </a>
        </div>
    );
}

function Memuat() {
    return (
        <div className="flex h-full items-center justify-center">
            <span className="text-sm text-ink-muted">Menyiapkan pratinjau…</span>
        </div>
    );
}

import { Button } from '@/Components/ui/Button';
import { IconButton } from '@/Components/ui/IconButton';
import { muatPdfJs } from '@/lib/pdf';
import type { PDFDocumentLoadingTask, PDFDocumentProxy } from 'pdfjs-dist';
import { ChevronLeft, ChevronRight, Loader2, ZoomIn, ZoomOut } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

export interface PdfViewerProps {
    url: string;
    judul: string;
}

const SKALA_MIN = 0.5;
const SKALA_MAKS = 3;
const LANGKAH_SKALA = 0.25;

/**
 * Penampil PDF berbasis pdf.js.
 *
 * Dipakai alih-alih `<embed>` atau `<iframe>` bawaan peramban karena keduanya
 * berperilaku berbeda-beda: sebagian peramban justru mengunduh berkas alih-alih
 * menampilkannya, tergantung setelan pengguna. pdf.js memberi hasil yang sama
 * di semua peramban sekaligus kendali halaman dan perbesaran.
 */
export function PdfViewer({ url, judul }: PdfViewerProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const dokumenRef = useRef<PDFDocumentProxy | null>(null);
    // Tugas pemuatannya yang ditahan, bukan dokumennya: sejak pdf.js v4
    // `destroy()` berada di `PDFDocumentLoadingTask`, dan itulah yang
    // melepaskan worker beserta buffer berkasnya.
    const tugasRef = useRef<PDFDocumentLoadingTask | null>(null);
    // Menahan tugas render yang sedang berjalan: pdf.js menolak dua permintaan
    // render pada kanvas yang sama, dan menekan "berikutnya" dua kali cepat
    // mudah memicunya.
    const renderRef = useRef<{ cancel: () => void } | null>(null);

    const [halaman, setHalaman] = useState(1);
    const [jumlahHalaman, setJumlahHalaman] = useState(0);
    const [skala, setSkala] = useState(1.2);
    const [keadaan, setKeadaan] = useState<'memuat' | 'siap' | 'gagal'>('memuat');

    // -- Memuat dokumen -------------------------------------------------------
    useEffect(() => {
        let dibatalkan = false;

        async function muat() {
            try {
                const pdfjs = await muatPdfJs();
                const tugas = pdfjs.getDocument({ url });
                tugasRef.current = tugas;
                const dokumen = await tugas.promise;

                if (dibatalkan) {
                    await tugas.destroy();

                    return;
                }

                dokumenRef.current = dokumen;
                setJumlahHalaman(dokumen.numPages);
                setKeadaan('siap');
            } catch {
                if (!dibatalkan) setKeadaan('gagal');
            }
        }

        void muat();

        return () => {
            dibatalkan = true;
            void tugasRef.current?.destroy();
            tugasRef.current = null;
            dokumenRef.current = null;
        };
    }, [url]);

    // -- Menggambar halaman ---------------------------------------------------
    const gambar = useCallback(async () => {
        const dokumen = dokumenRef.current;
        const canvas = canvasRef.current;
        if (dokumen === null || canvas === null) return;

        renderRef.current?.cancel();

        const page = await dokumen.getPage(halaman);
        const viewport = page.getViewport({ scale: skala });
        const konteks = canvas.getContext('2d');
        if (konteks === null) return;

        const rasio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.floor(viewport.width * rasio);
        canvas.height = Math.floor(viewport.height * rasio);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;
        konteks.setTransform(rasio, 0, 0, rasio, 0, 0);

        const tugas = page.render({ canvas, canvasContext: konteks, viewport });
        renderRef.current = tugas;

        try {
            await tugas.promise;
        } catch {
            // Render yang dibatalkan karena pengguna berpindah halaman bukan
            // kegagalan — diabaikan tanpa mengubah keadaan.
        }
    }, [halaman, skala]);

    useEffect(() => {
        if (keadaan === 'siap') void gambar();
    }, [keadaan, gambar]);

    if (keadaan === 'gagal') {
        return (
            <div className="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
                <p className="text-sm text-ink-muted">
                    Berkas PDF ini tidak dapat ditampilkan di peramban.
                </p>
                <Button variant="secondary" onClick={() => window.location.assign(url)}>
                    Unduh berkas
                </Button>
            </div>
        );
    }

    return (
        <div className="flex h-full flex-col">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-3 py-2">
                <div className="flex items-center gap-1">
                    <IconButton
                        icon={ChevronLeft}
                        label="Halaman sebelumnya"
                        size="sm"
                        disabled={halaman <= 1}
                        onClick={() => setHalaman((n) => Math.max(1, n - 1))}
                    />
                    <span className="px-2 font-mono text-xs text-ink-muted">
                        {halaman} / {jumlahHalaman || '—'}
                    </span>
                    <IconButton
                        icon={ChevronRight}
                        label="Halaman berikutnya"
                        size="sm"
                        disabled={halaman >= jumlahHalaman}
                        onClick={() => setHalaman((n) => Math.min(jumlahHalaman, n + 1))}
                    />
                </div>

                <div className="flex items-center gap-1">
                    <IconButton
                        icon={ZoomOut}
                        label="Perkecil"
                        size="sm"
                        disabled={skala <= SKALA_MIN}
                        onClick={() => setSkala((s) => Math.max(SKALA_MIN, s - LANGKAH_SKALA))}
                    />
                    <span className="w-12 text-center font-mono text-xs text-ink-muted">
                        {Math.round(skala * 100)}%
                    </span>
                    <IconButton
                        icon={ZoomIn}
                        label="Perbesar"
                        size="sm"
                        disabled={skala >= SKALA_MAKS}
                        onClick={() => setSkala((s) => Math.min(SKALA_MAKS, s + LANGKAH_SKALA))}
                    />
                </div>
            </div>

            <div className="flex-1 overflow-auto bg-surface-sunken p-4">
                {keadaan === 'memuat' ? (
                    <div className="flex h-full items-center justify-center">
                        <Loader2 className="size-6 animate-spin text-ink-subtle" aria-hidden />
                    </div>
                ) : (
                    <canvas
                        ref={canvasRef}
                        aria-label={`Halaman ${halaman} dari ${judul}`}
                        className="mx-auto rounded shadow-card"
                    />
                )}
            </div>
        </div>
    );
}

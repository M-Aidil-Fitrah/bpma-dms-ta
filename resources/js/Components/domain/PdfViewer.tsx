import { Button } from '@/Components/ui/Button';
import { IconButton } from '@/Components/ui/IconButton';
import { cn } from '@/lib/cn';
import { muatPdfJs } from '@/lib/pdf';
import type { PDFDocumentLoadingTask, PDFDocumentProxy, PDFPageProxy, RenderTask } from 'pdfjs-dist';
import { Loader2, Maximize, Minimize, ZoomIn, ZoomOut } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

export interface PdfViewerProps {
    url: string;
    judul: string;
    layarPenuh: boolean;
    onUbahLayarPenuh: () => void;
}

const SKALA_MIN = 0.5;
const SKALA_MAKS = 3;
const LANGKAH_SKALA = 0.25;

/** Penampil PDF berhalaman berkelanjutan dengan render bertahap. */
export function PdfViewer({ url, judul, layarPenuh, onUbahLayarPenuh }: PdfViewerProps) {
    const dokumenRef = useRef<PDFDocumentProxy | null>(null);
    const tugasRef = useRef<PDFDocumentLoadingTask | null>(null);
    const areaGulirRef = useRef<HTMLDivElement>(null);
    const halamanRefs = useRef(new Map<number, HTMLDivElement>());

    const [halaman, setHalaman] = useState(1);
    const [jumlahHalaman, setJumlahHalaman] = useState(0);
    const [ukuranHalaman, setUkuranHalaman] = useState({ lebar: 595, tinggi: 842 });
    const [halamanDirender, setHalamanDirender] = useState<Set<number>>(() => new Set([1]));
    const [skala, setSkala] = useState(1.2);
    const [keadaan, setKeadaan] = useState<'memuat' | 'siap' | 'gagal'>('memuat');

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

                const halamanPertama = await dokumen.getPage(1);
                const viewport = halamanPertama.getViewport({ scale: 1 });
                dokumenRef.current = dokumen;
                setJumlahHalaman(dokumen.numPages);
                setUkuranHalaman({ lebar: viewport.width, tinggi: viewport.height });
                setHalaman(1);
                setHalamanDirender(new Set([1]));
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

    const perbaruiHalamanTerlihat = useCallback(() => {
        const area = areaGulirRef.current;
        if (area === null || jumlahHalaman === 0) return;

        const batas = area.getBoundingClientRect();
        const tengahAtas = batas.top + 32;
        let halamanTerdekat = halaman;
        let jarakTerdekat = Number.POSITIVE_INFINITY;
        const kandidatRender = new Set<number>();

        halamanRefs.current.forEach((elemen, nomor) => {
            const posisi = elemen.getBoundingClientRect();

            if (posisi.bottom > batas.top - batas.height && posisi.top < batas.bottom + batas.height) {
                kandidatRender.add(nomor);
            }

            const jarak = Math.abs(posisi.top - tengahAtas);
            if (jarak < jarakTerdekat) {
                halamanTerdekat = nomor;
                jarakTerdekat = jarak;
            }
        });

        setHalaman(halamanTerdekat);
        setHalamanDirender((sebelumnya) => {
            const berikutnya = new Set(sebelumnya);
            kandidatRender.forEach((nomor) => berikutnya.add(nomor));

            return berikutnya.size === sebelumnya.size ? sebelumnya : berikutnya;
        });
    }, [halaman, jumlahHalaman]);

    useEffect(() => {
        if (keadaan !== 'siap') return;

        const frame = window.requestAnimationFrame(perbaruiHalamanTerlihat);

        return () => window.cancelAnimationFrame(frame);
    }, [keadaan, perbaruiHalamanTerlihat, skala]);

    if (keadaan === 'gagal') {
        return (
            <div className="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
                <p className="text-sm text-ink-muted">Berkas PDF ini tidak dapat ditampilkan di peramban.</p>
                <Button variant="secondary" onClick={() => window.location.assign(url)}>
                    Unduh berkas
                </Button>
            </div>
        );
    }

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-3 py-2">
                <span className="px-2 font-mono text-xs text-ink-muted">
                    Halaman {halaman} dari {jumlahHalaman || '—'}
                </span>

                <div className="flex items-center gap-1">
                    <IconButton icon={ZoomOut} label="Perkecil" size="sm" disabled={skala <= SKALA_MIN} onClick={() => setSkala((nilai) => Math.max(SKALA_MIN, nilai - LANGKAH_SKALA))} />
                    <span className="w-12 text-center font-mono text-xs text-ink-muted">{Math.round(skala * 100)}%</span>
                    <IconButton icon={ZoomIn} label="Perbesar" size="sm" disabled={skala >= SKALA_MAKS} onClick={() => setSkala((nilai) => Math.min(SKALA_MAKS, nilai + LANGKAH_SKALA))} />
                    <IconButton icon={layarPenuh ? Minimize : Maximize} label={layarPenuh ? 'Keluar dari layar penuh' : 'Layar penuh'} size="sm" onClick={onUbahLayarPenuh} />
                </div>
            </div>

            <div ref={areaGulirRef} onScroll={perbaruiHalamanTerlihat} className="min-h-0 flex-1 overflow-auto bg-surface-sunken p-4">
                {keadaan === 'memuat' ? (
                    <div className="flex h-full items-center justify-center">
                        <Loader2 className="size-6 animate-spin text-ink-subtle" aria-hidden />
                    </div>
                ) : (
                    <div className="space-y-4">
                        {Array.from({ length: jumlahHalaman }, (_, indeks) => {
                            const nomor = indeks + 1;

                            return (
                                <HalamanPdf
                                    key={nomor}
                                    nomor={nomor}
                                    dokumen={dokumenRef.current}
                                    judul={judul}
                                    skala={skala}
                                    ukuran={ukuranHalaman}
                                    perluDirender={halamanDirender.has(nomor)}
                                    aktif={halaman === nomor}
                                    onPasangRef={(elemen) => {
                                        if (elemen === null) halamanRefs.current.delete(nomor);
                                        else halamanRefs.current.set(nomor, elemen);
                                    }}
                                />
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}

function HalamanPdf({ nomor, dokumen, judul, skala, ukuran, perluDirender, aktif, onPasangRef }: {
    nomor: number;
    dokumen: PDFDocumentProxy | null;
    judul: string;
    skala: number;
    ukuran: { lebar: number; tinggi: number };
    perluDirender: boolean;
    aktif: boolean;
    onPasangRef: (elemen: HTMLDivElement | null) => void;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        if (!perluDirender || dokumen === null || canvasRef.current === null) return;

        const pdf = dokumen;
        let dibatalkan = false;
        let tugasRender: RenderTask | null = null;

        async function gambar() {
            const page: PDFPageProxy = await pdf.getPage(nomor);
            const viewport = page.getViewport({ scale: skala });
            const canvas = canvasRef.current;
            if (dibatalkan || canvas === null) return;
            const konteks = canvas.getContext('2d');
            if (konteks === null) return;

            const rasio = Math.min(window.devicePixelRatio || 1, 2);
            canvas.width = Math.floor(viewport.width * rasio);
            canvas.height = Math.floor(viewport.height * rasio);
            canvas.style.width = `${Math.floor(viewport.width)}px`;
            canvas.style.height = `${Math.floor(viewport.height)}px`;
            konteks.setTransform(rasio, 0, 0, rasio, 0, 0);

            const tugas = page.render({ canvas, canvasContext: konteks, viewport });
            tugasRender = tugas;

            try {
                await tugas.promise;
            } catch {
                // Membatalkan render saat zoom berubah adalah kondisi normal.
            }
        }

        void gambar();

        return () => {
            dibatalkan = true;
            tugasRender?.cancel();
        };
    }, [dokumen, nomor, perluDirender, skala]);

    return (
        <div
            ref={onPasangRef}
            aria-label={`Halaman ${nomor} dari ${judul}`}
            className={cn('mx-auto flex justify-center rounded bg-surface shadow-card ring-1 ring-inset transition-colors', aktif ? 'ring-brand-300' : 'ring-line')}
            style={{ width: `${ukuran.lebar * skala}px`, minHeight: `${ukuran.tinggi * skala}px` }}
        >
            {perluDirender && <canvas ref={canvasRef} className="rounded" />}
        </div>
    );
}

import { Button } from '@/Components/ui/Button';
import { formatUkuranBerkas } from '@/lib/format';
import { Download, FileQuestion } from 'lucide-react';
import { lazy, Suspense } from 'react';

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
}

/**
 * Menampilkan isi dokumen sesuai tipe berkasnya (FR-09b).
 *
 * Word dan Excel belum menampilkan tata letak aslinya — untuk itu diperlukan
 * konversi ke PDF di sisi server, yang dijadwalkan menyusul. Sampai saat itu,
 * isi teksnya yang ditampilkan, bukan sekadar ikon: teks yang benar jauh lebih
 * berguna daripada gambar berkas yang tidak mengatakan apa-apa.
 */
export function DocumentPreview({ dokumen }: DocumentPreviewProps) {
    const url = `/documents/${dokumen.id}/preview`;
    const mime = dokumen.tipe_berkas;

    if (mime.startsWith('image/')) {
        return (
            <div className="flex h-full items-center justify-center overflow-auto bg-surface-sunken p-4">
                <img
                    src={url}
                    alt={dokumen.judul}
                    className="max-h-full rounded shadow-card"
                />
            </div>
        );
    }

    if (mime === 'application/pdf') {
        return (
            <Suspense fallback={<Memuat />}>
                <PdfViewer url={url} judul={dokumen.judul} />
            </Suspense>
        );
    }

    if (mime.startsWith('video/')) {
        return (
            <div className="flex h-full items-center justify-center bg-ink p-4">
                <video src={url} controls className="max-h-full w-full rounded">
                    Peramban Anda tidak mendukung pemutaran video.
                </video>
            </div>
        );
    }

    if (mime.startsWith('audio/')) {
        return (
            <div className="flex h-full items-center justify-center p-8">
                <audio src={url} controls className="w-full max-w-md">
                    Peramban Anda tidak mendukung pemutaran audio.
                </audio>
            </div>
        );
    }

    if (dokumen.isi_teks) {
        return <PanelTeks teks={dokumen.isi_teks} mime={mime} />;
    }

    return <TanpaPratinjau dokumen={dokumen} />;
}

function PanelTeks({ teks, mime }: { teks: string; mime: string }) {
    const dariEkstraksi = mime !== 'text/plain';

    return (
        <div className="flex h-full flex-col">
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

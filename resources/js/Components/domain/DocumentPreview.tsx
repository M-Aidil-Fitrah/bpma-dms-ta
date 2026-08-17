import { Button } from '@/Components/ui/Button';
import { formatUkuranBerkas } from '@/lib/format';
import { Download, FileQuestion, Loader2 } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';

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

    if (dokumen.preview_tersedia) {
        return (
            <Suspense fallback={<Memuat />}>
                <PdfViewer url={url} judul={dokumen.judul} />
            </Suspense>
        );
    }

    if (mime.startsWith('image/')) {
        return <PratinjauGambar url={url} dokumen={dokumen} />;
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

    if (sedangMenyiapkanPratinjau) {
        return <MenyiapkanPratinjau />;
    }

    if (dokumen.isi_teks) {
        return <PanelTeks teks={dokumen.isi_teks} mime={mime} />;
    }

    return <TanpaPratinjau dokumen={dokumen} />;
}

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
function PratinjauGambar({ url, dokumen }: { url: string; dokumen: App.Data.DocumentDetailData }) {
    const [gagal, setGagal] = useState(false);

    if (gagal) {
        return <TanpaPratinjau dokumen={dokumen} />;
    }

    return (
        <div className="flex h-full items-center justify-center overflow-auto bg-surface-sunken p-4">
            <img
                src={url}
                alt={dokumen.judul}
                className="max-h-full rounded shadow-card"
                onError={() => setGagal(true)}
            />
        </div>
    );
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

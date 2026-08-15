import type { PDFDocumentLoadingTask } from 'pdfjs-dist';

/**
 * Memuat pdf.js sekali, saat benar-benar dibutuhkan.
 *
 * Pustakanya berukuran sekitar satu megabyte. Diimpor secara dinamis supaya
 * tidak ikut di bundel utama — halaman masuk dan dasbor tidak seharusnya
 * menanggung ongkos pustaka yang hanya dipakai untuk menampilkan PDF.
 *
 * Hasil impornya disimpan dalam satu promise bersama: sepuluh kartu PDF pada
 * satu halaman grid memicu satu pemuatan, bukan sepuluh.
 */
let pustaka: Promise<typeof import('pdfjs-dist')> | null = null;

export function muatPdfJs(): Promise<typeof import('pdfjs-dist')> {
    pustaka ??= import('pdfjs-dist').then((pdfjs) => {
        // Worker dirujuk lewat `new URL(..., import.meta.url)` supaya Vite
        // ikut menerbitkan berkasnya saat build. Menuliskan jalurnya sebagai
        // string biasa membuat pratinjau berjalan di `npm run dev` tapi gagal
        // setelah `npm run build` — persis jenis kerusakan yang baru ketahuan
        // saat aplikasi sudah dianggap selesai.
        pdfjs.GlobalWorkerOptions.workerSrc = new URL(
            'pdfjs-dist/build/pdf.worker.min.mjs',
            import.meta.url,
        ).toString();

        return pdfjs;
    });

    return pustaka;
}

/**
 * Menggambar halaman pertama sebuah PDF ke kanvas sebagai gambar mini.
 *
 * @param lebar Lebar target dalam piksel CSS.
 */
export async function gambarHalamanPertama(
    url: string,
    canvas: HTMLCanvasElement,
    lebar: number,
): Promise<void> {
    const pdfjs = await muatPdfJs();

    // Yang ditahan adalah tugas pemuatannya, bukan dokumennya: sejak pdf.js v4,
    // `destroy()` berada di `PDFDocumentLoadingTask` — dan itulah yang
    // melepaskan worker beserta buffer berkasnya.
    let tugas: PDFDocumentLoadingTask | null = null;

    try {
        tugas = pdfjs.getDocument({ url, disableAutoFetch: true });
        const dokumen = await tugas.promise;
        const halaman = await dokumen.getPage(1);

        const skalaAwal = halaman.getViewport({ scale: 1 });
        const viewport = halaman.getViewport({ scale: lebar / skalaAwal.width });

        const konteks = canvas.getContext('2d');
        if (konteks === null) return;

        // Kanvas digambar pada resolusi perangkat lalu dikecilkan lewat CSS,
        // supaya hasilnya tetap tajam di layar high-DPI (`PRD.md` §9).
        const rasio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.floor(viewport.width * rasio);
        canvas.height = Math.floor(viewport.height * rasio);
        canvas.style.width = '100%';
        canvas.style.height = 'auto';
        konteks.scale(rasio, rasio);

        await halaman.render({ canvas, canvasContext: konteks, viewport }).promise;
    } finally {
        // Selalu dilepaskan: pdf.js menahan buffer berkas di memori, dan grid
        // berisi dua puluh PDF akan menumpuknya kalau dibiarkan.
        await tugas?.destroy();
    }
}

/**
 * Fungsi pemformat murni — tanpa efek samping, tanpa ketergantungan pada React.
 *
 * Seluruh tampilan tanggal, ukuran berkas, dan angka melewati berkas ini supaya
 * formatnya seragam di seluruh aplikasi. Menuliskan `toLocaleDateString`
 * langsung di komponen akan membuat format berbeda-beda antar halaman.
 */

const LOCALE = 'id-ID';

/** "2026-08-14" menjadi "14 Agu 2026". */
export function formatTanggal(value: string | null | undefined): string {
    if (!value) return '—';

    return new Date(value).toLocaleDateString(LOCALE, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

/** "2026-08-14" menjadi "14 Agustus 2026". */
export function formatTanggalPanjang(value: string | null | undefined): string {
    if (!value) return '—';

    return new Date(value).toLocaleDateString(LOCALE, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/** Menyertakan jam: "14 Agu 2026, 09:15". */
export function formatWaktu(value: string | null | undefined): string {
    if (!value) return '—';

    const date = new Date(value);

    return `${formatTanggal(value)}, ${date.toLocaleTimeString(LOCALE, {
        hour: '2-digit',
        minute: '2-digit',
    })}`;
}

/** Jarak waktu yang mudah dibaca: "2 jam lalu", "Kemarin". */
export function formatWaktuRelatif(value: string | null | undefined): string {
    if (!value) return '—';

    const detik = Math.floor((Date.now() - new Date(value).getTime()) / 1000);

    if (detik < 60) return 'Baru saja';
    if (detik < 3600) return `${Math.floor(detik / 60)} menit lalu`;
    if (detik < 86_400) return `${Math.floor(detik / 3600)} jam lalu`;
    if (detik < 172_800) return 'Kemarin';
    if (detik < 604_800) return `${Math.floor(detik / 86_400)} hari lalu`;

    return formatTanggal(value);
}

/** 2_400_000 menjadi "2,4 MB". */
export function formatUkuranBerkas(bytes: number | null | undefined): string {
    if (!bytes || bytes <= 0) return '—';

    const satuan = ['B', 'KB', 'MB', 'GB'];
    const tingkat = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        satuan.length - 1,
    );
    const nilai = bytes / 1024 ** tingkat;

    return `${nilai.toLocaleString(LOCALE, {
        maximumFractionDigits: tingkat === 0 ? 0 : 1,
    })} ${satuan[tingkat]}`;
}

/** 1245 menjadi "1.245". */
export function formatAngka(value: number): string {
    return value.toLocaleString(LOCALE);
}

/**
 * Label tipe berkas ringkas dari MIME, untuk lencana di daftar dokumen.
 */
export function labelTipeBerkas(mime: string): string {
    if (mime === 'application/pdf') return 'PDF';
    if (mime.includes('wordprocessingml') || mime === 'application/msword') return 'Word';
    if (mime.includes('spreadsheetml') || mime === 'application/vnd.ms-excel') return 'Excel';
    if (mime.includes('presentationml') || mime === 'application/vnd.ms-powerpoint') return 'PPT';
    if (mime.startsWith('image/')) return 'Gambar';
    if (mime.startsWith('video/')) return 'Video';
    if (mime.startsWith('audio/')) return 'Audio';
    if (mime === 'text/plain') return 'Teks';
    if (mime.includes('zip') || mime.includes('compressed')) return 'ZIP';

    return 'Berkas';
}

/** Memotong teks panjang tanpa memutus di tengah kata. */
export function potongTeks(text: string, maksimum: number): string {
    if (text.length <= maksimum) return text;

    const potongan = text.slice(0, maksimum);
    const spasiTerakhir = potongan.lastIndexOf(' ');

    return `${potongan.slice(0, spasiTerakhir > 0 ? spasiTerakhir : maksimum)}…`;
}

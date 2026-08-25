import i18next from '@/lib/i18n';

/**
 * Fungsi pemformat murni — tanpa ketergantungan pada React (tidak memakai
 * hook), meski tetap sadar bahasa aktif lewat instans i18next singleton.
 * Sengaja bukan komponen/hook: dipanggil dari mana saja tanpa perlu berada
 * di dalam pohon render, dan pemanggilnya tidak perlu berubah saat bahasa
 * berganti — signature setiap fungsi di sini tetap sama.
 *
 * Seluruh tampilan tanggal, ukuran berkas, dan angka melewati berkas ini supaya
 * formatnya seragam di seluruh aplikasi. Menuliskan `toLocaleDateString`
 * langsung di komponen akan membuat format berbeda-beda antar halaman.
 */

function localeAktif(): string {
    return i18next.language === 'en' ? 'en-US' : 'id-ID';
}

/** "2026-08-14" menjadi "14 Agu 2026". */
export function formatTanggal(value: string | null | undefined): string {
    if (!value) return '—';

    return new Date(value).toLocaleDateString(localeAktif(), {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

/** "2026-08-14" menjadi "14 Agustus 2026". */
export function formatTanggalPanjang(value: string | null | undefined): string {
    if (!value) return '—';

    return new Date(value).toLocaleDateString(localeAktif(), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/** Menyertakan jam: "14 Agu 2026, 09:15". */
export function formatWaktu(value: string | null | undefined): string {
    if (!value) return '—';

    const date = new Date(value);

    return `${formatTanggal(value)}, ${date.toLocaleTimeString(localeAktif(), {
        hour: '2-digit',
        minute: '2-digit',
    })}`;
}

/** Jarak waktu yang mudah dibaca: "2 jam lalu", "Kemarin". */
export function formatWaktuRelatif(value: string | null | undefined): string {
    if (!value) return '—';

    const detik = Math.floor((Date.now() - new Date(value).getTime()) / 1000);

    if (detik < 60) return i18next.t('common:format.baruSaja');
    if (detik < 3600) return i18next.t('common:format.menitLalu', { n: Math.floor(detik / 60) });
    if (detik < 86_400) return i18next.t('common:format.jamLalu', { n: Math.floor(detik / 3600) });
    if (detik < 172_800) return i18next.t('common:format.kemarin');
    if (detik < 604_800) return i18next.t('common:format.hariLalu', { n: Math.floor(detik / 86_400) });

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

    return `${nilai.toLocaleString(localeAktif(), {
        maximumFractionDigits: tingkat === 0 ? 0 : 1,
    })} ${satuan[tingkat]}`;
}

/** 1245 menjadi "1.245". */
export function formatAngka(value: number): string {
    return value.toLocaleString(localeAktif());
}

/**
 * Label tipe berkas ringkas dari MIME, untuk lencana di daftar dokumen.
 */
export function labelTipeBerkas(mime: string): string {
    // PDF/Word/Excel/PPT sudah berupa singkatan universal — tidak diterjemahkan.
    if (mime === 'application/pdf') return 'PDF';
    if (mime.includes('wordprocessingml') || mime.includes('msword')) return 'Word';
    if (mime.includes('spreadsheetml') || mime.includes('ms-excel')) return 'Excel';
    if (mime.includes('presentationml') || mime.includes('ms-powerpoint')) return 'PPT';
    if (mime.startsWith('image/')) return i18next.t('common:format.tipeBerkas.gambar');
    if (mime.startsWith('video/')) return i18next.t('common:format.tipeBerkas.video');
    if (mime.startsWith('audio/')) return i18next.t('common:format.tipeBerkas.audio');
    if (mime === 'text/plain') return i18next.t('common:format.tipeBerkas.teks');
    if (mime.includes('zip') || mime.includes('compressed')) return i18next.t('common:format.tipeBerkas.zip');

    return i18next.t('common:format.tipeBerkas.berkas');
}

/**
 * Apakah suatu waktu ISO masih dalam N menit terakhir dari sekarang.
 *
 * Dipakai untuk membatasi jendela "masih menunggu proses latar belakang"
 * (mis. konversi pratinjau Office) — tanpa batas waktu, dokumen yang job-nya
 * gagal permanen (perkakas server tidak terpasang, dsb.) akan tampak
 * "sedang disiapkan" selamanya alih-alih jatuh ke fallback yang aman.
 */
export function dalamJendelaWaktu(value: string, menit: number): boolean {
    const berlalu = Date.now() - new Date(value).getTime();

    return berlalu < menit * 60_000;
}

/** Memotong teks panjang tanpa memutus di tengah kata. */
export function potongTeks(text: string, maksimum: number): string {
    if (text.length <= maksimum) return text;

    const potongan = text.slice(0, maksimum);
    const spasiTerakhir = potongan.lastIndexOf(' ');

    return `${potongan.slice(0, spasiTerakhir > 0 ? spasiTerakhir : maksimum)}…`;
}

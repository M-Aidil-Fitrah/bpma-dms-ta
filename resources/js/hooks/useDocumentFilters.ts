import { useFilters } from '@/hooks/useFilters';
import { useCallback } from 'react';

export interface FilterDokumen {
    cari: string | null;
    kategori: number | null;
    unit: number | null;
    status: string | null;
    status_ekstraksi: string | null;
    pengunggah: number | null;
    tipe: string | null;
    dari: string | null;
    sampai: string | null;
    urut: string;
    urut_manual: boolean;
    arah: 'asc' | 'desc';
    tampilan: 'tabel' | 'grid';
}

/**
 * Spesialisasi `useFilters` untuk daftar dokumen: menambah `urutkan` dan
 * `ubahTampilan`. Daftar dimuat ulang lengkap saat filter berubah; jumlah
 * opsi kecil, sedangkan ini menghindari partial reload yang dapat membuat
 * umpan balik filter terasa diam bila state halaman lama tertinggal.
 *
 * `alamat` dapat diisi halaman lain yang memakai bentuk daftar dokumen yang
 * sama (Dokumen Saya, Terbaru, Berbintang, Sampah) — bawaannya `/documents`
 * untuk Jelajahi Dokumen sendiri.
 */
export function useDocumentFilters(filter: FilterDokumen, alamat: string = '/documents') {
    const { terapkan, ubah, bersihkan } = useFilters(alamat, filter, {
        // Mode tampilan bukan penyaring — dipertahankan supaya "bersihkan
        // filter" tidak diam-diam melemparkan pengguna ke tampilan yang
        // tidak ia pilih.
        pertahankanSaatBersihkan: { tampilan: filter.tampilan },
    });

    const urutkan = useCallback(
        (urut: string, arah: 'asc' | 'desc') => terapkan({ urut, arah, urut_manual: true }),
        [terapkan],
    );

    const ubahTampilan = useCallback(
        (tampilan: 'tabel' | 'grid') => terapkan({ tampilan }),
        [terapkan],
    );

    return { terapkan, ubah, urutkan, ubahTampilan, bersihkan };
}

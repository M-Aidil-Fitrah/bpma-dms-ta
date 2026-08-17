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
    arah: 'asc' | 'desc';
    tampilan: 'tabel' | 'grid';
}

/**
 * Spesialisasi `useFilters` untuk daftar dokumen: menambah `urutkan` dan
 * `ubahTampilan`, dan membatasi Inertia hanya mengambil ulang `dokumen` +
 * `filter` — pilihan kategori dan unit tidak ikut dikirim ulang setiap kali
 * pengguna mengetik, padahal isinya tidak pernah berubah.
 */
export function useDocumentFilters(filter: FilterDokumen) {
    const { terapkan, ubah, bersihkan } = useFilters('/documents', filter, {
        only: ['dokumen', 'filter'],
        // Mode tampilan bukan penyaring — dipertahankan supaya "bersihkan
        // filter" tidak diam-diam melemparkan pengguna ke tampilan yang
        // tidak ia pilih.
        pertahankanSaatBersihkan: { tampilan: filter.tampilan },
    });

    const urutkan = useCallback(
        (urut: string, arah: 'asc' | 'desc') => terapkan({ urut, arah }),
        [terapkan],
    );

    const ubahTampilan = useCallback(
        (tampilan: 'tabel' | 'grid') => terapkan({ tampilan }),
        [terapkan],
    );

    return { terapkan, ubah, urutkan, ubahTampilan, bersihkan };
}

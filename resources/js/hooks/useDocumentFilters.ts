import { router } from '@inertiajs/react';
import { useCallback } from 'react';

export interface FilterDokumen {
    cari: string | null;
    kategori: number | null;
    unit: number | null;
    status: string | null;
    dari: string | null;
    sampai: string | null;
    urut: string;
    arah: 'asc' | 'desc';
    tampilan: 'tabel' | 'grid';
}

/**
 * Mengelola keadaan penyaring daftar dokumen lewat query string.
 *
 * Keadaan penyaring sengaja TIDAK disimpan di state komponen. Menaruhnya di
 * alamat membuat hasil penyaringan dapat dibagikan begitu saja lewat tautan,
 * bertahan setelah halaman disegarkan, dan membuat tombol kembali peramban
 * bekerja seperti yang diharapkan pengguna.
 */
export function useDocumentFilters(filter: FilterDokumen) {
    /**
     * Mengirim ulang permintaan dengan penyaring yang diperbarui.
     *
     * `only: ['dokumen', 'filter']` membuat Inertia hanya mengambil ulang dua
     * props itu — pilihan kategori dan unit tidak ikut dikirim ulang setiap
     * kali pengguna mengetik, padahal isinya tidak pernah berubah.
     */
    const terapkan = useCallback(
        (perubahan: Partial<Record<keyof FilterDokumen, string | number | null>>) => {
            const query: Record<string, string> = {};

            const gabungan = { ...filter, ...perubahan };

            Object.entries(gabungan).forEach(([kunci, nilai]) => {
                if (nilai !== null && nilai !== '' && nilai !== undefined) {
                    query[kunci] = String(nilai);
                }
            });

            router.get('/documents', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['dokumen', 'filter'],
            });
        },
        [filter],
    );

    const ubah = useCallback(
        (kunci: string, nilai: string) => terapkan({ [kunci]: nilai || null } as never),
        [terapkan],
    );

    const urutkan = useCallback(
        (urut: string, arah: 'asc' | 'desc') => terapkan({ urut, arah }),
        [terapkan],
    );

    const ubahTampilan = useCallback(
        (tampilan: 'tabel' | 'grid') => terapkan({ tampilan }),
        [terapkan],
    );

    const bersihkan = useCallback(() => {
        // Mode tampilan bukan penyaring — pilihannya dipertahankan supaya
        // "bersihkan filter" tidak diam-diam melemparkan pengguna kembali ke
        // tampilan yang tidak ia pilih.
        router.get('/documents', { tampilan: filter.tampilan }, {
            preserveScroll: true,
            replace: true,
        });
    }, [filter.tampilan]);

    return { terapkan, ubah, urutkan, ubahTampilan, bersihkan };
}

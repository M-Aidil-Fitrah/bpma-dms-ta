import { router } from '@inertiajs/react';
import { useCallback } from 'react';

type NilaiFilter = string | number | boolean | null;

export interface OpsiFilter<F> {
    /**
     * Field yang tetap dipertahankan saat "bersihkan semua filter" — untuk
     * nilai yang bukan penyaring sungguhan (mis. mode tampilan tabel/grid),
     * supaya membersihkan filter tidak diam-diam melempar pengguna ke mode
     * yang tidak ia pilih.
     */
    pertahankanSaatBersihkan?: Partial<F>;
    /** Membatasi props yang diminta ulang dari Inertia. Kosongkan untuk minta semua. */
    only?: string[];
}

/**
 * Mengelola keadaan penyaring lewat query string — dipakai bersama oleh
 * setiap halaman berdaftar (dokumen, pengguna, riwayat aktivitas, data
 * referensi). Sebelumnya tiap halaman menulis ulang pola yang nyaris
 * identik ini sendiri-sendiri.
 *
 * Keadaan penyaring sengaja TIDAK disimpan di state komponen. Menaruhnya di
 * alamat membuat hasil penyaringan dapat dibagikan lewat tautan, bertahan
 * setelah halaman disegarkan, dan membuat tombol kembali peramban bekerja
 * seperti yang diharapkan pengguna.
 */
export function useFilters<F extends object>(
    alamat: string,
    filter: F,
    { pertahankanSaatBersihkan, only }: OpsiFilter<F> = {},
) {
    /**
     * Mengirim ulang permintaan dengan penyaring yang diperbarui. Menerima
     * beberapa field sekaligus supaya halaman yang perlu mengubah lebih dari
     * satu nilai bersamaan (mis. urutan + arah) tidak melakukan dua
     * permintaan berurutan.
     */
    const terapkan = useCallback(
        (perubahan: Partial<Record<keyof F, NilaiFilter>>) => {
            const query: Record<string, string> = {};
            const gabungan = { ...filter, ...perubahan } as Record<string, NilaiFilter>;

            Object.entries(gabungan).forEach(([kunci, nilai]) => {
                if (nilai !== null && nilai !== '' && nilai !== undefined) {
                    // Laravel menolak "true"/"false" untuk rule `boolean` — hanya
                    // menerima "1"/"0". `String(true)` menghasilkan bentuk yang
                    // gagal validasi dan membuat SETIAP permintaan penyaring
                    // (bukan cuma yang berisi field boolean) dikembalikan tanpa
                    // query string oleh redirect galat validasi.
                    query[kunci] = typeof nilai === 'boolean' ? (nilai ? '1' : '0') : String(nilai);
                }
            });

            router.get(alamat, query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                ...(only ? { only } : {}),
            });
        },
        [alamat, filter, only],
    );

    const ubah = useCallback(
        (kunci: string, nilai: string) => terapkan({ [kunci]: nilai || null } as never),
        [terapkan],
    );

    const bersihkan = useCallback(() => {
        router.get(alamat, pertahankanSaatBersihkan ?? {}, {
            preserveScroll: true,
            replace: true,
        });
    }, [alamat, pertahankanSaatBersihkan]);

    return { terapkan, ubah, bersihkan };
}

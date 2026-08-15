import { router } from '@inertiajs/react';
import { useEffect } from 'react';

interface Opsi {
    jedaMs?: number;
    maksPercobaan?: number;
}

/**
 * Memuat ulang prop `dokumen` secara berkala selama status ekstraksi masih
 * `pending`, supaya badge berpindah ke "Dapat dicari" tanpa pengguna harus
 * memuat ulang halaman secara manual.
 *
 * Berhenti sendiri begitu status berubah (dependensi efek berganti nilai)
 * dan berhenti setelah `maksPercobaan` supaya tab yang ditinggalkan terbuka
 * tidak memanggil server tanpa henti. Nilai bawaan meniru
 * `config('dms.ekstraksi.polling_jeda_ms')` dan `polling_maks_percobaan`.
 */
export function useExtractionStatusPolling(
    status: App.Enums.ExtractionStatus,
    { jedaMs = 3000, maksPercobaan = 40 }: Opsi = {},
): void {
    useEffect(() => {
        if (status !== 'pending') {
            return;
        }

        let percobaan = 0;

        const interval = setInterval(() => {
            percobaan += 1;

            if (percobaan > maksPercobaan) {
                clearInterval(interval);
                return;
            }

            router.reload({ only: ['dokumen'] });
        }, jedaMs);

        return () => clearInterval(interval);
    }, [status, jedaMs, maksPercobaan]);
}

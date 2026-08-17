import { router } from '@inertiajs/react';
import { useEffect } from 'react';

interface Opsi {
    jedaMs?: number;
    maksPercobaan?: number;
}

/**
 * Memuat ulang prop `dokumen` secara berkala selama `aktif` bernilai true —
 * dipakai baik selama ekstraksi/OCR masih `pending` maupun selama pratinjau
 * Office (`preview_path`) belum jadi, supaya keduanya berpindah ke hasil
 * akhirnya tanpa pengguna harus memuat ulang halaman secara manual.
 *
 * Berhenti sendiri begitu `aktif` berubah jadi false (dependensi efek
 * berganti nilai) dan berhenti setelah `maksPercobaan` supaya tab yang
 * ditinggalkan terbuka tidak memanggil server tanpa henti. Nilai bawaan
 * hanya jaring pengaman — pemanggil WAJIB mengoper nilai dari
 * `pollingKonfigurasi` (dikirim controller dari `config('dms.ekstraksi')`)
 * supaya anggaran percobaan selalu menutupi durasi OCR terpanjang yang
 * mungkin terjadi, bukan angka yang diam-diam menyimpang dari config.
 */
export function useDocumentReloadPolling(aktif: boolean, { jedaMs = 3000, maksPercobaan = 320 }: Opsi = {}): void {
    useEffect(() => {
        if (!aktif) {
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
    }, [aktif, jedaMs, maksPercobaan]);
}

import { useToast, type StatusToast } from '@/Components/ui/Toast';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/** Bentuk pesan kilat yang dibagikan `HandleInertiaRequests`. */
type Kilat = Partial<Record<StatusToast, string | null>> & { id?: string };

const URUTAN: StatusToast[] = ['error', 'warning', 'success', 'info'];
const FLASH_TERPUBLIKASI = new Set<string>();
const BATAS_FLASH_TERSIMPAN = 100;

/**
 * Mengubah pesan kilat dari server menjadi toast.
 *
 * Aksi yang mengubah data di aplikasi ini selalu berakhir dengan pengalihan,
 * dan hasilnya dititipkan lewat `session()->with(...)`. Komponen ini satu-satunya
 * tempat pesan itu diangkat menjadi toast — kalau tiap halaman menanganinya
 * sendiri, halaman yang lupa memasangnya akan menelan hasil aksi tanpa jejak.
 *
 * ID flash berasal dari respons server. Ia tetap sama ketika komponen dipasang
 * ulang pada kunjungan yang sama, namun berbeda untuk aksi berikutnya walau
 * pesannya kebetulan sama.
 */
export function FlashToast() {
    const { props } = usePage();
    const { tampilkan } = useToast();
    const terakhir = useRef<string | null>(null);

    useEffect(() => {
        const kilat = (props as { flash?: Kilat }).flash;

        if (kilat === undefined) return;

        const id = kilat.id;
        if (id === undefined || terakhir.current === id || FLASH_TERPUBLIKASI.has(id)) return;

        terakhir.current = id;
        FLASH_TERPUBLIKASI.add(id);
        if (FLASH_TERPUBLIKASI.size > BATAS_FLASH_TERSIMPAN) {
            const palingLama = FLASH_TERPUBLIKASI.values().next().value;
            if (palingLama !== undefined) FLASH_TERPUBLIKASI.delete(palingLama);
        }

        for (const status of URUTAN) {
            const pesan = kilat[status];

            if (typeof pesan === 'string' && pesan !== '') {
                tampilkan({ status, judul: pesan });
            }
        }
    }, [props, tampilkan]);

    return null;
}

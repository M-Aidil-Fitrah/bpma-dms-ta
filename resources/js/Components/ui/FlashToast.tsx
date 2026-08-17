import { useToast, type StatusToast } from '@/Components/ui/Toast';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/** Bentuk pesan kilat yang dibagikan `HandleInertiaRequests`. */
type Kilat = Partial<Record<StatusToast, string | null>>;

const URUTAN: StatusToast[] = ['error', 'warning', 'success', 'info'];

/**
 * Mengubah pesan kilat dari server menjadi toast.
 *
 * Aksi yang mengubah data di aplikasi ini selalu berakhir dengan pengalihan,
 * dan hasilnya dititipkan lewat `session()->with(...)`. Komponen ini satu-satunya
 * tempat pesan itu diangkat menjadi toast — kalau tiap halaman menanganinya
 * sendiri, halaman yang lupa memasangnya akan menelan hasil aksi tanpa jejak.
 *
 * Pemicunya adalah identitas objek `props`, bukan isi pesannya. Inertia
 * membuat objek props baru pada setiap kunjungan, sehingga dua aksi berturut-turut
 * yang menghasilkan kalimat yang sama persis tetap memunculkan dua toast —
 * sementara render ulang biasa di dalam satu halaman tidak memunculkan apa pun.
 */
export function FlashToast() {
    const { props } = usePage();
    const { tampilkan } = useToast();
    const terakhir = useRef<object | null>(null);

    useEffect(() => {
        if (terakhir.current === props) return;

        terakhir.current = props;

        const kilat = (props as { flash?: Kilat }).flash;

        if (kilat === undefined) return;

        for (const status of URUTAN) {
            const pesan = kilat[status];

            if (typeof pesan === 'string' && pesan !== '') {
                tampilkan({ status, judul: pesan });
            }
        }
    }, [props, tampilkan]);

    return null;
}

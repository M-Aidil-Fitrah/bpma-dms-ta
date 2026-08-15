import { useEffect, useRef, useState } from 'react';

/**
 * Menandai kapan sebuah elemen mulai masuk ke area pandang.
 *
 * Dipakai menunda pekerjaan mahal — menggambar halaman pertama PDF — sampai
 * kartunya benar-benar terlihat. Tanpa ini, membuka halaman grid berisi dua
 * puluh dokumen akan langsung mengunduh dan merender dua puluh berkas PDF
 * sekaligus, sebagian besar untuk kartu yang bahkan belum tergulir ke layar.
 *
 * Sekali terlihat, statusnya tidak dikembalikan lagi: hasil render tidak perlu
 * dibuang hanya karena kartunya tergulir keluar.
 */
export function useTampakDiLayar<T extends HTMLElement>(margin = '200px') {
    const ref = useRef<T>(null);
    const [tampak, setTampak] = useState(false);

    useEffect(() => {
        const elemen = ref.current;
        if (elemen === null || tampak) return;

        // Peramban lawas tanpa IntersectionObserver langsung dianggap terlihat,
        // supaya pratinjaunya tetap muncul walau tanpa penundaan.
        if (typeof IntersectionObserver === 'undefined') {
            setTampak(true);

            return;
        }

        const observer = new IntersectionObserver(
            ([entri]) => {
                if (entri.isIntersecting) {
                    setTampak(true);
                    observer.disconnect();
                }
            },
            { rootMargin: margin },
        );

        observer.observe(elemen);

        return () => observer.disconnect();
    }, [margin, tampak]);

    return { ref, tampak };
}

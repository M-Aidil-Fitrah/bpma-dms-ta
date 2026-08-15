import { useEffect, useState } from 'react';

/**
 * Menunda perubahan nilai sampai pengguna berhenti mengetik.
 *
 * Tanpa ini, setiap ketukan tombol pada kolom pencarian memicu satu permintaan
 * ke server — mengetik "laporan" berarti tujuh permintaan, dan yang terakhir
 * belum tentu tiba paling akhir.
 */
export function useDebounce<T>(value: T, delayMs = 300): T {
    const [ditunda, setDitunda] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setDitunda(value), delayMs);

        return () => clearTimeout(timer);
    }, [value, delayMs]);

    return ditunda;
}

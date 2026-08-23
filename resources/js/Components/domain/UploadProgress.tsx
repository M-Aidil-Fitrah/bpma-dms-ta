import { formatUkuranBerkas } from '@/lib/format';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';

export interface UploadProgressProps {
    /** Persentase dari Inertia; null berarti belum ada progres. */
    persen: number | null;
    /** Byte yang sudah terkirim, dari event progres asli. */
    terkirim: number;
    total: number;
    namaBerkas: string;
}

/**
 * Progres unggah berdasarkan byte yang benar-benar terkirim (FR-33b).
 *
 * Kecepatan dan sisa waktu dihitung dari selisih byte antar pembaruan, bukan
 * dari animasi yang berjalan sendiri. Bilah progres yang bergerak tanpa
 * berhubungan dengan transfer sesungguhnya adalah bentuk kebohongan antarmuka:
 * ia terlihat meyakinkan justru saat unggahannya sedang macet.
 *
 * Sisa waktu dihaluskan dengan rata-rata bergerak. Tanpa itu, angkanya
 * meloncat-loncat setiap kali jaringan berdenyut, dan pengguna berhenti
 * mempercayainya.
 */
export function UploadProgress({
    persen,
    terkirim,
    total,
    namaBerkas,
}: UploadProgressProps) {
    const { t } = useTranslation('documentForm');
    const [kecepatan, setKecepatan] = useState<number | null>(null);
    const [sisaDetik, setSisaDetik] = useState<number | null>(null);
    const sebelumnya = useRef<{ waktu: number; byte: number } | null>(null);
    const riwayat = useRef<number[]>([]);

    useEffect(() => {
        const sekarang = performance.now();
        const lalu = sebelumnya.current;

        if (lalu !== null) {
            const detik = (sekarang - lalu.waktu) / 1000;
            const selisih = terkirim - lalu.byte;

            // Pembaruan yang terlalu rapat menghasilkan pembagian oleh angka
            // sangat kecil, dan kecepatannya jadi tidak masuk akal.
            if (detik > 0.15 && selisih > 0) {
                riwayat.current = [...riwayat.current.slice(-4), selisih / detik];

                const rata =
                    riwayat.current.reduce((a, b) => a + b, 0) / riwayat.current.length;

                setKecepatan(rata);
                setSisaDetik(rata > 0 ? Math.max(0, (total - terkirim) / rata) : null);
                sebelumnya.current = { waktu: sekarang, byte: terkirim };
            }
        } else {
            sebelumnya.current = { waktu: sekarang, byte: terkirim };
        }
    }, [terkirim, total]);

    const nilai = persen ?? 0;
    const selesai = nilai >= 100;

    return (
        <div className="rounded-card border border-line bg-surface p-4">
            <p className="truncate text-sm font-medium text-ink">{namaBerkas}</p>

            <div
                role="progressbar"
                aria-valuenow={Math.round(nilai)}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={t('documentForm:progresUnggah.ariaLabel')}
                className="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunken"
            >
                <div
                    className="h-full rounded-full bg-brand-700 transition-[width] duration-150"
                    style={{ width: `${nilai}%` }}
                />
            </div>

            <div className="mt-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs">
                <span className="font-medium text-brand-700">
                    {t('documentForm:progresUnggah.persenSelesai', { persen: Math.round(nilai) })}
                </span>

                <span className="font-mono text-ink-muted">
                    {formatUkuranBerkas(terkirim)} / {formatUkuranBerkas(total)}
                </span>
            </div>

            {!selesai && kecepatan !== null && (
                <p className="mt-1.5 font-mono text-xs text-ink-subtle">
                    {t('documentForm:progresUnggah.perDetik', { kecepatan: formatUkuranBerkas(kecepatan) })}
                    {sisaDetik !== null &&
                        ` · ${t('documentForm:progresUnggah.sisaWaktu', { waktu: formatSisa(sisaDetik, t) })}`}
                </p>
            )}

            {selesai && (
                <p className="mt-1.5 text-xs text-ink-muted">
                    {t('documentForm:progresUnggah.selesaiTersimpan')}
                </p>
            )}
        </div>
    );
}

function formatSisa(detik: number, t: TFunction): string {
    if (detik < 60) return t('documentForm:progresUnggah.detik', { jumlah: Math.ceil(detik) });
    if (detik < 3600) return t('documentForm:progresUnggah.menit', { jumlah: Math.ceil(detik / 60) });

    return t('documentForm:progresUnggah.jam', { jumlah: (detik / 3600).toFixed(1) });
}

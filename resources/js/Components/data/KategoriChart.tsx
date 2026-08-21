import { Skeleton } from '@/Components/ui/Skeleton';
import { formatAngka } from '@/lib/format';
import { ArcElement, Chart, DoughnutController, Tooltip } from 'chart.js';
import { useEffect, useRef, useState } from 'react';

export interface KategoriChartProps {
    data: readonly App.Data.KategoriRingkasData[];
}

/**
 * Warna potongan diagram. Diambil dari keluarga warna merek, bukan warna acak,
 * supaya diagramnya terbaca sebagai bagian dari aplikasi — bukan tempelan.
 */
const WARNA = [
    '#1d3c8f', '#2f52b8', '#4f74d1', '#80a0e3', '#b3c4ef',
    '#2f7434', '#4caf50', '#b45309', '#15803d', '#6b7280',
];

/**
 * Diagram komposisi dokumen per kategori (FR-02).
 *
 * Chart.js ikut pada chunk halaman Dashboard, bukan bundle utama aplikasi.
 * Dengan begitu halaman masuk tetap ringan dan Firefox tidak perlu memuat
 * modul chart kedua secara dinamis setelah Dashboard sudah tampil.
 */
export function KategoriChart({ data }: KategoriChartProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [siap, setSiap] = useState(false);

    useEffect(() => {
        let chart: { destroy: () => void } | null = null;

        if (!canvasRef.current) return;

        // Hanya bagian yang dipakai yang didaftarkan, bukan seluruh
        // registri Chart.js — sisanya tidak ikut terbawa ke bundel.
        Chart.register(ArcElement, DoughnutController, Tooltip);

        chart = new Chart(canvasRef.current, {
            type: 'doughnut',
            data: {
                labels: data.map((d) => d.nama),
                datasets: [
                    {
                        data: data.map((d) => d.jumlah),
                        backgroundColor: data.map((_, i) => WARNA[i % WARNA.length]),
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (ctx) =>
                                ` ${ctx.label}: ${formatAngka(Number(ctx.parsed))} dokumen`,
                        },
                    },
                },
            },
        });

        setSiap(true);

        return () => {
            chart?.destroy();
        };
    }, [data]);

    return (
        <div className="relative h-56">
            {!siap && <Skeleton className="absolute inset-0 rounded-full" />}
            <canvas ref={canvasRef} role="img" aria-label="Komposisi dokumen per kategori" />
        </div>
    );
}

import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

interface DocumentSearchMatchProps {
    kecocokan: string[] | null;
    cuplikan: string | null;
    jumlahFrasa: number | null;
}

/**
 * Penjelas hasil pencarian yang sengaja hanya menerima projection pendek dari
 * server. Komponen ini tidak pernah memegang atau meminta `extracted_text`
 * penuh, sehingga daftar tetap ringan dan tidak memperluas permukaan data.
 */
export function DocumentSearchMatch({
    kecocokan,
    cuplikan,
    jumlahFrasa,
}: DocumentSearchMatchProps) {
    const kataKunci = usePage<PageProps<{ filter?: { cari?: string | null } }>>().props.filter?.cari ?? '';

    if (!kecocokan?.length) {
        return null;
    }

    return (
        <div className="mt-1.5 space-y-1 text-xs text-ink-muted">
            <p>
                Cocok di: <span className="font-medium text-ink-muted">{kecocokan.join(' · ')}</span>
            </p>

            {jumlahFrasa && (
                <p className="font-medium text-brand-700">
                    Ditemukan {jumlahFrasa} kali di isi dokumen
                </p>
            )}

            {cuplikan && (
                <p className="line-clamp-2 text-ink-subtle">
                    <span aria-hidden>…</span>
                    <SorotKata teks={cuplikan} kataKunci={kataKunci} />
                    <span aria-hidden>…</span>
                </p>
            )}
        </div>
    );
}

function SorotKata({ teks, kataKunci }: { teks: string; kataKunci: string }) {
    const teksKecil = teks.toLocaleLowerCase();
    const istilah = istilahSorot(kataKunci, teksKecil);
    if (!istilah) return teks;

    const posisi = teksKecil.indexOf(istilah.toLocaleLowerCase());
    if (posisi < 0) return teks;

    return (
        <>
            {teks.slice(0, posisi)}
            <mark className="rounded bg-warning-soft px-0.5 text-ink">
                {teks.slice(posisi, posisi + istilah.length)}
            </mark>
            {teks.slice(posisi + istilah.length)}
        </>
    );
}

function istilahSorot(kataKunci: string, teksKecil: string): string | null {
    const frasa = kataKunci.trim();
    const kata = frasa.split(/[^\p{L}\p{N}]+/u).filter((bagian) => bagian.length >= 3);
    const kandidat = frasa === '' ? kata : [frasa, ...kata];

    return kandidat.find((bagian) => teksKecil.includes(bagian.toLocaleLowerCase())) ?? null;
}

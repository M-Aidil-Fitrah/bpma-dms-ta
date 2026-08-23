import { Badge } from '@/Components/ui/Badge';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { CalendarClock } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface DocumentSearchMatchProps {
    kecocokan: string[] | null;
    cuplikan: string | null;
    jumlahFrasa: number | null;
    /** Dipakai menghitung badge "X hari lagi" saat filter Masa Berlaku aktif. */
    masaBerlaku: string | null;
}

/**
 * Penjelas hasil pencarian yang sengaja hanya menerima projection pendek dari
 * server. Komponen ini tidak pernah memegang atau meminta `extracted_text`
 * penuh, sehingga daftar tetap ringan dan tidak memperluas permukaan data.
 *
 * Saat filter Masa Berlaku aktif, slot ini diambil alih badge "X hari lagi" —
 * kecocokan pencarian isi (yang datang dari hasil OCR) tidak relevan lagi
 * ketika yang sedang dilihat pengguna adalah daftar dokumen mendekati masa
 * evaluasi, bukan hasil pencarian kata kunci.
 */
export function DocumentSearchMatch({
    kecocokan,
    cuplikan,
    jumlahFrasa,
    masaBerlaku,
}: DocumentSearchMatchProps) {
    const { t } = useTranslation('documentBrowse');
    const props = usePage<PageProps<{ filter?: { cari?: string | null; evaluasi?: number | null } }>>().props;
    const kataKunci = props.filter?.cari ?? '';
    const evaluasiAktif = Boolean(props.filter?.evaluasi);

    if (evaluasiAktif) {
        return <EvaluasiBadge masaBerlaku={masaBerlaku} />;
    }

    if (!kecocokan?.length) {
        return null;
    }

    return (
        <div className="mt-1.5 space-y-1 text-xs text-ink-muted">
            <p>
                {t('documentBrowse:searchMatch.cocokDi')} <span className="font-medium text-ink-muted">{kecocokan.join(' · ')}</span>
            </p>

            {jumlahFrasa && (
                <p className="font-medium text-brand-700">
                    {t('documentBrowse:searchMatch.ditemukanKali', { jumlah: jumlahFrasa })}
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

function EvaluasiBadge({ masaBerlaku }: { masaBerlaku: string | null }) {
    const { t } = useTranslation('documentBrowse');

    if (!masaBerlaku) return null;

    const hari = Math.max(0, Math.ceil((new Date(masaBerlaku).getTime() - Date.now()) / 86_400_000));

    return (
        <Badge variant="warning" size="sm" className="mt-1.5">
            <CalendarClock className="size-3" aria-hidden />
            {hari === 0 ? t('documentBrowse:evaluasiBadge.hariIni') : t('documentBrowse:evaluasiBadge.hariLagi', { hari })}
        </Badge>
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

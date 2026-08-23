import { Badge } from '@/Components/ui/Badge';
import { Globe, Lock, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export interface AccessSummaryProps {
    /** Dihitung backend dari mekanisme yang benar-benar aktif. */
    ringkasan: readonly string[] | null;
    /** Tampilkan hanya lencana pertama, sisanya sebagai hitungan. */
    ringkas?: boolean;
}

/**
 * Menampilkan mekanisme akses yang sedang aktif pada sebuah dokumen.
 *
 * Isinya datang dari backend, dihitung dari kolom dan relasi yang sama dengan
 * yang menegakkan hak akses — sehingga label yang tampil tidak mungkin
 * bertentangan dengan kenyataan. Frontend tidak pernah menyimpulkannya sendiri.
 */
export function AccessSummary({ ringkasan, ringkas = false }: AccessSummaryProps) {
    const { t } = useTranslation('documentForm');

    if (ringkasan === null || ringkasan.length === 0) {
        return <span className="text-sm text-ink-subtle">—</span>;
    }

    const semua = ringkasan[0] === 'Semua pengguna';
    const terbatas = ringkasan[0] === 'Hanya pengunggah';
    const Icon = semua ? Globe : terbatas ? Lock : ShieldCheck;
    const variant = semua ? 'info' : terbatas ? 'neutral' : 'brand';

    if (ringkas) {
        /*
         * Bentuk ringkas sengaja tidak menampilkan nama unit.
         *
         * Nama unit BPMA panjang-panjang — "Divisi Audit Kontraktor Kontrak
         * Kerja Sama Eksplorasi & Eksploitasi" — dan menampilkannya utuh di sel
         * tabel mendorong kolom lain keluar layar. Rinciannya tetap terbaca
         * lewat tooltip, dan tersaji lengkap di halaman detail.
         */
        const label = semua
            ? 'Semua'
            : terbatas
              ? 'Terbatas'
              : ringkasan.length === 1
                ? ringkasanPendek(ringkasan[0])
                : `${ringkasan.length} mekanisme`;

        return (
            <Badge variant={variant} size="sm" className="max-w-full">
                <Icon className="size-3 shrink-0" aria-hidden />
                <span className="truncate" title={ringkasan.join(' · ')}>
                    {label}
                </span>
            </Badge>
        );
    }

    return (
        <ul className="flex flex-wrap gap-1.5">
            {ringkasan.map((bagian, index) => (
                <li key={bagian}>
                    <Badge variant={index === 0 ? variant : 'brand'} size="sm">
                        {index === 0 && <Icon className="size-3" aria-hidden />}
                        {bagian}
                    </Badge>
                </li>
            ))}
        </ul>
    );
}

/**
 * Menyingkat satu baris ringkasan menjadi kata kunci mekanismenya saja.
 */
function ringkasanPendek(bagian: string): string {
    if (bagian.startsWith('Unit:')) return 'Unit tertentu';
    if (bagian.includes('unit kerja')) return bagian;
    if (bagian.startsWith('Jenjang jabatan')) return 'Jenjang jabatan';
    if (bagian.includes('orang tertentu')) return bagian;

    return bagian;
}

import { Badge } from '@/Components/ui/Badge';
import { useUnitTree } from '@/hooks/useUnitTree';
import { cn } from '@/lib/cn';
import { Check, ChevronRight } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

export interface UnitPilihan {
    id: number;
    nama: string;
    parent_id: number | null;
}

export interface UnitTreePickerProps {
    units: readonly UnitPilihan[];
    terpilih: readonly number[];
    onChange: (ids: number[]) => void;
}

/**
 * Pemilih unit berbentuk pohon, dengan cascade yang terlihat.
 *
 * Memilih unit induk otomatis mencentang seluruh divisi di bawahnya — dan
 * pengguna dapat mencabut sebagian secara manual (FR-39). Cascade sengaja
 * ditampilkan sebagai centang sungguhan, bukan disembunyikan sebagai aturan:
 * pengguna harus melihat persis unit mana saja yang akan mendapat akses,
 * sebelum ia menyimpan.
 */
export function UnitTreePicker({ units, terpilih, onChange }: UnitTreePickerProps) {
    const { t } = useTranslation('documentForm');
    const { induk, anakDari, terbuka, toggleTerbuka } = useUnitTree(units);
    const dipilih = useMemo(() => new Set(terpilih), [terpilih]);

    function ubah(ids: number[], aktif: boolean) {
        const berikutnya = new Set(dipilih);
        for (const id of ids) {
            aktif ? berikutnya.add(id) : berikutnya.delete(id);
        }
        onChange([...berikutnya]);
    }

    function toggleInduk(unit: UnitPilihan) {
        const anak = anakDari.get(unit.id) ?? [];
        const aktif = !dipilih.has(unit.id);

        // Cascade: induk beserta seluruh anaknya bergerak bersamaan.
        ubah([unit.id, ...anak.map((a) => a.id)], aktif);
    }

    return (
        <div className="max-h-72 space-y-1 overflow-y-auto rounded-lg border border-line p-2">
            {induk.map((unit) => {
                const anak = anakDari.get(unit.id) ?? [];
                const anakTerpilih = anak.filter((a) => dipilih.has(a.id)).length;
                const isTerbuka = terbuka.has(unit.id);

                return (
                    <div key={unit.id}>
                        <div className="flex min-w-0 items-center gap-1">
                            {anak.length > 0 && (
                                <button
                                    type="button"
                                    onClick={() => toggleTerbuka(unit.id)}
                                    aria-label={t(
                                        isTerbuka ? 'documentForm:pohonUnit.tutupDivisi' : 'documentForm:pohonUnit.bukaDivisi',
                                        { nama: unit.nama },
                                    )}
                                    aria-expanded={isTerbuka}
                                    className="flex size-6 shrink-0 items-center justify-center rounded text-ink-subtle hover:bg-surface-sunken"
                                >
                                    <ChevronRight
                                        className={cn(
                                            'size-4 transition-transform',
                                            isTerbuka && 'rotate-90',
                                        )}
                                        aria-hidden
                                    />
                                </button>
                            )}

                            <BarisUnit
                                nama={unit.nama}
                                terpilih={dipilih.has(unit.id)}
                                onToggle={() => toggleInduk(unit)}
                                tebal
                            />

                            {anakTerpilih > 0 && (
                                <Badge variant="brand" size="sm">
                                    {t('documentForm:pohonUnit.jumlahDivisi', { jumlah: anakTerpilih })}
                                </Badge>
                            )}
                        </div>

                        {isTerbuka && anak.length > 0 && (
                            <div className="ml-7 space-y-0.5 border-l border-line pl-2">
                                {anak.map((divisi) => (
                                    <BarisUnit
                                        key={divisi.id}
                                        nama={divisi.nama}
                                        terpilih={dipilih.has(divisi.id)}
                                        onToggle={() =>
                                            ubah([divisi.id], !dipilih.has(divisi.id))
                                        }
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function BarisUnit({
    nama,
    terpilih,
    onToggle,
    tebal = false,
}: {
    nama: string;
    terpilih: boolean;
    onToggle: () => void;
    tebal?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onToggle}
            aria-pressed={terpilih}
            className={cn(
                'flex min-h-touch min-w-0 flex-1 items-center gap-2 rounded px-2 py-1.5 text-left text-sm transition-colors sm:min-h-8',
                terpilih ? 'bg-brand-50 text-brand-700' : 'text-ink-muted hover:bg-surface-sunken',
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'flex size-4 shrink-0 items-center justify-center rounded border',
                    terpilih ? 'border-brand-700 bg-brand-700 text-white' : 'border-line',
                )}
            >
                {terpilih && <Check className="size-3" />}
            </span>

            {/* Nama unit dibiarkan membungkus, bukan dipotong. Nama divisi di
                sini panjang-panjang dan sering berbeda hanya di bagian
                belakangnya — memotongnya justru membuat dua unit tampak
                identik, dan salah centang berarti dokumen salah bagi. */}
            <span className={cn('min-w-0 break-words', tebal && 'font-medium')}>
                {nama}
            </span>
        </button>
    );
}

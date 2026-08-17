import type { UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { cn } from '@/lib/cn';
import { ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface UnitTreeSelectProps {
    units: readonly UnitPilihan[];
    nilai: number | null;
    onChange: (id: number | null) => void;
}

/**
 * Penyaring unit asal berbentuk pohon — satu pilihan, bukan cascade seperti
 * `UnitTreePicker` di formulir unggah.
 *
 * Hasil penyaringannya identik dengan dropdown datar (`origin_unit_id`
 * persis satu unit); bedanya murni tampilan — hierarki lewat indentasi dan
 * lipat/buka per cabang, bukan label "Induk — Anak" yang digabung jadi satu
 * baris.
 */
export function UnitTreeSelect({ units, nilai, onChange }: UnitTreeSelectProps) {
    const [terbuka, setTerbuka] = useState<Set<number>>(new Set());

    const { induk, anakDari } = useMemo(() => {
        const induk = units.filter((u) => u.parent_id === null);
        const anakDari = new Map<number, UnitPilihan[]>();

        for (const unit of units) {
            if (unit.parent_id === null) continue;
            const daftar = anakDari.get(unit.parent_id) ?? [];
            daftar.push(unit);
            anakDari.set(unit.parent_id, daftar);
        }

        return { induk, anakDari };
    }, [units]);

    function toggle(id: number) {
        setTerbuka((s) => {
            const n = new Set(s);
            n.has(id) ? n.delete(id) : n.add(id);

            return n;
        });
    }

    return (
        <div className="max-h-72 space-y-0.5 overflow-y-auto rounded-lg border border-line p-2">
            <BarisPilih nama="Semua unit" terpilih={nilai === null} onClick={() => onChange(null)} tebal />

            {induk.map((unit) => {
                const anak = anakDari.get(unit.id) ?? [];
                const isTerbuka = terbuka.has(unit.id);

                return (
                    <div key={unit.id}>
                        <div className="flex min-w-0 items-center gap-1">
                            {anak.length > 0 ? (
                                <button
                                    type="button"
                                    onClick={() => toggle(unit.id)}
                                    aria-label={`${isTerbuka ? 'Tutup' : 'Buka'} divisi ${unit.nama}`}
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
                            ) : (
                                <span className="size-6 shrink-0" aria-hidden />
                            )}

                            <BarisPilih
                                nama={unit.nama}
                                terpilih={nilai === unit.id}
                                onClick={() => onChange(unit.id)}
                                tebal
                            />
                        </div>

                        {isTerbuka && anak.length > 0 && (
                            <div className="ml-7 space-y-0.5 border-l border-line pl-2">
                                {anak.map((divisi) => (
                                    <BarisPilih
                                        key={divisi.id}
                                        nama={divisi.nama}
                                        terpilih={nilai === divisi.id}
                                        onClick={() => onChange(divisi.id)}
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

function BarisPilih({
    nama,
    terpilih,
    onClick,
    tebal = false,
}: {
    nama: string;
    terpilih: boolean;
    onClick: () => void;
    tebal?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={terpilih}
            className={cn(
                'flex min-h-touch w-full min-w-0 items-center gap-2 rounded px-2 py-1.5 text-left text-sm transition-colors sm:min-h-8',
                terpilih ? 'bg-brand-50 text-brand-700' : 'text-ink-muted hover:bg-surface-sunken',
                tebal && 'font-medium',
            )}
        >
            {/* Nama unit dibiarkan membungkus, bukan dipotong — sama seperti
                `UnitTreePicker`, nama divisi di sini sering hanya berbeda di
                bagian belakangnya. */}
            <span className="min-w-0 break-words">{nama}</span>
        </button>
    );
}

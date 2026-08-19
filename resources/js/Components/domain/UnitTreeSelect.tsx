import type { UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { useUnitTree } from '@/hooks/useUnitTree';
import { cn } from '@/lib/cn';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface UnitTreeSelectProps {
    units: readonly UnitPilihan[];
    nilai: number | null;
    onChange: (id: number | null) => void;
    id?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
}

/**
 * Satu pemilih Unit Asal yang tampak seperti dropdown, tetapi menjaga pohon
 * Deputi → Divisi di dalam panelnya. Deputi adalah pembuka cabang, sedangkan
 * nilai filter selalu Unit Asal yang benar-benar dipilih (Divisi atau unit
 * tanpa anak); ini menghindari klaim keliru bahwa memilih Deputi otomatis
 * mencakup seluruh Divisi di bawahnya.
 */
export function UnitTreeSelect({
    units,
    nilai,
    onChange,
    id,
    'aria-describedby': describedBy,
    'aria-invalid': invalid = false,
}: UnitTreeSelectProps) {
    const { induk, anakDari, terbuka, toggleTerbuka } = useUnitTree(units);
    const [menuTerbuka, setMenuTerbuka] = useState(false);
    const pembungkus = useRef<HTMLDivElement>(null);
    const pilihan = units.find((unit) => unit.id === nilai);

    useEffect(() => {
        function tutupJikaDiLuar(event: PointerEvent) {
            if (!pembungkus.current?.contains(event.target as Node)) {
                setMenuTerbuka(false);
            }
        }

        document.addEventListener('pointerdown', tutupJikaDiLuar);

        return () => document.removeEventListener('pointerdown', tutupJikaDiLuar);
    }, []);

    function pilih(id: number | null) {
        onChange(id);
        setMenuTerbuka(false);
    }

    return (
        <div ref={pembungkus} className="relative">
            <button
                id={id}
                type="button"
                aria-haspopup="tree"
                aria-expanded={menuTerbuka}
                aria-describedby={describedBy}
                aria-invalid={invalid}
                onClick={() => setMenuTerbuka((terbuka) => !terbuka)}
                onKeyDown={(event) => {
                    if (event.key === 'Escape') setMenuTerbuka(false);
                }}
                className={cn(
                    'flex h-10 min-h-touch w-full items-center justify-between rounded-lg border bg-surface px-3 text-left text-sm text-ink sm:min-h-0',
                    'focus:border-brand-700 focus:ring-1 focus:ring-brand-700',
                    invalid ? 'border-danger focus:border-danger focus:ring-danger' : 'border-line',
                )}
            >
                <span className="truncate">{pilihan?.nama ?? 'Semua unit asal'}</span>
                <ChevronDown
                    className={cn('size-4 shrink-0 text-ink-subtle transition-transform', menuTerbuka && 'rotate-180')}
                    aria-hidden
                />
            </button>

            {menuTerbuka && (
                <div
                    role="tree"
                    aria-label="Pilih unit asal"
                    className="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-line bg-surface p-2 shadow-pop"
                >
                    <BarisPilih nama="Semua unit asal" terpilih={nilai === null} onClick={() => pilih(null)} tebal />

                    {induk.map((unit) => {
                        const anak = anakDari.get(unit.id) ?? [];
                        const isTerbuka = terbuka.has(unit.id);

                        return (
                            <div key={unit.id} role="treeitem" aria-expanded={anak.length > 0 ? isTerbuka : undefined}>
                                <div className="flex min-w-0 items-center gap-1">
                                    {anak.length > 0 ? (
                                        <button
                                            type="button"
                                            onClick={() => toggleTerbuka(unit.id)}
                                            aria-label={`${isTerbuka ? 'Tutup' : 'Buka'} divisi ${unit.nama}`}
                                            className="flex size-7 shrink-0 items-center justify-center rounded text-ink-subtle hover:bg-surface-sunken"
                                        >
                                            <ChevronRight
                                                className={cn('size-4 transition-transform', isTerbuka && 'rotate-90')}
                                                aria-hidden
                                            />
                                        </button>
                                    ) : (
                                        <span className="size-7 shrink-0" aria-hidden />
                                    )}

                                    {anak.length > 0 ? (
                                        <BarisPilih nama={unit.nama} terpilih={false} onClick={() => toggleTerbuka(unit.id)} tebal />
                                    ) : (
                                        <BarisPilih nama={unit.nama} terpilih={nilai === unit.id} onClick={() => pilih(unit.id)} tebal />
                                    )}
                                </div>

                                {isTerbuka && anak.length > 0 && (
                                    <div role="group" className="ml-8 space-y-0.5 border-l border-line pl-2">
                                        {anak.map((divisi) => (
                                            <div key={divisi.id} role="treeitem">
                                                <BarisPilih nama={divisi.nama} terpilih={nilai === divisi.id} onClick={() => pilih(divisi.id)} />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
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
            <span className="min-w-0 break-words">{nama}</span>
        </button>
    );
}

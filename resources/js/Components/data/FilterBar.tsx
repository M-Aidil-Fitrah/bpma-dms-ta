import type { UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { UnitTreeSelect } from '@/Components/domain/UnitTreeSelect';
import { UserFilterSelect, type PenggunaFilterPilihan } from '@/Components/domain/UserFilterSelect';
import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Select, type SelectOption } from '@/Components/ui/Select';
import { cn } from '@/lib/cn';
import { Filter, RotateCcw, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';

export interface FilterChip {
    kunci: string;
    label: string;
}

export interface FilterDefinition {
    kunci: string;
    label: string;
    tipe: 'select' | 'date' | 'tree' | 'user';
    options?: readonly SelectOption[];
    placeholder?: string;
    /** Wajib diisi saat `tipe: 'tree'`. */
    treeUnits?: readonly UnitPilihan[];
    /** Wajib diisi saat `tipe: 'user'` — sumber pencarian pengguna. */
    userSearchUrl?: string;
    /**
     * Wajib diisi saat `tipe: 'user'` — pengguna yang sedang aktif
     * sebagai nilai filter. Diresolusi di server (bukan dicari ulang di
     * klien) karena `nilai[kunci]` hanya menyimpan id, bukan nama.
     */
    userValue?: PenggunaFilterPilihan | null;
}

export interface FilterBarProps {
    definisi: readonly FilterDefinition[];
    nilai: Record<string, string>;
    onChange: (kunci: string, nilai: string) => void;
    onReset: () => void;
    chips: readonly FilterChip[];
    onHapusChip: (kunci: string) => void;
    /** Slot kiri, biasanya kolom pencarian. */
    children?: ReactNode;
}

/**
 * Bilah penyaring dengan panel yang dapat dilipat.
 *
 * Panelnya tersembunyi secara bawaan supaya halaman tidak dibuka oleh deretan
 * kolom yang jarang dipakai. Penyaring yang sedang aktif tetap terlihat sebagai
 * chip — tanpa itu, pengguna bisa bingung mengapa daftarnya kosong padahal
 * penyaring yang lupa dimatikan masih berlaku.
 */
export function FilterBar({
    definisi,
    nilai,
    onChange,
    onReset,
    chips,
    onHapusChip,
    children,
}: FilterBarProps) {
    const [terbuka, setTerbuka] = useState(false);

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                {children}

                <Button
                    variant="secondary"
                    icon={Filter}
                    onClick={() => setTerbuka((v) => !v)}
                    aria-expanded={terbuka}
                    className={cn('shrink-0', chips.length > 0 && 'border-brand-700 text-brand-700')}
                >
                    Filter
                    {chips.length > 0 && (
                        <span className="ml-1 inline-flex size-5 items-center justify-center rounded-full bg-brand-700 text-xs text-white">
                            {chips.length}
                        </span>
                    )}
                </Button>

                {chips.length > 0 && (
                    <Button
                        variant="ghost"
                        size="sm"
                        icon={RotateCcw}
                        onClick={onReset}
                        className="shrink-0"
                    >
                        Reset filter
                    </Button>
                )}
            </div>

            {terbuka && (
                <div className="grid gap-3 rounded-card border border-line bg-surface-sunken p-4 sm:grid-cols-2 xl:grid-cols-4">
                    {definisi.map((filter) => (
                        <Field key={filter.kunci} label={filter.label}>
                            {(props) => {
                                if (filter.tipe === 'select') {
                                    return (
                                        <Select
                                            {...props}
                                            options={filter.options ?? []}
                                            placeholder={filter.placeholder ?? 'Semua'}
                                            value={nilai[filter.kunci] ?? ''}
                                            onChange={(e) => onChange(filter.kunci, e.target.value)}
                                        />
                                    );
                                }

                                if (filter.tipe === 'tree') {
                                    const nilaiUnit = nilai[filter.kunci]
                                        ? Number(nilai[filter.kunci])
                                        : null;

                                    return (
                                        <UnitTreeSelect
                                            {...props}
                                            units={filter.treeUnits ?? []}
                                            nilai={nilaiUnit}
                                            onChange={(id) =>
                                                onChange(filter.kunci, id === null ? '' : String(id))
                                            }
                                        />
                                    );
                                }

                                if (filter.tipe === 'user') {
                                    return (
                                        <UserFilterSelect
                                            {...props}
                                            searchUrl={filter.userSearchUrl ?? ''}
                                            nilai={filter.userValue ?? null}
                                            onChange={(pengguna) =>
                                                onChange(filter.kunci, pengguna === null ? '' : String(pengguna.id))
                                            }
                                        />
                                    );
                                }

                                return (
                                    <Input
                                        {...props}
                                        type="date"
                                        value={nilai[filter.kunci] ?? ''}
                                        onChange={(e) => onChange(filter.kunci, e.target.value)}
                                    />
                                );
                            }}
                        </Field>
                    ))}
                </div>
            )}

            {chips.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    {chips.map((chip) => (
                        <button
                            key={chip.kunci}
                            type="button"
                            onClick={() => onHapusChip(chip.kunci)}
                            className="inline-flex min-h-touch items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-sm text-ink-muted transition-colors hover:border-danger/30 hover:bg-danger-soft hover:text-danger sm:min-h-8"
                        >
                            <span aria-hidden>{chip.label}</span>
                            <X className="size-3.5" aria-hidden />
                            <span className="sr-only">Hapus filter {chip.label}</span>
                        </button>
                    ))}

                </div>
            )}
        </div>
    );
}

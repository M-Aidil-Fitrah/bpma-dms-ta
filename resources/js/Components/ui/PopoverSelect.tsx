import { cn } from '@/lib/cn';
import { ChevronDown } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';

export interface PopoverSelectOption {
    value: string | number;
    label: string;
}

export interface PopoverSelectProps {
    options: readonly PopoverSelectOption[];
    value: string | number;
    onChange: (value: string) => void;
    placeholder?: string;
    id?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
}

/**
 * Dropdown bergaya sama persis dengan `UnitTreeSelect`, tanpa struktur pohon.
 *
 * `<select>` bawaan browser terlihat konsisten saat tertutup, tapi panel yang
 * terbuka memakai tampilan asli OS/peramban — tidak selaras dengan sisa
 * antarmuka. Komponen ini dipakai di tempat yang memang menginginkan panel
 * bergaya aplikasi (mis. bilah penyaring), bukan pengganti `Select` di
 * seluruh aplikasi.
 */
export function PopoverSelect({
    options,
    value,
    onChange,
    placeholder,
    id,
    'aria-describedby': describedBy,
    'aria-invalid': invalid = false,
}: PopoverSelectProps) {
    const [menuTerbuka, setMenuTerbuka] = useState(false);
    const pembungkus = useRef<HTMLDivElement>(null);
    const panelId = useId();
    const pilihan = options.find((opsi) => String(opsi.value) === String(value));

    useEffect(() => {
        function tutupJikaDiLuar(event: PointerEvent) {
            if (!pembungkus.current?.contains(event.target as Node)) {
                setMenuTerbuka(false);
            }
        }

        document.addEventListener('pointerdown', tutupJikaDiLuar);

        return () => document.removeEventListener('pointerdown', tutupJikaDiLuar);
    }, []);

    function pilih(nilai: string) {
        onChange(nilai);
        setMenuTerbuka(false);
    }

    return (
        <div ref={pembungkus} className="relative">
            <button
                id={id}
                type="button"
                role="combobox"
                aria-haspopup="listbox"
                aria-controls={panelId}
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
                <span className="truncate">{pilihan?.label ?? placeholder ?? ''}</span>
                <ChevronDown
                    className={cn('size-4 shrink-0 text-ink-subtle transition-transform', menuTerbuka && 'rotate-180')}
                    aria-hidden
                />
            </button>

            {menuTerbuka && (
                <div
                    id={panelId}
                    role="listbox"
                    className="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-line bg-surface p-2 shadow-pop"
                >
                    {placeholder && (
                        <BarisPilih label={placeholder} terpilih={value === ''} onClick={() => pilih('')} />
                    )}

                    {options.map((opsi) => (
                        <BarisPilih
                            key={opsi.value}
                            label={opsi.label}
                            terpilih={String(opsi.value) === String(value)}
                            onClick={() => pilih(String(opsi.value))}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function BarisPilih({ label, terpilih, onClick }: { label: string; terpilih: boolean; onClick: () => void }) {
    return (
        <button
            type="button"
            role="option"
            aria-selected={terpilih}
            onClick={onClick}
            className={cn(
                'flex min-h-touch w-full min-w-0 items-center gap-2 rounded px-2 py-1.5 text-left text-sm transition-colors sm:min-h-8',
                terpilih ? 'bg-brand-50 text-brand-700' : 'text-ink-muted hover:bg-surface-sunken',
            )}
        >
            <span className="min-w-0 break-words">{label}</span>
        </button>
    );
}

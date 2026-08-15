import { cn } from '@/lib/cn';
import { LayoutGrid, List } from 'lucide-react';

export type ModeTampilan = 'tabel' | 'grid';

export interface ViewToggleProps {
    nilai: ModeTampilan;
    onChange: (mode: ModeTampilan) => void;
}

const PILIHAN = [
    { mode: 'tabel', label: 'Tampilan tabel', icon: List },
    { mode: 'grid', label: 'Tampilan kartu', icon: LayoutGrid },
] as const;

/**
 * Pengalih antara tampilan tabel dan kartu.
 *
 * Pilihannya disimpan di query string oleh pemanggil, bukan di state komponen —
 * supaya bertahan setelah halaman disegarkan dan ikut terbawa saat alamatnya
 * dibagikan, sama seperti penyaring.
 */
export function ViewToggle({ nilai, onChange }: ViewToggleProps) {
    return (
        <div
            role="group"
            aria-label="Mode tampilan"
            className="flex shrink-0 rounded-lg border border-line bg-white p-0.5"
        >
            {PILIHAN.map(({ mode, label, icon: Icon }) => (
                <button
                    key={mode}
                    type="button"
                    onClick={() => onChange(mode)}
                    aria-label={label}
                    aria-pressed={nilai === mode}
                    className={cn(
                        'inline-flex min-h-touch min-w-touch items-center justify-center rounded-md transition-colors sm:min-h-9 sm:min-w-9',
                        nilai === mode
                            ? 'bg-brand-700 text-white'
                            : 'text-ink-muted hover:bg-surface-sunken hover:text-ink',
                    )}
                >
                    <Icon className="size-4" aria-hidden />
                </button>
            ))}
        </div>
    );
}

import { useDebounce } from '@/hooks/useDebounce';
import { cn } from '@/lib/cn';
import { ChevronDown, Loader2, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

export interface PenggunaFilterPilihan {
    id: number;
    nama: string;
    jabatan: string | null;
    unit: string | null;
}

export interface UserFilterSelectProps {
    nilai: PenggunaFilterPilihan | null;
    onChange: (pengguna: PenggunaFilterPilihan | null) => void;
    searchUrl: string;
    id?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
}

/**
 * Pemilih SATU pengguna untuk filter (mis. "pelaku" pada log aktivitas
 * admin) — beda dari `UserPicker` yang memilih banyak orang sekaligus untuk
 * mekanisme akses dokumen.
 */
export function UserFilterSelect({
    nilai,
    onChange,
    searchUrl,
    id,
    'aria-describedby': describedBy,
    'aria-invalid': invalid = false,
}: UserFilterSelectProps) {
    const { t } = useTranslation(['users', 'common']);
    const [menuTerbuka, setMenuTerbuka] = useState(false);
    const [kata, setKata] = useState('');
    const [hasil, setHasil] = useState<PenggunaFilterPilihan[]>([]);
    const [mencari, setMencari] = useState(false);
    const ditunda = useDebounce(kata, 300);
    const pembungkus = useRef<HTMLDivElement>(null);
    const permintaanRef = useRef<AbortController | null>(null);

    useEffect(() => {
        function tutupJikaDiLuar(event: PointerEvent) {
            if (!pembungkus.current?.contains(event.target as Node)) {
                setMenuTerbuka(false);
            }
        }

        document.addEventListener('pointerdown', tutupJikaDiLuar);

        return () => document.removeEventListener('pointerdown', tutupJikaDiLuar);
    }, []);

    useEffect(() => {
        if (ditunda.trim().length < 2) {
            setHasil([]);
            setMencari(false);

            return;
        }

        permintaanRef.current?.abort();
        const controller = new AbortController();
        permintaanRef.current = controller;
        setMencari(true);

        fetch(`${searchUrl}?cari=${encodeURIComponent(ditunda)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((data: PenggunaFilterPilihan[]) => setHasil(data))
            .catch(() => {
                // Permintaan yang dibatalkan bukan kegagalan — tidak perlu
                // ditampilkan sebagai galat kepada pengguna.
            })
            .finally(() => {
                if (!controller.signal.aborted) setMencari(false);
            });

        return () => controller.abort();
    }, [ditunda, searchUrl]);

    function pilih(pengguna: PenggunaFilterPilihan | null) {
        onChange(pengguna);
        setKata('');
        setHasil([]);
        setMenuTerbuka(false);
    }

    return (
        <div ref={pembungkus} className="relative">
            <button
                id={id}
                type="button"
                aria-haspopup="listbox"
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
                <span className="truncate">{nilai?.nama ?? t('users:filterSelect.allUsers')}</span>
                <ChevronDown
                    className={cn('size-4 shrink-0 text-ink-subtle transition-transform', menuTerbuka && 'rotate-180')}
                    aria-hidden
                />
            </button>

            {menuTerbuka && (
                <div className="absolute z-30 mt-1 w-full rounded-lg border border-line bg-surface p-2 shadow-pop">
                    <button
                        type="button"
                        onClick={() => pilih(null)}
                        aria-pressed={nilai === null}
                        className={cn(
                            'mb-1 flex min-h-touch w-full items-center rounded px-2 py-1.5 text-left text-sm font-medium transition-colors sm:min-h-8',
                            nilai === null ? 'bg-brand-50 text-brand-700' : 'text-ink-muted hover:bg-surface-sunken',
                        )}
                    >
                        {t('users:filterSelect.allUsers')}
                    </button>

                    <div className="relative">
                        <input
                            type="search"
                            value={kata}
                            placeholder={t('users:filterSelect.searchPlaceholder')}
                            aria-label={t('users:filterSelect.searchAriaLabel')}
                            onChange={(e) => setKata(e.target.value)}
                            className="h-9 w-full rounded-lg border border-line bg-surface px-8 text-sm text-ink focus:border-brand-700 focus:outline-none focus:ring-1 focus:ring-brand-700 [&::-webkit-search-cancel-button]:appearance-none"
                        />
                        <Search className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-ink-subtle" aria-hidden />
                        {mencari && (
                            <Loader2 className="absolute right-2.5 top-1/2 size-4 -translate-y-1/2 animate-spin text-ink-subtle" aria-hidden />
                        )}
                    </div>

                    {kata.trim().length >= 2 && !mencari && hasil.length === 0 && (
                        <p className="px-2 py-2 text-sm text-ink-subtle">{t('users:filterSelect.noResults')}</p>
                    )}

                    {hasil.length > 0 && (
                        <ul className="mt-1 max-h-52 overflow-y-auto">
                            {hasil.map((pengguna) => (
                                <li key={pengguna.id}>
                                    <button
                                        type="button"
                                        onClick={() => pilih(pengguna)}
                                        aria-pressed={nilai?.id === pengguna.id}
                                        className={cn(
                                            'flex min-h-touch w-full flex-col items-start rounded px-2 py-1.5 text-left text-sm transition-colors sm:min-h-8',
                                            nilai?.id === pengguna.id ? 'bg-brand-50 text-brand-700' : 'text-ink-muted hover:bg-surface-sunken',
                                        )}
                                    >
                                        <span className="truncate text-ink">{pengguna.nama}</span>
                                        <span className="truncate text-xs text-ink-subtle">
                                            {[pengguna.jabatan, pengguna.unit].filter(Boolean).join(' · ') || '—'}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}

import { Avatar } from '@/Components/ui/Avatar';
import { IconButton } from '@/Components/ui/IconButton';
import { Input } from '@/Components/ui/Input';
import { useDebounce } from '@/hooks/useDebounce';
import { Loader2, Search, UserPlus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface PenggunaTerpilih {
    id: number;
    nama: string;
    jabatan: string | null;
    unit: string | null;
}

export interface UserPickerProps {
    terpilih: readonly PenggunaTerpilih[];
    onChange: (pengguna: PenggunaTerpilih[]) => void;
}

/**
 * Pencari orang untuk mekanisme akses "orang tertentu" (FR-41).
 *
 * Hasil pencarian menampilkan jabatan dan unit, bukan nama saja. Pada
 * organisasi sebesar BPMA, nama mirip di unit berbeda adalah hal biasa — dan
 * salah pilih berarti dokumen terbuka bagi orang yang keliru.
 */
export function UserPicker({ terpilih, onChange }: UserPickerProps) {
    const [kata, setKata] = useState('');
    const [hasil, setHasil] = useState<PenggunaTerpilih[]>([]);
    const [mencari, setMencari] = useState(false);
    const ditunda = useDebounce(kata, 300);

    // Menahan permintaan yang sedang berjalan: mengetik cepat menghasilkan
    // beberapa permintaan sekaligus, dan yang lebih dulu berangkat belum tentu
    // lebih dulu tiba — hasil lama bisa menimpa hasil baru.
    const permintaanRef = useRef<AbortController | null>(null);

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

        fetch(`/documents/cari-pengguna?cari=${encodeURIComponent(ditunda)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((data: PenggunaTerpilih[]) => setHasil(data))
            .catch(() => {
                // Permintaan yang dibatalkan bukan kegagalan — tidak perlu
                // ditampilkan sebagai galat kepada pengguna.
            })
            .finally(() => {
                if (!controller.signal.aborted) setMencari(false);
            });

        return () => controller.abort();
    }, [ditunda]);

    const idTerpilih = new Set(terpilih.map((p) => p.id));
    const tersedia = hasil.filter((p) => !idTerpilih.has(p.id));

    return (
        <div className="space-y-2">
            <div className="relative">
                <Input
                    type="search"
                    icon={Search}
                    value={kata}
                    placeholder="Ketik nama, minimal 2 huruf…"
                    aria-label="Cari pengguna"
                    onChange={(e) => setKata(e.target.value)}
                    className="[&::-webkit-search-cancel-button]:appearance-none"
                />
                {mencari && (
                    <Loader2
                        className="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-ink-subtle"
                        aria-hidden
                    />
                )}
            </div>

            {kata.trim().length >= 2 && !mencari && tersedia.length === 0 && (
                <p className="px-1 text-sm text-ink-subtle">
                    Tidak ada pengguna aktif yang cocok.
                </p>
            )}

            {tersedia.length > 0 && (
                <ul className="max-h-52 divide-y divide-line overflow-y-auto rounded-lg border border-line">
                    {tersedia.map((pengguna) => (
                        <li key={pengguna.id}>
                            <button
                                type="button"
                                onClick={() => {
                                    onChange([...terpilih, pengguna]);
                                    setKata('');
                                }}
                                className="flex min-h-touch w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-surface-sunken"
                            >
                                <Avatar initials={inisial(pengguna.nama)} size="sm" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm text-ink">
                                        {pengguna.nama}
                                    </span>
                                    <span className="block truncate text-xs text-ink-subtle">
                                        {[pengguna.jabatan, pengguna.unit]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </span>
                                </span>
                                <UserPlus className="size-4 shrink-0 text-ink-subtle" aria-hidden />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {terpilih.length > 0 && (
                <ul className="space-y-1.5">
                    {terpilih.map((pengguna) => (
                        <li
                            key={pengguna.id}
                            className="flex items-center gap-2 rounded-lg bg-brand-50 px-3 py-2"
                        >
                            <Avatar initials={inisial(pengguna.nama)} size="sm" />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium text-ink">
                                    {pengguna.nama}
                                </span>
                                <span className="block truncate text-xs text-ink-muted">
                                    {[pengguna.jabatan, pengguna.unit]
                                        .filter(Boolean)
                                        .join(' · ') || '—'}
                                </span>
                            </span>
                            <IconButton
                                icon={X}
                                label={`Hapus ${pengguna.nama}`}
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    onChange(terpilih.filter((p) => p.id !== pengguna.id))
                                }
                            />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/** Inisial dihitung di sini karena hasil pencarian tidak melewati DTO backend. */
function inisial(nama: string): string {
    return (
        nama
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((k) => k[0]?.toUpperCase() ?? '')
            .join('') || '?'
    );
}

import { Avatar } from '@/Components/ui/Avatar';
import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Textarea } from '@/Components/ui/Textarea';
import { cn } from '@/lib/cn';
import { formatWaktu } from '@/lib/format';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/react';
import { Link, useForm } from '@inertiajs/react';
import { ChevronDown, Download, Eye, History, RotateCcw } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface DocumentVersionHistoryProps {
    versi: App.Data.DocumentVersionData[];
    bolehPulihkan: boolean;
}

/** Daftar revisi yang memilih ulang halaman pratinjau, bukan menyalin viewer. */
export function DocumentVersionHistory({ versi, bolehPulihkan }: DocumentVersionHistoryProps) {
    const [batasTampil, setBatasTampil] = useState(5);
    const { data, setData, post, processing, errors } = useForm({ version_note: '' });
    const versiDitampilkan = versi.slice(0, batasTampil);

    function pulihkan(event: FormEvent, id: number) {
        event.preventDefault();
        post(`/documents/${id}/restore-version`);
    }

    return (
        <div className="space-y-4" id="riwayat-versi">
            <ol className="space-y-2">
                {versiDitampilkan.map((item) => (
                    <Disclosure as="li" key={item.id}>
                        {({ open }) => (
                            <>
                                <DisclosureButton className="flex min-h-touch w-full items-center justify-between gap-3 rounded-lg border border-line bg-surface px-3 py-2 text-left hover:bg-surface-sunken focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700 sm:min-h-0">
                                    <span className="flex min-w-0 items-center gap-2">
                                        <span className="rounded-full bg-brand-100 px-2 py-0.5 font-mono text-xs font-semibold text-brand-700">
                                            {item.label}
                                        </span>
                                        <span className="truncate text-sm text-ink-muted">{item.catatan}</span>
                                        {item.terbaru && (
                                            <span className="hidden shrink-0 text-xs font-medium text-brand-700 sm:inline">Terbaru</span>
                                        )}
                                    </span>
                                    <ChevronDown
                                        className={cn('size-4 shrink-0 text-ink-subtle transition-transform', open && 'rotate-180')}
                                        aria-hidden
                                    />
                                </DisclosureButton>

                                <DisclosurePanel
                                    transition
                                    className="origin-top pt-2 transition duration-200 ease-out data-[closed]:-translate-y-1 data-[closed]:opacity-0"
                                >
                                    <article className="rounded-lg border border-line bg-surface p-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="rounded-full bg-brand-100 px-2 py-0.5 font-mono text-xs font-semibold text-brand-700">
                                                        {item.label}
                                                    </span>
                                                    {item.terbaru && (
                                                        <span className="text-xs font-medium text-brand-700">Versi terbaru</span>
                                                    )}
                                                </div>
                                                <p className="mt-2 text-sm font-medium text-ink">{labelJenis(item.jenis)}</p>
                                                <p className="mt-0.5 whitespace-pre-wrap text-sm text-ink-muted">{item.catatan}</p>
                                            </div>
                                            <Avatar initials={item.inisial_pembuat} name={item.pembuat ?? undefined} size="sm" />
                                        </div>

                                        <p className="mt-3 break-all font-mono text-xs text-ink-muted">{item.nama_berkas}</p>
                                        <p className="mt-1 text-xs text-ink-subtle">
                                            {item.pembuat ?? 'Pengguna tidak diketahui'} · {formatWaktu(item.dibuat_pada)}
                                        </p>

                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <Link href={`/documents/${item.id}#riwayat`} className="inline-flex">
                                                <Button size="sm" variant="ghost" icon={Eye}>
                                                    Lihat
                                                </Button>
                                            </Link>
                                            <a href={`/documents/${item.id}/file`} className="inline-flex">
                                                <Button size="sm" variant="ghost" icon={Download}>
                                                    Unduh
                                                </Button>
                                            </a>
                                        </div>
                                    </article>

                                    {bolehPulihkan && !item.terbaru && (
                                        <form
                                            onSubmit={(event) => pulihkan(event, item.id)}
                                            className="mt-2 space-y-3 rounded-lg border border-brand-200 bg-brand-50 p-3"
                                        >
                                            <div className="flex items-center gap-2 text-sm font-semibold text-brand-800">
                                                <RotateCcw className="size-4" aria-hidden />
                                                Jadikan {item.label} versi terbaru
                                            </div>
                                            <p className="text-xs text-ink-muted">
                                                Sistem membuat major baru dari snapshot ini. Arsip {item.label} tidak berubah.
                                            </p>
                                            <Field label="Catatan pemulihan" error={errors.version_note} required>
                                                {(props) => (
                                                    <Textarea
                                                        {...props}
                                                        rows={3}
                                                        value={data.version_note}
                                                        invalid={Boolean(errors.version_note)}
                                                        onChange={(event) => setData('version_note', event.target.value)}
                                                        placeholder="Alasan menjadikan versi ini sebagai versi terbaru"
                                                    />
                                                )}
                                            </Field>
                                            <Button type="submit" size="sm" icon={RotateCcw} loading={processing}>
                                                Jadikan versi terbaru
                                            </Button>
                                        </form>
                                    )}
                                </DisclosurePanel>
                            </>
                        )}
                    </Disclosure>
                ))}
            </ol>

            {versi.length === 0 && (
                <div className="py-6 text-center text-sm text-ink-muted">
                    <History className="mx-auto mb-2 size-5" aria-hidden />
                    Belum ada versi dokumen.
                </div>
            )}

            <KontrolTampilkanLebihBanyak
                jumlahTampil={batasTampil}
                jumlahTotal={versi.length}
                onTampilkanLagi={() => setBatasTampil((batas) => batas + 5)}
                onTampilkanSemua={() => setBatasTampil(versi.length)}
            />
        </div>
    );
}

export function KontrolTampilkanLebihBanyak({
    jumlahTampil,
    jumlahTotal,
    onTampilkanLagi,
    onTampilkanSemua,
}: {
    jumlahTampil: number;
    jumlahTotal: number;
    onTampilkanLagi: () => void;
    onTampilkanSemua: () => void;
}) {
    if (jumlahTampil >= jumlahTotal) return null;

    return (
        <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="secondary" onClick={onTampilkanLagi}>
                Tampilkan {Math.min(5, jumlahTotal - jumlahTampil)} lagi
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={onTampilkanSemua}>
                Tampilkan semua
            </Button>
        </div>
    );
}

function labelJenis(jenis: App.Enums.DocumentVersionKind): string {
    return {
        content: 'Perubahan isi berkas',
        metadata: 'Perubahan metadata atau akses',
        restoration: 'Pemulihan versi arsip',
    }[jenis];
}

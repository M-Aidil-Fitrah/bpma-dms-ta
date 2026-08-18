import { Avatar } from '@/Components/ui/Avatar';
import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Textarea } from '@/Components/ui/Textarea';
import { cn } from '@/lib/cn';
import { formatWaktu } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { Download, Eye, History, RotateCcw } from 'lucide-react';
import { type FormEvent } from 'react';

interface DocumentVersionHistoryProps {
    versi: App.Data.DocumentVersionData[];
    bolehPulihkan: boolean;
}

/** Daftar revisi yang memilih ulang halaman pratinjau, bukan menyalin viewer. */
export function DocumentVersionHistory({ versi, bolehPulihkan }: DocumentVersionHistoryProps) {
    const dipilih = versi.find((item) => item.dipilih);
    const { data, setData, post, processing, errors } = useForm({ version_note: '' });

    function pulihkan(event: FormEvent) {
        event.preventDefault();

        if (dipilih) {
            post(`/documents/${dipilih.id}/restore-version`);
        }
    }

    return (
        <div className="space-y-4" id="riwayat-versi">
            <p className="text-xs text-ink-muted">
                Pilih versi untuk membuka pratinjau, metadata, dan berkas yang tersimpan pada saat itu.
            </p>

            <ol className="space-y-3">
                {versi.map((item) => (
                    <li
                        key={item.id}
                        className={cn(
                            'rounded-lg border p-3',
                            item.dipilih ? 'border-brand-400 bg-brand-50' : 'border-line bg-surface',
                        )}
                    >
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
                                <Button size="sm" variant={item.dipilih ? 'secondary' : 'ghost'} icon={Eye}>
                                    Lihat
                                </Button>
                            </Link>
                            <a href={`/documents/${item.id}/file`} className="inline-flex">
                                <Button size="sm" variant="ghost" icon={Download}>
                                    Unduh
                                </Button>
                            </a>
                        </div>
                    </li>
                ))}
            </ol>

            {dipilih && bolehPulihkan && !dipilih.terbaru && (
                <form onSubmit={pulihkan} className="space-y-3 rounded-lg border border-brand-200 bg-brand-50 p-3">
                    <div className="flex items-center gap-2 text-sm font-semibold text-brand-800">
                        <RotateCcw className="size-4" aria-hidden />
                        Jadikan {dipilih.label} versi terbaru
                    </div>
                    <p className="text-xs text-ink-muted">
                        Sistem membuat major baru dari snapshot ini. Arsip {dipilih.label} tidak berubah.
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

            {versi.length === 0 && (
                <div className="py-6 text-center text-sm text-ink-muted">
                    <History className="mx-auto mb-2 size-5" aria-hidden />
                    Belum ada versi dokumen.
                </div>
            )}
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

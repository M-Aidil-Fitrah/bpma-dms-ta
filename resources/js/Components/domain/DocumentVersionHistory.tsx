import { Avatar } from '@/Components/ui/Avatar';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Textarea } from '@/Components/ui/Textarea';
import { cn } from '@/lib/cn';
import { formatWaktu } from '@/lib/format';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/react';
import { Link, useForm } from '@inertiajs/react';
import { ChevronDown, Download, Eye, History, RotateCcw } from 'lucide-react';
import type { TFunction } from 'i18next';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

interface DocumentVersionHistoryProps {
    versi: App.Data.DocumentVersionData[];
    bolehPulihkan: boolean;
}

/** Daftar revisi yang memilih ulang halaman pratinjau, bukan menyalin viewer. */
export function DocumentVersionHistory({ versi, bolehPulihkan }: DocumentVersionHistoryProps) {
    const { t } = useTranslation(['documentBrowse', 'common']);
    const konfirmasikan = usePasswordConfirmation();
    const [batasTampil, setBatasTampil] = useState(5);
    const { data, setData, post, processing, errors } = useForm({ version_note: '' });
    const versiDitampilkan = versi.slice(0, batasTampil);

    function pulihkan(event: FormEvent, id: number) {
        event.preventDefault();
        konfirmasikan(() => post(`/documents/${id}/restore-version`));
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
                                            <span className="hidden shrink-0 text-xs font-medium text-brand-700 sm:inline">{t('documentBrowse:versionHistory.terbaru')}</span>
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
                                                        <span className="text-xs font-medium text-brand-700">{t('documentBrowse:versionHistory.versiTerbaru')}</span>
                                                    )}
                                                </div>
                                                <p className="mt-2 text-sm font-medium text-ink">{labelJenis(item.jenis, t)}</p>
                                                <p className="mt-0.5 whitespace-pre-wrap text-sm text-ink-muted">{item.catatan}</p>
                                            </div>
                                            <Avatar initials={item.inisial_pembuat} name={item.pembuat ?? undefined} size="sm" />
                                        </div>

                                        <p className="mt-3 break-all font-mono text-xs text-ink-muted">{item.nama_berkas}</p>
                                        <p className="mt-1 text-xs text-ink-subtle">
                                            {item.pembuat ?? t('documentBrowse:versionHistory.penggunaTidakDiketahui')} · {formatWaktu(item.dibuat_pada)}
                                        </p>

                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <Link href={`/documents/${item.id}#riwayat`} className="inline-flex">
                                                <Button size="sm" variant="ghost" icon={Eye}>
                                                    {t('documentBrowse:versionHistory.lihat')}
                                                </Button>
                                            </Link>
                                            <a href={`/documents/${item.id}/file`} className="inline-flex">
                                                <Button size="sm" variant="ghost" icon={Download}>
                                                    {t('common:aksi.unduh')}
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
                                                {t('documentBrowse:versionHistory.jadikanVersiTerbaru', { label: item.label })}
                                            </div>
                                            <p className="text-xs text-ink-muted">
                                                {t('documentBrowse:versionHistory.keteranganPemulihan', { label: item.label })}
                                            </p>
                                            <Field label={t('documentBrowse:versionHistory.labelCatatanPemulihan')} error={errors.version_note} required>
                                                {(props) => (
                                                    <Textarea
                                                        {...props}
                                                        rows={3}
                                                        value={data.version_note}
                                                        invalid={Boolean(errors.version_note)}
                                                        onChange={(event) => setData('version_note', event.target.value)}
                                                        placeholder={t('documentBrowse:versionHistory.placeholderCatatanPemulihan')}
                                                    />
                                                )}
                                            </Field>
                                            <Button type="submit" size="sm" icon={RotateCcw} loading={processing}>
                                                {t('documentBrowse:versionHistory.submitJadikanVersiTerbaru')}
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
                    {t('documentBrowse:versionHistory.belumAdaVersi')}
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
    const { t } = useTranslation('documentBrowse');

    if (jumlahTampil >= jumlahTotal) return null;

    return (
        <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="secondary" onClick={onTampilkanLagi}>
                {t('documentBrowse:versionHistory.tampilkanLagi', { jumlah: Math.min(5, jumlahTotal - jumlahTampil) })}
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={onTampilkanSemua}>
                {t('documentBrowse:versionHistory.tampilkanSemua')}
            </Button>
        </div>
    );
}

function labelJenis(jenis: App.Enums.DocumentVersionKind, t: TFunction): string {
    return {
        content: t('documentBrowse:versionHistory.jenis.content'),
        metadata: t('documentBrowse:versionHistory.jenis.metadata'),
        restoration: t('documentBrowse:versionHistory.jenis.restoration'),
    }[jenis];
}

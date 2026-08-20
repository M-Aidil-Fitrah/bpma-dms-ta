import { AccessSummary } from '@/Components/domain/AccessSummary';
import { Badge } from '@/Components/ui/Badge';
import { cn } from '@/lib/cn';
import { Check, Globe, LockKeyhole, PenLine, ShieldCheck, UserRoundCheck, Users, type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

type DokumenAkses = Pick<
    App.Data.DocumentDetailData,
    'ringkasan_akses' | 'dibagikan_ke_semua' | 'min_tingkat_akses' | 'unit_tujuan' | 'orang_tertentu' | 'edit_scope' | 'label_edit_scope'
>;

/**
 * Ringkasan akses baca dan wewenang ubah pada detail dokumen.
 *
 * Seluruh empat jalur selalu tampil. Pengguna dapat membedakan jalur yang
 * memang tidak aktif dari jalur yang kebetulan tidak memiliki sasaran.
 */
export function DocumentAccessPanel({ dokumen }: { dokumen: DokumenAkses }) {
    const jumlahAktif = [
        dokumen.dibagikan_ke_semua,
        dokumen.min_tingkat_akses !== null,
        dokumen.unit_tujuan.length > 0,
        dokumen.orang_tertentu.length > 0,
    ].filter(Boolean).length;

    return (
        <div className="space-y-5" id="akses">
            <section className="rounded-card border border-brand-200 bg-brand-50 p-4">
                <div className="flex items-start gap-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface text-brand-700 shadow-sm">
                        <ShieldCheck className="size-5" aria-hidden />
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-brand-700">Akses dokumen</p>
                                <h2 className="mt-0.5 text-sm font-semibold text-ink">Siapa yang dapat membuka</h2>
                            </div>
                            <Badge variant="brand" size="sm">{jumlahAktif} aktif</Badge>
                        </div>
                        <p className="mt-2 text-xs leading-relaxed text-ink-muted">
                            Akses berlaku bila pengguna memenuhi salah satu mekanisme aktif di bawah.
                        </p>
                        <div className="mt-3">
                            <AccessSummary ringkasan={dokumen.ringkasan_akses} />
                        </div>
                    </div>
                </div>
            </section>

            <section aria-labelledby="jalur-akses">
                <div className="mb-2 flex items-center justify-between gap-3">
                    <h2 id="jalur-akses" className="text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                        Jalur akses
                    </h2>
                    <span className="text-xs text-ink-subtle">4 mekanisme</span>
                </div>

                <ul className="space-y-2">
                    <MekanismeAkses
                        aktif={dokumen.dibagikan_ke_semua}
                        icon={Globe}
                        judul="Bagikan ke semua"
                        keterangan="Seluruh pengguna internal dapat melihat dokumen ini."
                    />
                    <MekanismeAkses
                        aktif={dokumen.min_tingkat_akses !== null}
                        icon={ShieldCheck}
                        judul="Bagikan ke jenjang jabatan"
                        keterangan="Berlaku lintas unit kerja."
                        detail={dokumen.min_tingkat_akses !== null && <Badge variant="brand" size="sm">Tingkat {dokumen.min_tingkat_akses} ke atas</Badge>}
                    />
                    <MekanismeAkses
                        aktif={dokumen.unit_tujuan.length > 0}
                        icon={Users}
                        judul="Bagikan ke unit"
                        keterangan={dokumen.unit_tujuan.length > 0 ? `${dokumen.unit_tujuan.length} unit dapat melihat dokumen ini.` : 'Belum ada unit yang dituju.'}
                        detail={<DaftarSasaran sasaran={dokumen.unit_tujuan} />}
                    />
                    <MekanismeAkses
                        aktif={dokumen.orang_tertentu.length > 0}
                        icon={UserRoundCheck}
                        judul="Bagikan ke orang tertentu"
                        keterangan={dokumen.orang_tertentu.length > 0 ? `${dokumen.orang_tertentu.length} orang mendapat akses langsung.` : 'Belum ada orang yang ditunjuk.'}
                        detail={<DaftarSasaran sasaran={dokumen.orang_tertentu} />}
                    />
                </ul>
            </section>

            <section aria-labelledby="jalur-wewenang-ubah">
                <h2 id="jalur-wewenang-ubah" className="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                    Wewenang mengubah
                </h2>
                <div className="rounded-card border border-line bg-surface-sunken p-4">
                    <div className="flex items-start gap-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface text-ink-muted shadow-sm">
                            {dokumen.edit_scope === 'owner_only' ? <LockKeyhole className="size-4" aria-hidden /> : <PenLine className="size-4" aria-hidden />}
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-sm font-semibold text-ink">Siapa yang dapat mengubah</p>
                                <Badge variant="neutral" size="sm">{dokumen.label_edit_scope}</Badge>
                            </div>
                            <p className="mt-1 text-xs leading-relaxed text-ink-muted">
                                {dokumen.edit_scope === 'owner_only'
                                    ? 'Hanya pengunggah yang dapat mengubah dokumen ini.'
                                    : 'Pengguna yang dapat melihat dokumen ini juga dapat mengubahnya.'}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}

function MekanismeAkses({
    aktif,
    icon: Icon,
    judul,
    keterangan,
    detail,
}: {
    aktif: boolean;
    icon: LucideIcon;
    judul: string;
    keterangan: string;
    detail?: ReactNode;
}) {
    return (
        <li className={cn('rounded-card border p-3 transition-colors', aktif ? 'border-brand-200 bg-brand-50/40' : 'border-line bg-surface')}>
            <div className="flex items-start gap-3">
                <span className={cn('mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md', aktif ? 'bg-brand-100 text-brand-700' : 'bg-surface-sunken text-ink-subtle')}>
                    <Icon className="size-4" aria-hidden />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className={cn('text-sm font-medium', aktif ? 'text-brand-700' : 'text-ink')}>{judul}</p>
                        <Badge variant={aktif ? 'brand' : 'neutral'} size="sm">
                            {aktif && <Check className="size-3" aria-hidden />}
                            {aktif ? 'Aktif' : 'Nonaktif'}
                        </Badge>
                    </div>
                    <p className="mt-0.5 text-xs leading-relaxed text-ink-muted">{keterangan}</p>
                    {aktif && detail && <div className="mt-2">{detail}</div>}
                </div>
            </div>
        </li>
    );
}

function DaftarSasaran({ sasaran }: { sasaran: readonly string[] }) {
    if (sasaran.length === 0) return null;

    return (
        <ul className="flex flex-wrap gap-1.5" aria-label="Sasaran akses">
            {sasaran.map((nama, index) => (
                <li key={`${nama}-${index}`} className="rounded-md bg-surface px-2 py-1 text-xs text-ink-muted ring-1 ring-inset ring-brand-700/10">
                    {nama}
                </li>
            ))}
        </ul>
    );
}

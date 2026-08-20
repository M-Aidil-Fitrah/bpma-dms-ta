import { Badge } from '@/Components/ui/Badge';
import { BriefcaseBusiness, Building2, Check, Globe, LockKeyhole, PenLine, ShieldCheck, UserRound, UserRoundCheck, type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

type DokumenAkses = Pick<
    App.Data.DocumentDetailData,
    'dibagikan_ke_semua' | 'min_tingkat_akses' | 'unit_tujuan' | 'jabatan_tujuan' | 'orang_tertentu' | 'edit_scope' | 'label_edit_scope'
>;

/** Ringkasan akses baca dan wewenang ubah pada detail dokumen. */
export function DocumentAccessPanel({ dokumen }: { dokumen: DokumenAkses }) {
    const kandidatMekanisme: Array<Mekanisme | false> = [
        dokumen.dibagikan_ke_semua && {
            icon: Globe,
            judul: 'Bagikan ke semua',
            keterangan: 'Seluruh pengguna internal dapat melihat dokumen ini.',
        },
        dokumen.min_tingkat_akses !== null && {
            icon: BriefcaseBusiness,
            judul: 'Bagikan ke jabatan',
            keterangan: 'Berlaku lintas unit kerja.',
            detail: <DaftarJabatan jabatan={dokumen.jabatan_tujuan} />,
        },
        dokumen.unit_tujuan.length > 0 && {
            icon: Building2,
            judul: 'Bagikan ke unit',
            keterangan: `${dokumen.unit_tujuan.length} unit kerja dapat melihat dokumen ini.`,
            detail: <DaftarUnit unit={dokumen.unit_tujuan} />,
        },
        dokumen.orang_tertentu.length > 0 && {
            icon: UserRoundCheck,
            judul: 'Bagikan ke orang tertentu',
            keterangan: `${dokumen.orang_tertentu.length} orang mendapat akses langsung.`,
            detail: <DaftarOrang orang={dokumen.orang_tertentu} />,
        },
    ];
    const mekanisme = kandidatMekanisme.filter((item): item is Mekanisme => item !== false);

    const ringkasan = mekanisme.length === 0
        ? ['Hanya pemilik dokumen yang dapat membuka dokumen ini.']
        : mekanisme.map(({ judul, keterangan }) => `${judul}: ${keterangan}`);

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
                            <Badge variant="brand" size="sm">{mekanisme.length} aktif</Badge>
                        </div>
                        {ringkasan.length > 1 ? (
                            <ul className="mt-3 space-y-1.5" aria-label="Ringkasan akses aktif">
                                {ringkasan.map((item) => (
                                    <li key={item} className="flex items-start gap-2 text-xs leading-relaxed text-ink-muted">
                                        <Check className="mt-0.5 size-3.5 shrink-0 text-brand-700" aria-hidden />
                                        <span>{item}</span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-2 text-xs leading-relaxed text-ink-muted">{ringkasan[0]}</p>
                        )}
                    </div>
                </div>
            </section>

            {mekanisme.length > 0 && (
                <section aria-labelledby="jalur-akses">
                    <div className="mb-2 flex items-center justify-between gap-3">
                        <h2 id="jalur-akses" className="text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            Jalur akses
                        </h2>
                        <span className="text-xs text-ink-subtle">{mekanisme.length} mekanisme aktif</span>
                    </div>

                    <ul className="space-y-2">
                        {mekanisme.map((item) => <MekanismeAkses key={item.judul} {...item} />)}
                    </ul>
                </section>
            )}

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

type Mekanisme = {
    icon: LucideIcon;
    judul: string;
    keterangan: string;
    detail?: ReactNode;
};

function MekanismeAkses({ icon: Icon, judul, keterangan, detail }: Mekanisme) {
    return (
        <li className="rounded-card border border-brand-200 bg-brand-50/40 p-3">
            <div className="flex items-start gap-3">
                <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-brand-100 text-brand-700">
                    <Icon className="size-4" aria-hidden />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-medium text-brand-700">{judul}</p>
                        <Badge variant="brand" size="sm"><Check className="size-3" aria-hidden />Aktif</Badge>
                    </div>
                    <p className="mt-0.5 text-xs leading-relaxed text-ink-muted">{keterangan}</p>
                    {detail && <div className="mt-2">{detail}</div>}
                </div>
            </div>
        </li>
    );
}

function DaftarJabatan({ jabatan }: { jabatan: readonly string[] }) {
    if (jabatan.length === 0) {
        return <p className="text-xs text-ink-muted">Belum ada jabatan aktif yang sesuai dengan jalur ini.</p>;
    }

    return (
        <ul className="space-y-1.5" aria-label="Jabatan yang dapat membuka">
            {jabatan.map((nama) => (
                <li key={nama} className="flex items-center gap-2 text-xs text-ink-muted">
                    <BriefcaseBusiness className="size-3.5 shrink-0 text-brand-700" aria-hidden />
                    {nama}
                </li>
            ))}
        </ul>
    );
}

function DaftarUnit({ unit }: { unit: readonly string[] }) {
    return (
        <ul className="space-y-1.5" aria-label="Unit kerja yang dapat membuka">
            {unit.map((nama) => (
                <li key={nama} className="flex items-center gap-2 text-xs text-ink-muted">
                    <Building2 className="size-3.5 shrink-0 text-brand-700" aria-hidden />
                    {nama}
                </li>
            ))}
        </ul>
    );
}

function DaftarOrang({ orang }: { orang: readonly { nama: string; unit: string | null }[] }) {
    return (
        <ul className="space-y-1.5" aria-label="Orang yang dapat membuka">
            {orang.map((pengguna) => (
                <li key={pengguna.nama} className="flex items-center gap-2 text-xs text-ink-muted">
                    <UserRound className="size-3.5 shrink-0 text-brand-700" aria-hidden />
                    <span className="font-medium text-ink">{pengguna.nama}</span>
                    {pengguna.unit && <span className="text-ink-subtle">· {pengguna.unit}</span>}
                </li>
            ))}
        </ul>
    );
}

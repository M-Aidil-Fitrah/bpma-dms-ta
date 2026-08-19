import { AccessSummary } from '@/Components/domain/AccessSummary';
import { ActivityItem } from '@/Components/domain/ActivityItem';
import { DocumentHeaderActions } from '@/Components/domain/DocumentHeaderActions';
import { DocumentPreview } from '@/Components/domain/DocumentPreview';
import { DocumentVersionHistory } from '@/Components/domain/DocumentVersionHistory';
import { DocumentStatusBadge } from '@/Components/domain/DocumentStatusBadge';
import { ExtractionStatusBadge } from '@/Components/domain/ExtractionStatusBadge';
import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { Alert } from '@/Components/ui/Alert';
import { Avatar } from '@/Components/ui/Avatar';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { useDocumentReloadPolling } from '@/hooks/useDocumentReloadPolling';
import { AppLayout } from '@/Layouts/AppLayout';
import { cn } from '@/lib/cn';
import { dalamJendelaWaktu, formatTanggalPanjang, formatUkuranBerkas, formatWaktu } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { ArrowLeft, FileText, History, Info, ShieldCheck } from 'lucide-react';
import { useState, type ReactNode } from 'react';

interface ShowProps {
    dokumen: App.Data.DocumentDetailData;
    versi: App.Data.DocumentVersionData[];
    riwayat: App.Data.ActivityLogData[];
    pollingKonfigurasi: { jedaMs: number; maksPercobaan: number };
}

type Tab = 'detail' | 'akses' | 'riwayat';

const TAB_VALID: readonly Tab[] = ['detail', 'akses', 'riwayat'];

/**
 * Tab awal mengikuti `location.hash` (mis. tautan menu "Lihat pengaturan
 * akses" mengarah ke `#akses`) — tanpa ini, tab kontennya dirender kondisional
 * sehingga `id="akses"` bahkan tidak ada di DOM saat halaman baru dimuat, dan
 * pengguna selalu mendarat di tab "Detail" berapa pun hash di alamatnya.
 */
function tabDariHash(): Tab {
    const hash = window.location.hash.slice(1);

    return (TAB_VALID as string[]).includes(hash) ? (hash as Tab) : 'detail';
}

/**
 * Batas atas menunggu konversi pratinjau Office. Melewati ini, kartu
 * berhenti menganggapnya "sedang disiapkan" — kemungkinan besar job gagal
 * permanen (perkakas server tidak terpasang) dan tidak akan pernah selesai.
 */
const JENDELA_PRATINJAU_MENIT = 5;

export default function Show({ dokumen, versi, riwayat, pollingKonfigurasi }: ShowProps) {
    const [tab, setTab] = useState<Tab>(tabDariHash);

    const masihMenyiapkanPratinjau =
        dokumen.pratinjau_sedang_disiapkan && dalamJendelaWaktu(dokumen.diunggah_pada, JENDELA_PRATINJAU_MENIT);

    useDocumentReloadPolling(dokumen.extraction_status === 'pending' || masihMenyiapkanPratinjau, pollingKonfigurasi);

    return (
        <AppLayout
            title={dokumen.judul}
            header={<Remah judul={dokumen.judul} />}
            actions={
                <DocumentHeaderActions
                    dokumenId={dokumen.id}
                    judul={dokumen.judul}
                    aktif={dokumen.aktif}
                    bolehUbah={dokumen.boleh_ubah}
                    bolehNonaktifkan={dokumen.boleh_nonaktifkan}
                    bolehAktifkan={dokumen.boleh_aktifkan}
                />
            }
        >
            {!dokumen.aktif && (
                <Alert variant="warning" title="Dokumen ini nonaktif" className="mb-5">
                    Disembunyikan dari daftar dokumen dan hasil pencarian. Anda membukanya
                    lewat riwayat versi. Arsip tidak dapat diubah; pemilik rantai dapat
                    menjadikannya versi terbaru dari panel Riwayat.
                </Alert>
            )}

            <div className="grid gap-5 xl:grid-cols-5">
                {/* Pratinjau mendapat porsi terbesar: itu yang dicari orang saat
                    membuka halaman ini, bukan daftar metadatanya. */}
                <Card className="overflow-hidden xl:col-span-3">
                    <div className="h-[28rem] xl:h-[38rem]">
                        <DocumentPreview dokumen={dokumen} sedangMenyiapkanPratinjau={masihMenyiapkanPratinjau} />
                    </div>
                </Card>

                <Card className="flex flex-col xl:col-span-2">
                    <div className="flex border-b border-line" role="tablist">
                        <TabButton aktif={tab === 'detail'} onClick={() => setTab('detail')} icon={Info}>
                            Detail
                        </TabButton>
                        <TabButton aktif={tab === 'akses'} onClick={() => setTab('akses')} icon={ShieldCheck}>
                            Akses
                        </TabButton>
                        <TabButton aktif={tab === 'riwayat'} onClick={() => setTab('riwayat')} icon={History}>
                            Riwayat
                        </TabButton>
                    </div>

                    <div className="flex-1 overflow-auto p-5">
                        {tab === 'detail' && <PanelDetail dokumen={dokumen} />}
                        {tab === 'akses' && <PanelAkses dokumen={dokumen} />}
                        {tab === 'riwayat' && (
                            <PanelRiwayat
                                versi={versi}
                                riwayat={riwayat}
                                bolehPulihkan={dokumen.boleh_pulihkan_versi}
                            />
                        )}
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}

function Remah({ judul }: { judul: string }) {
    return (
        <div className="flex min-w-0 items-center gap-2 text-sm">
            <Link
                href="/documents"
                className="flex shrink-0 items-center gap-1.5 font-medium text-ink-muted hover:text-ink"
            >
                <ArrowLeft className="size-4" aria-hidden />
                Semua Dokumen
            </Link>
            <span className="text-ink-subtle" aria-hidden>
                /
            </span>
            <span className="truncate font-semibold text-ink">{judul}</span>
        </div>
    );
}

function TabButton({
    aktif,
    onClick,
    icon: Icon,
    children,
}: {
    aktif: boolean;
    onClick: () => void;
    icon: typeof Info;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={aktif}
            onClick={onClick}
            className={cn(
                'flex min-h-touch flex-1 items-center justify-center gap-1.5 border-b-2 px-3 py-3 text-sm font-medium transition-colors',
                aktif
                    ? 'border-brand-700 text-brand-700'
                    : 'border-transparent text-ink-muted hover:text-ink',
            )}
        >
            <Icon className="size-4" aria-hidden />
            {children}
        </button>
    );
}

function PanelDetail({ dokumen }: { dokumen: App.Data.DocumentDetailData }) {
    const [teksEkstraksiTerbuka, setTeksEkstraksiTerbuka] = useState(false);
    const teksEkstraksiTersedia = dokumen.extraction_status === 'completed' && dokumen.isi_teks !== null;

    return (
        <dl className="space-y-4">
            <Baris label="Nomor Dokumen" mono>
                {dokumen.nomor}
            </Baris>
            <Baris label="Judul">{dokumen.judul}</Baris>

            <Baris label="Versi Dokumen">
                <div className="space-y-1">
                    <span className="inline-flex rounded-full bg-brand-100 px-2 py-0.5 font-mono text-xs font-semibold text-brand-700">
                        {dokumen.version_label}
                    </span>
                    <p className="whitespace-pre-wrap text-sm text-ink-muted">
                        Deskripsi perubahan: {dokumen.version_note}
                    </p>
                </div>
            </Baris>

            {dokumen.deskripsi && (
                <Baris label="Deskripsi">
                    <span className="whitespace-pre-wrap">{dokumen.deskripsi}</span>
                </Baris>
            )}

            <Baris label="Kategori">{dokumen.kategori ?? '—'}</Baris>
            <Baris label="Unit Asal">{dokumen.unit_asal ?? '—'}</Baris>
            <Baris label="Tanggal Dokumen">{formatTanggalPanjang(dokumen.tanggal)}</Baris>

            <Baris label="Masa Berlaku">
                {dokumen.masa_berlaku ? (
                    formatTanggalPanjang(dokumen.masa_berlaku)
                ) : (
                    <span className="text-ink-subtle">Tanpa batas waktu</span>
                )}
            </Baris>

            <Baris label="Status">
                <DocumentStatusBadge status={dokumen.status} />
            </Baris>

            <hr className="border-line" />

            <Baris label="Nama Berkas" mono>
                <span className="block break-all">{dokumen.nama_berkas}</span>
            </Baris>

            <Baris label="Tipe & Ukuran">
                <span className="flex flex-wrap items-center gap-2">
                    <FileTypeBadge mime={dokumen.tipe_berkas} />
                    <span className="font-mono text-sm text-ink-muted">
                        {formatUkuranBerkas(dokumen.ukuran_berkas)}
                    </span>
                </span>
            </Baris>

            <Baris label="Pencarian Isi">
                <div className="space-y-2">
                    <ExtractionStatusBadge
                        status={dokumen.extraction_status}
                        halamanTotal={dokumen.halaman_ekstraksi_total}
                        halamanSelesai={dokumen.halaman_ekstraksi_selesai}
                        estimasiDetik={dokumen.estimasi_ekstraksi_detik}
                        pesan={dokumen.pesan_ekstraksi}
                    />
                    {teksEkstraksiTersedia && (
                        <>
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                icon={FileText}
                                aria-expanded={teksEkstraksiTerbuka}
                                aria-controls="teks-hasil-ekstraksi"
                                onClick={() => setTeksEkstraksiTerbuka((terbuka) => !terbuka)}
                            >
                                {teksEkstraksiTerbuka ? 'Sembunyikan teks hasil ekstraksi' : 'Lihat teks hasil ekstraksi'}
                            </Button>

                            {teksEkstraksiTerbuka && (
                                <div
                                    id="teks-hasil-ekstraksi"
                                    className="max-h-80 overflow-auto rounded-card border border-line bg-surface-sunken p-3"
                                    role="region"
                                    aria-label="Teks hasil ekstraksi"
                                >
                                    <p className="mb-2 text-xs text-ink-muted">
                                        Teks ini dipakai untuk pencarian isi dan dapat berbeda dari tata letak berkas asli.
                                    </p>
                                    <pre className="whitespace-pre-wrap break-words font-mono text-xs leading-relaxed text-ink">
                                        {dokumen.isi_teks}
                                    </pre>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </Baris>

            <hr className="border-line" />

            <Baris label="Diunggah Oleh">
                <span className="flex items-start gap-2">
                    <Avatar
                        initials={dokumen.inisial_pengunggah}
                        name={dokumen.pengunggah ?? undefined}
                        size="sm"
                    />
                    <span className="min-w-0">
                        <span className="block truncate font-medium text-ink">
                            {dokumen.pengunggah ?? '—'}
                        </span>
                        <span className="block truncate text-xs text-ink-muted">
                            {dokumen.jabatan_pengunggah ?? '—'}
                        </span>
                        <span className="block truncate text-xs text-ink-subtle">
                            {dokumen.unit_pengunggah ?? '—'}
                        </span>
                    </span>
                </span>
            </Baris>

            <Baris label="Diunggah Pada" mono>
                {formatWaktu(dokumen.diunggah_pada)}
            </Baris>
            <Baris label="Terakhir Diperbarui" mono>
                {formatWaktu(dokumen.diperbarui_pada)}
            </Baris>
        </dl>
    );
}

function PanelAkses({ dokumen }: { dokumen: App.Data.DocumentDetailData }) {
    return (
        <div className="space-y-5" id="akses">
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                    Mekanisme Aktif
                </p>
                <AccessSummary ringkasan={dokumen.ringkasan_akses} />
            </div>

            {/* Keempat mekanisme ditampilkan seluruhnya, termasuk yang tidak
                aktif. Menyembunyikan yang mati membuat pengguna tidak dapat
                memastikan apakah sebuah jalur akses memang tidak aktif atau
                sekadar tidak ikut ditampilkan. */}
            <div className="space-y-3">
                <MekanismeAkses
                    aktif={dokumen.dibagikan_ke_semua}
                    judul="Bagikan ke semua"
                    keterangan="Seluruh pengguna internal dapat melihat dokumen ini."
                />

                <MekanismeAkses
                    aktif={dokumen.min_tingkat_akses !== null}
                    judul="Bagikan ke jenjang jabatan"
                    keterangan={
                        dokumen.min_tingkat_akses !== null
                            ? `Jabatan tingkat ${dokumen.min_tingkat_akses} ke atas, lintas unit.`
                            : 'Tidak dibatasi ke jenjang jabatan tertentu.'
                    }
                />

                <MekanismeAkses
                    aktif={dokumen.unit_tujuan.length > 0}
                    judul="Bagikan ke unit"
                    keterangan={
                        dokumen.unit_tujuan.length > 0
                            ? dokumen.unit_tujuan.join(' · ')
                            : 'Belum ada unit yang dituju.'
                    }
                />

                <MekanismeAkses
                    aktif={dokumen.orang_tertentu.length > 0}
                    judul="Bagikan ke orang tertentu"
                    keterangan={
                        dokumen.orang_tertentu.length > 0
                            ? dokumen.orang_tertentu.join(' · ')
                            : 'Belum ada orang yang ditunjuk.'
                    }
                />
            </div>

            <div className="rounded-lg bg-surface-sunken p-4">
                <p className="text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                    Wewenang Mengubah
                </p>
                <p className="mt-1 text-sm text-ink">{dokumen.label_edit_scope}</p>
                <p className="mt-0.5 text-xs text-ink-muted">
                    {dokumen.edit_scope === 'owner_only'
                        ? 'Hanya pengunggah yang dapat mengubah dokumen ini.'
                        : 'Siapa pun yang dapat melihat dokumen ini juga dapat mengubahnya.'}
                </p>
            </div>
        </div>
    );
}

function MekanismeAkses({
    aktif,
    judul,
    keterangan,
}: {
    aktif: boolean;
    judul: string;
    keterangan: string;
}) {
    return (
        <div
            className={cn(
                'rounded-lg border p-3',
                aktif ? 'border-brand-200 bg-brand-50' : 'border-line bg-surface',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <p
                    className={cn(
                        'text-sm font-medium',
                        aktif ? 'text-brand-700' : 'text-ink-subtle',
                    )}
                >
                    {judul}
                </p>
                <span
                    className={cn(
                        'shrink-0 text-xs font-medium',
                        aktif ? 'text-brand-700' : 'text-ink-subtle',
                    )}
                >
                    {aktif ? 'Aktif' : 'Nonaktif'}
                </span>
            </div>
            <p className="mt-1 text-xs text-ink-muted">{keterangan}</p>
        </div>
    );
}

function PanelRiwayat({
    versi,
    riwayat,
    bolehPulihkan,
}: {
    versi: App.Data.DocumentVersionData[];
    riwayat: App.Data.ActivityLogData[];
    bolehPulihkan: boolean;
}) {
    const [bagian, setBagian] = useState<'versi' | 'aktivitas'>('versi');

    return (
        <div className="space-y-4" id="riwayat">
            <div className="grid grid-cols-2 rounded-lg bg-surface-sunken p-1">
                <button
                    type="button"
                    onClick={() => setBagian('versi')}
                    aria-pressed={bagian === 'versi'}
                    className={cn('rounded-md px-3 py-2 text-sm font-medium', bagian === 'versi' ? 'bg-surface text-brand-700 shadow-sm' : 'text-ink-muted')}
                >
                    Versi ({versi.length})
                </button>
                <button
                    type="button"
                    onClick={() => setBagian('aktivitas')}
                    aria-pressed={bagian === 'aktivitas'}
                    className={cn('rounded-md px-3 py-2 text-sm font-medium', bagian === 'aktivitas' ? 'bg-surface text-brand-700 shadow-sm' : 'text-ink-muted')}
                >
                    Aktivitas
                </button>
            </div>

            {bagian === 'versi' ? (
                <DocumentVersionHistory versi={versi} bolehPulihkan={bolehPulihkan} />
            ) : riwayat.length > 0 ? (
                <div className="-mx-5 divide-y divide-line"><div className="px-2 py-2">{riwayat.map((activity) => <ActivityItem key={activity.id} activity={activity} />)}</div></div>
            ) : (
                <EmptyState
                    icon={History}
                    title="Belum ada aktivitas"
                    description="Aktivitas yang dapat Anda akses akan muncul di sini."
                />
            )}
        </div>
    );
}

function Baris({
    label,
    children,
    mono = false,
}: {
    label: string;
    children: ReactNode;
    mono?: boolean;
}) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                {label}
            </dt>
            <dd className={cn('mt-1 text-sm text-ink', mono && 'break-all font-mono')}>
                {children}
            </dd>
        </div>
    );
}

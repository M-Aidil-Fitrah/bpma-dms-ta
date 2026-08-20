import { DocumentAccessPanel } from '@/Components/domain/DocumentAccessPanel';
import { ActivityItem } from '@/Components/domain/ActivityItem';
import { DocumentHeaderActions } from '@/Components/domain/DocumentHeaderActions';
import { DocumentPreview } from '@/Components/domain/DocumentPreview';
import { DocumentVersionHistory, KontrolTampilkanLebihBanyak } from '@/Components/domain/DocumentVersionHistory';
import { DocumentStatusBadge } from '@/Components/domain/DocumentStatusBadge';
import { ExtractionStatusBadge } from '@/Components/domain/ExtractionStatusBadge';
import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { Alert } from '@/Components/ui/Alert';
import { Avatar } from '@/Components/ui/Avatar';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { IconButton } from '@/Components/ui/IconButton';
import { Modal } from '@/Components/ui/Modal';
import { Tabs, type TabItem } from '@/Components/ui/Tabs';
import { useDocumentReloadPolling } from '@/hooks/useDocumentReloadPolling';
import { AppLayout } from '@/Layouts/AppLayout';
import { cn } from '@/lib/cn';
import { dalamJendelaWaktu, formatTanggalPanjang, formatUkuranBerkas, formatWaktu } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Check, Copy, FileText, History, Info, ShieldCheck } from 'lucide-react';
import { useState, type ReactNode } from 'react';

interface ShowProps {
    dokumen: App.Data.DocumentDetailData;
    versi: App.Data.DocumentVersionData[];
    riwayat: App.Data.ActivityLogData[];
    pollingKonfigurasi: { jedaMs: number; maksPercobaan: number };
}

type Tab = 'detail' | 'akses' | 'riwayat';

const TAB_VALID: readonly Tab[] = ['detail', 'akses', 'riwayat'];
const TAB_ITEMS: readonly TabItem<Tab>[] = [
    { value: 'detail', label: 'Detail', icon: Info },
    { value: 'akses', label: 'Akses', icon: ShieldCheck },
    { value: 'riwayat', label: 'Riwayat', icon: History },
];

/**
 * Tab awal mengikuti `location.hash` (mis. tautan menu "Lihat pengaturan
 * akses" mengarah ke `#akses`) — tanpa ini, tab kontennya dirender kondisional
 * sehingga `id="akses"` bahkan tidak ada di DOM saat halaman baru dimuat, dan
 * pengguna selalu mendarat di tab "Detail" berapa pun hash di alamatnya.
 */
function tabDariHash(): Tab {
    const hash = window.location.hash.slice(1);

    return isTab(hash) ? hash : 'detail';
}

function isTab(value: string): value is Tab {
    return TAB_VALID.some((tab) => tab === value);
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

            <div className="grid gap-5 xl:h-[calc(100dvh-6.5rem)] xl:grid-cols-5">
                {/* Pratinjau mendapat porsi terbesar: itu yang dicari orang saat
                    membuka halaman ini, bukan daftar metadatanya. */}
                <Card className="min-h-0 overflow-hidden xl:col-span-3">
                    <div className="h-[28rem] xl:h-full">
                        <DocumentPreview dokumen={dokumen} sedangMenyiapkanPratinjau={masihMenyiapkanPratinjau} />
                    </div>
                </Card>

                <Card className="flex min-h-0 flex-col xl:col-span-2 xl:h-full">
                    <Tabs items={TAB_ITEMS} value={tab} onChange={setTab} label="Bagian dokumen" />

                    <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                        {tab === 'detail' && <PanelDetail dokumen={dokumen} />}
                        {tab === 'akses' && <DocumentAccessPanel dokumen={dokumen} />}
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

function PanelDetail({ dokumen }: { dokumen: App.Data.DocumentDetailData }) {
    const [teksEkstraksiTerbuka, setTeksEkstraksiTerbuka] = useState(false);
    const [teksTersalin, setTeksTersalin] = useState(false);
    const teksEkstraksiTersedia = dokumen.extraction_status === 'completed' && dokumen.isi_teks !== null;

    async function salinTeksEkstraksi() {
        const teks = dokumen.isi_teks;
        if (teks === null) return;

        try {
            await navigator.clipboard?.writeText(teks);

            if (!navigator.clipboard) throw new Error('Clipboard API tidak tersedia.');

            setTeksTersalin(true);
        } catch {
            const bidang = document.createElement('textarea');
            bidang.value = teks;
            bidang.setAttribute('readonly', '');
            bidang.style.position = 'fixed';
            bidang.style.opacity = '0';
            document.body.appendChild(bidang);
            bidang.select();
            const berhasil = document.execCommand('copy');
            bidang.remove();
            setTeksTersalin(berhasil);
        }
    }

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
                    <div className="flex flex-wrap items-center gap-2">
                        <ExtractionStatusBadge
                            status={dokumen.extraction_status}
                            halamanTotal={dokumen.halaman_ekstraksi_total}
                            halamanSelesai={dokumen.halaman_ekstraksi_selesai}
                            estimasiDetik={dokumen.estimasi_ekstraksi_detik}
                            pesan={dokumen.pesan_ekstraksi}
                        />
                        {teksEkstraksiTersedia && (
                            <Button
                                type="button"
                                size="xs"
                                variant="secondary"
                                icon={FileText}
                                onClick={() => setTeksEkstraksiTerbuka(true)}
                            >
                                Lihat teks hasil ekstraksi
                            </Button>
                        )}
                    </div>
                </div>
            </Baris>

            {teksEkstraksiTersedia && (
                <Modal
                    terbuka={teksEkstraksiTerbuka}
                    onTutup={setTeksEkstraksiTerbuka}
                    judul="Teks hasil ekstraksi"
                    keterangan={dokumen.nama_berkas}
                    aksiHeader={
                        <IconButton
                            icon={teksTersalin ? Check : Copy}
                            label={teksTersalin ? 'Teks hasil ekstraksi sudah disalin' : 'Salin teks hasil ekstraksi'}
                            variant="ghost"
                            onClick={() => void salinTeksEkstraksi()}
                        />
                    }
                    className="h-[min(42rem,calc(100dvh-2rem))]"
                    contentClassName="p-0"
                >
                    <p className="border-b border-line bg-surface-raised px-5 py-3 text-sm text-ink-muted">
                        Teks ini dipakai untuk pencarian isi dan dapat berbeda dari tata letak berkas asli.
                    </p>
                    <pre className="whitespace-pre-wrap break-words p-5 font-mono text-sm leading-relaxed text-ink">
                        {dokumen.isi_teks}
                    </pre>
                </Modal>
            )}

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
    const [batasAktivitas, setBatasAktivitas] = useState(5);
    const aktivitasDitampilkan = riwayat.slice(0, batasAktivitas);

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
                <div className="-mx-5 divide-y divide-line">
                    <div className="px-2 py-2">{aktivitasDitampilkan.map((activity) => <ActivityItem key={activity.id} activity={activity} />)}</div>
                    <div className="px-5 py-3">
                        <KontrolTampilkanLebihBanyak
                            jumlahTampil={batasAktivitas}
                            jumlahTotal={riwayat.length}
                            onTampilkanLagi={() => setBatasAktivitas((batas) => batas + 5)}
                            onTampilkanSemua={() => setBatasAktivitas(riwayat.length)}
                        />
                    </div>
                </div>
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

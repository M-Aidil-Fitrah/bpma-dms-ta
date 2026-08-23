import { KategoriChart } from '@/Components/data/KategoriChart';
import { StatCard } from '@/Components/data/StatCard';
import { ActivityItem } from '@/Components/domain/ActivityItem';
import { DocumentRow } from '@/Components/domain/DocumentRow';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { AppLayout } from '@/Layouts/AppLayout';
import { cn } from '@/lib/cn';
import { formatAngka } from '@/lib/format';
import { wajibPenggunaTerautentikasi } from '@/types/auth';
import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CircleCheck,
    CircleX,
    FileText,
    FolderOpen,
    History,
    Plus,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface DashboardProps {
    data: App.Data.DashboardData;
}

export default function Dashboard({ data }: DashboardProps) {
    const { t } = useTranslation('dashboard');
    const penggunaSaatIni = wajibPenggunaTerautentikasi(usePage().props);

    return (
        <AppLayout title={t('judulHalaman')}>
            <div className="space-y-5">
                <SambutanBanner user={penggunaSaatIni} />

                <section aria-labelledby="statistik">
                    <h2 id="statistik" className="mb-3 text-sm font-semibold text-ink">
                        {t('statistik.judul')}
                    </h2>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label={t('statistik.totalDokumen')}
                            value={data.total}
                            icon={FileText}
                            caption={t('statistik.captionTotal')}
                        />
                        <StatCard
                            label={t('statistik.berlaku')}
                            value={data.berlaku}
                            icon={CircleCheck}
                            tone="success"
                        />
                        <StatCard
                            label={t('statistik.kadaluarsa')}
                            value={data.kadaluarsa}
                            icon={CircleX}
                            tone="danger"
                        />
                        <StatCard
                            label={t('statistik.mendekatiEvaluasi')}
                            value={data.jumlah_mendekati_evaluasi}
                            icon={CalendarClock}
                            tone="warning"
                            caption={t('statistik.captionRentang', { hari: data.rentang_evaluasi })}
                        />
                    </div>
                </section>

                <div className="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <KartuMasaEvaluasi data={data} />
                    <KartuKategori data={data} />
                </div>

                <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
                    <KartuTerbaru data={data} />
                    <KartuAktivitas data={data} />
                </div>
            </div>
        </AppLayout>
    );
}

function SambutanBanner({ user }: { user: App.Data.AuthUserData }) {
    const { t, i18n } = useTranslation('dashboard');
    const hariIni = new Date().toLocaleDateString(i18n.language === 'en' ? 'en-US' : 'id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return (
        <div className="overflow-hidden rounded-card bg-brand-700 px-5 py-6 text-white sm:px-7 sm:py-8">
            <p className="text-sm text-brand-100">{t('sambutan.kembali')}</p>
            <h1 className="mt-1 text-2xl font-semibold sm:text-3xl">{user.name}</h1>
            <p className="mt-1 text-sm text-brand-100">
                {hariIni}
                {user.jabatan && ` · ${user.jabatan}`}
                {user.unit && ` · ${user.unit}`}
            </p>

            <div className="mt-5 flex flex-col gap-2 sm:flex-row">
                <Link
                    href="/documents/create"
                    className="inline-flex min-h-touch w-full items-center justify-center gap-2 rounded-lg bg-surface px-4 py-2 text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:w-auto"
                >
                    <Plus className="size-4" aria-hidden />
                    {t('sambutan.tombolUnggah')}
                </Link>

                <Link
                    href="/documents"
                    className="inline-flex min-h-touch w-full items-center justify-center gap-2 rounded-lg border border-white/40 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-surface/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:w-auto"
                >
                    <FolderOpen className="size-4" aria-hidden />
                    {t('sambutan.tombolJelajahi')}
                </Link>
            </div>
        </div>
    );
}

function KartuMasaEvaluasi({ data }: { data: App.Data.DashboardData }) {
    const { t } = useTranslation('dashboard');

    /**
     * Rentang disimpan di query string, bukan di state komponen. Dengan begitu
     * pilihan pengguna ikut terbawa saat halaman disegarkan atau alamatnya
     * dibagikan — dan tombol kembali peramban bekerja seperti yang diharapkan.
     */
    function pilihRentang(hari: number) {
        router.get('/dashboard', { rentang: hari }, {
            preserveScroll: true,
            preserveState: true,
            only: ['data'],
        });
    }

    return (
        <Card className="xl:col-span-2">
            <CardHeader>
                <CardTitle>{t('statistik.mendekatiEvaluasi')}</CardTitle>

                <div
                    role="group"
                    aria-label={t('masaEvaluasi.ariaRentang')}
                    className="flex rounded-lg border border-line p-0.5"
                >
                    {data.rentang_pilihan.map((hari) => (
                        <button
                            key={hari}
                            type="button"
                            onClick={() => pilihRentang(hari)}
                            aria-pressed={hari === data.rentang_evaluasi}
                            className={cn(
                                'min-h-touch rounded-md px-3 text-sm font-medium transition-colors sm:min-h-0 sm:py-1.5',
                                hari === data.rentang_evaluasi
                                    ? 'bg-brand-700 text-white'
                                    : 'text-ink-muted hover:bg-surface-sunken hover:text-ink',
                            )}
                        >
                            {t('masaEvaluasi.hari', { hari })}
                        </button>
                    ))}
                </div>
            </CardHeader>

            <CardBody className="p-2">
                {data.mendekati_evaluasi.length === 0 ? (
                    <EmptyState
                        icon={CalendarClock}
                        title={t('masaEvaluasi.kosongJudul')}
                        description={t('masaEvaluasi.kosongDeskripsi', { hari: data.rentang_evaluasi })}
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {data.mendekati_evaluasi.map((dokumen) => (
                            <li key={dokumen.id}>
                                <DocumentRow document={dokumen} />
                            </li>
                        ))}
                    </ul>
                )}
            </CardBody>
        </Card>
    );
}

function KartuKategori({ data }: { data: App.Data.DashboardData }) {
    const { t } = useTranslation(['dashboard', 'common']);

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('kategori.judul')}</CardTitle>
            </CardHeader>

            <CardBody>
                {data.per_kategori.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title={t('common:kosong.judul')}
                        description={t('kategori.kosongDeskripsi')}
                    />
                ) : (
                    <>
                        <KategoriChart data={data.per_kategori} />

                        <ul className="mt-4 space-y-1.5">
                            {data.per_kategori.slice(0, 5).map((kategori) => (
                                <li
                                    key={kategori.id}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span className="truncate text-ink-muted">
                                        {kategori.nama}
                                    </span>
                                    <span className="shrink-0 font-medium tabular-nums text-ink">
                                        {formatAngka(kategori.jumlah)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </CardBody>
        </Card>
    );
}

function KartuTerbaru({ data }: { data: App.Data.DashboardData }) {
    const { t } = useTranslation('dashboard');

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('terbaru.judul')}</CardTitle>
                <Link
                    href="/documents"
                    className="text-sm font-medium text-brand-700 hover:text-brand-800"
                >
                    {t('terbaru.lihatSemua')}
                </Link>
            </CardHeader>

            <CardBody className="p-2">
                {data.terbaru.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title={t('terbaru.kosongJudul')}
                        description={t('terbaru.kosongDeskripsi')}
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {data.terbaru.map((dokumen) => (
                            <li key={dokumen.id}>
                                <DocumentRow document={dokumen} />
                            </li>
                        ))}
                    </ul>
                )}
            </CardBody>
        </Card>
    );
}

/**
 * Riwayat aktivitas baru tersedia setelah modul pencatatannya dibangun.
 *
 * Ditampilkan apa adanya, bukan diisi contoh — angka atau nama palsu di dasbor
 * mudah terbawa sampai demo dan disangka data sungguhan.
 */
function KartuAktivitas({ data }: { data: App.Data.DashboardData }) {
    const { t } = useTranslation('dashboard');

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('aktivitas.judul')}</CardTitle>
            </CardHeader>

            <CardBody className="p-2">
                {data.aktivitas_terbaru.length > 0 ? (
                    <div className="divide-y divide-line">{data.aktivitas_terbaru.map((activity) => <ActivityItem key={activity.id} activity={activity} />)}</div>
                ) : (
                    <EmptyState
                        icon={History}
                        title={t('aktivitas.kosongJudul')}
                        description={t('aktivitas.kosongDeskripsi')}
                    />
                )}
            </CardBody>
        </Card>
    );
}

import { KategoriChart } from '@/Components/data/KategoriChart';
import { StatCard } from '@/Components/data/StatCard';
import { ActivityItem } from '@/Components/domain/ActivityItem';
import { DocumentRow } from '@/Components/domain/DocumentRow';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { AppLayout } from '@/Layouts/AppLayout';
import { cn } from '@/lib/cn';
import { formatAngka } from '@/lib/format';
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

interface DashboardProps {
    data: App.Data.DashboardData;
}

export default function Dashboard({ data }: DashboardProps) {
    const { auth } = usePage().props as unknown as {
        auth: { user: App.Data.AuthUserData };
    };

    return (
        <AppLayout title="Beranda">
            <div className="space-y-5">
                <SambutanBanner user={auth.user} />

                <section aria-labelledby="statistik">
                    <h2 id="statistik" className="mb-3 text-sm font-semibold text-ink">
                        Statistik Dokumen
                    </h2>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label="Total Dokumen"
                            value={data.total}
                            icon={FileText}
                            caption="dapat Anda akses"
                        />
                        <StatCard
                            label="Berlaku"
                            value={data.berlaku}
                            icon={CircleCheck}
                            tone="success"
                        />
                        <StatCard
                            label="Kadaluarsa"
                            value={data.kadaluarsa}
                            icon={CircleX}
                            tone="danger"
                        />
                        <StatCard
                            label="Mendekati Masa Evaluasi"
                            value={data.jumlah_mendekati_evaluasi}
                            icon={CalendarClock}
                            tone="warning"
                            caption={`dalam ${data.rentang_evaluasi} hari`}
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
    const hariIni = new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return (
        <div className="overflow-hidden rounded-card bg-brand-700 px-5 py-6 text-white sm:px-7 sm:py-8">
            <p className="text-sm text-brand-100">Selamat datang kembali,</p>
            <h1 className="mt-1 text-2xl font-semibold sm:text-3xl">{user.name}</h1>
            <p className="mt-1 text-sm text-brand-100">
                {hariIni}
                {user.jabatan && ` · ${user.jabatan}`}
                {user.unit && ` · ${user.unit}`}
            </p>

            <div className="mt-5 flex flex-col gap-2 sm:flex-row">
                <Link
                    href="/documents/create"
                    className="inline-flex min-h-touch items-center justify-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                >
                    <Plus className="size-4" aria-hidden />
                    Unggah Dokumen
                </Link>

                <Link
                    href="/documents"
                    className="inline-flex min-h-touch items-center justify-center gap-2 rounded-lg border border-white/40 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                >
                    <FolderOpen className="size-4" aria-hidden />
                    Jelajahi Dokumen
                </Link>
            </div>
        </div>
    );
}

function KartuMasaEvaluasi({ data }: { data: App.Data.DashboardData }) {
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
                <CardTitle>Mendekati Masa Evaluasi</CardTitle>

                <div
                    role="group"
                    aria-label="Rentang masa evaluasi"
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
                            {hari} hari
                        </button>
                    ))}
                </div>
            </CardHeader>

            <CardBody className="p-2">
                {data.mendekati_evaluasi.length === 0 ? (
                    <EmptyState
                        icon={CalendarClock}
                        title="Tidak ada yang mendekati masa evaluasi"
                        description={`Tidak ada dokumen yang masa berlakunya berakhir dalam ${data.rentang_evaluasi} hari ke depan.`}
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
    return (
        <Card>
            <CardHeader>
                <CardTitle>Komposisi Kategori</CardTitle>
            </CardHeader>

            <CardBody>
                {data.per_kategori.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="Belum ada data"
                        description="Kategori akan tampil di sini setelah ada dokumen yang dapat Anda akses."
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
    return (
        <Card>
            <CardHeader>
                <CardTitle>Dokumen Terbaru</CardTitle>
                <Link
                    href="/documents"
                    className="text-sm font-medium text-brand-700 hover:text-brand-800"
                >
                    Lihat semua
                </Link>
            </CardHeader>

            <CardBody className="p-2">
                {data.terbaru.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="Belum ada dokumen"
                        description="Dokumen yang dapat Anda akses akan tampil di sini."
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
    return (
        <Card>
            <CardHeader>
                <CardTitle>Aktivitas Terbaru</CardTitle>
            </CardHeader>

            <CardBody className="p-2">
                {data.aktivitas_terbaru.length > 0 ? (
                    <div className="divide-y divide-line">{data.aktivitas_terbaru.map((activity) => <ActivityItem key={activity.id} activity={activity} />)}</div>
                ) : (
                    <EmptyState
                        icon={History}
                        title="Belum ada aktivitas"
                        description="Aktivitas yang dapat Anda akses akan muncul di sini."
                    />
                )}
            </CardBody>
        </Card>
    );
}

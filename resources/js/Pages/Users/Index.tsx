import { FilterBar, type FilterChip, type FilterDefinition } from '@/Components/data/FilterBar';
import { Pagination } from '@/Components/data/Pagination';
import { SearchInput } from '@/Components/data/SearchInput';
import { UserCardList } from '@/Components/domain/UserCardList';
import { UserTable } from '@/Components/domain/UserTable';
import { Button } from '@/Components/ui/Button';
import { Card, CardFooter } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { UserPlus, UserSearch, Users } from 'lucide-react';
import { useMemo } from 'react';

interface FilterPengguna {
    cari: string | null;
    jabatan: number | null;
    unit: number | null;
    status: string | null;
}

interface OpsiPengguna {
    jabatan: { id: number; nama: string }[];
    unit: { id: number; nama: string }[];
}

interface UsersIndexProps {
    pengguna: Pagination.Paginated<App.Data.UserListData>;
    filter: FilterPengguna;
    opsi?: OpsiPengguna;
}

const STATUS_OPTIONS = [
    { value: 'aktif', label: 'Aktif' },
    { value: 'nonaktif', label: 'Nonaktif' },
] as const;

export default function Index({ pengguna, filter, opsi }: UsersIndexProps) {
    const { auth } = usePage().props as unknown as {
        auth: { user: App.Data.AuthUserData };
    };

    function ubah(kunci: string, nilai: string) {
        router.get(
            '/admin/users',
            { ...bersihkanFilter(filter), [kunci]: nilai || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function bersihkan() {
        router.get('/admin/users', {}, { preserveScroll: true, replace: true });
    }

    const definisi = useMemo<FilterDefinition[]>(
        () => [
            {
                kunci: 'jabatan',
                label: 'Jabatan',
                tipe: 'select',
                placeholder: 'Semua jabatan',
                options: (opsi?.jabatan ?? []).map((j) => ({ value: j.id, label: j.nama })),
            },
            {
                kunci: 'unit',
                label: 'Unit Kerja',
                tipe: 'select',
                placeholder: 'Semua unit',
                options: (opsi?.unit ?? []).map((u) => ({ value: u.id, label: u.nama })),
            },
            {
                kunci: 'status',
                label: 'Status',
                tipe: 'select',
                placeholder: 'Semua status',
                options: STATUS_OPTIONS,
            },
        ],
        [opsi],
    );

    const chips = useMemo<FilterChip[]>(() => {
        const daftar: FilterChip[] = [];

        if (filter.cari) daftar.push({ kunci: 'cari', label: `Kata kunci: ${filter.cari}` });

        if (filter.jabatan) {
            const nama = opsi?.jabatan.find((j) => j.id === filter.jabatan)?.nama;
            daftar.push({ kunci: 'jabatan', label: `Jabatan: ${nama ?? filter.jabatan}` });
        }

        if (filter.unit) {
            const nama = opsi?.unit.find((u) => u.id === filter.unit)?.nama;
            daftar.push({ kunci: 'unit', label: `Unit: ${nama ?? filter.unit}` });
        }

        if (filter.status) {
            const label = STATUS_OPTIONS.find((s) => s.value === filter.status)?.label;
            daftar.push({ kunci: 'status', label: `Status: ${label ?? filter.status}` });
        }

        return daftar;
    }, [filter, opsi]);

    const nilaiFilter = useMemo<Record<string, string>>(
        () => ({
            jabatan: filter.jabatan?.toString() ?? '',
            unit: filter.unit?.toString() ?? '',
            status: filter.status ?? '',
        }),
        [filter],
    );

    return (
        <AppLayout
            title="Pengguna"
            actions={
                <Link href="/admin/users/create">
                    <Button icon={UserPlus}>
                        <span className="hidden sm:inline">Tambah Pengguna</span>
                        <span className="sr-only sm:hidden">Tambah Pengguna</span>
                    </Button>
                </Link>
            }
        >
            <div className="space-y-4">
                <FilterBar
                    definisi={definisi}
                    nilai={nilaiFilter}
                    onChange={ubah}
                    onReset={bersihkan}
                    chips={chips}
                    onHapusChip={(kunci) => ubah(kunci, '')}
                >
                    <SearchInput
                        value={filter.cari ?? ''}
                        onChange={(nilai) => ubah('cari', nilai)}
                        placeholder="Cari nama atau surel…"
                        className="flex-1"
                    />
                </FilterBar>

                <Card>
                    {pengguna.data.length === 0 ? (
                        <KeadaanKosong adaPenyaring={chips.length > 0} onReset={bersihkan} />
                    ) : (
                        <>
                            <UserTable pengguna={pengguna.data} idSayaSendiri={auth.user.id} />
                            <UserCardList pengguna={pengguna.data} idSayaSendiri={auth.user.id} />
                        </>
                    )}

                    {pengguna.total > 0 && (
                        <CardFooter>
                            <Pagination meta={pengguna} labelItem="pengguna" />
                        </CardFooter>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}

function bersihkanFilter(filter: FilterPengguna): Record<string, string> {
    const hasil: Record<string, string> = {};

    if (filter.cari) hasil.cari = filter.cari;
    if (filter.jabatan) hasil.jabatan = String(filter.jabatan);
    if (filter.unit) hasil.unit = String(filter.unit);
    if (filter.status) hasil.status = filter.status;

    return hasil;
}

function KeadaanKosong({ adaPenyaring, onReset }: { adaPenyaring: boolean; onReset: () => void }) {
    if (adaPenyaring) {
        return (
            <EmptyState
                icon={UserSearch}
                title="Tidak ada pengguna yang cocok"
                description="Tidak ada akun yang sesuai dengan penyaring yang sedang aktif. Coba longgarkan atau bersihkan penyaringnya."
                action={
                    <button
                        type="button"
                        onClick={onReset}
                        className="text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        Bersihkan semua filter
                    </button>
                }
            />
        );
    }

    return (
        <EmptyState
            icon={Users}
            title="Belum ada pengguna"
            description="Tidak ada registrasi publik — tambahkan akun pertama lewat tombol di atas."
            action={
                <Link href="/admin/users/create">
                    <Button icon={UserPlus}>Tambah Pengguna</Button>
                </Link>
            }
        />
    );
}

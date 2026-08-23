import { FilterBar, type FilterChip, type FilterDefinition } from '@/Components/data/FilterBar';
import { Pagination } from '@/Components/data/Pagination';
import { SearchInput } from '@/Components/data/SearchInput';
import { UserCardList } from '@/Components/domain/UserCardList';
import { UserTable } from '@/Components/domain/UserTable';
import { Button } from '@/Components/ui/Button';
import { Card, CardFooter } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { useFilters } from '@/hooks/useFilters';
import { AppLayout } from '@/Layouts/AppLayout';
import { wajibPenggunaTerautentikasi } from '@/types/auth';
import { Link, usePage } from '@inertiajs/react';
import { UserPlus, UserSearch, Users } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

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

export default function Index({ pengguna, filter, opsi }: UsersIndexProps) {
    const { t } = useTranslation(['users', 'common']);
    const penggunaSaatIni = wajibPenggunaTerautentikasi(usePage().props);

    const { ubah, bersihkan } = useFilters('/admin/users', filter);

    const STATUS_OPTIONS = useMemo(
        () =>
            [
                { value: 'aktif', label: t('users:index.filters.statusActive') },
                { value: 'nonaktif', label: t('users:index.filters.statusInactive') },
            ] as const,
        [t],
    );

    const definisi = useMemo<FilterDefinition[]>(
        () => [
            {
                kunci: 'jabatan',
                label: t('users:index.filters.jabatanLabel'),
                tipe: 'select',
                placeholder: t('users:index.filters.jabatanPlaceholder'),
                options: (opsi?.jabatan ?? []).map((j) => ({ value: j.id, label: j.nama })),
            },
            {
                kunci: 'unit',
                label: t('users:index.filters.unitLabel'),
                tipe: 'select',
                placeholder: t('users:index.filters.unitPlaceholder'),
                options: (opsi?.unit ?? []).map((u) => ({ value: u.id, label: u.nama })),
            },
            {
                kunci: 'status',
                label: t('users:index.filters.statusLabel'),
                tipe: 'select',
                placeholder: t('users:index.filters.statusPlaceholder'),
                options: STATUS_OPTIONS,
            },
        ],
        [opsi, t, STATUS_OPTIONS],
    );

    const chips = useMemo<FilterChip[]>(() => {
        const daftar: FilterChip[] = [];

        if (filter.cari) daftar.push({ kunci: 'cari', label: t('users:index.chips.keyword', { value: filter.cari }) });

        if (filter.jabatan) {
            const nama = opsi?.jabatan.find((j) => j.id === filter.jabatan)?.nama;
            daftar.push({ kunci: 'jabatan', label: t('users:index.chips.jabatan', { value: nama ?? filter.jabatan }) });
        }

        if (filter.unit) {
            const nama = opsi?.unit.find((u) => u.id === filter.unit)?.nama;
            daftar.push({ kunci: 'unit', label: t('users:index.chips.unit', { value: nama ?? filter.unit }) });
        }

        if (filter.status) {
            const label = STATUS_OPTIONS.find((s) => s.value === filter.status)?.label;
            daftar.push({ kunci: 'status', label: t('users:index.chips.status', { value: label ?? filter.status }) });
        }

        return daftar;
    }, [filter, opsi, t, STATUS_OPTIONS]);

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
            title={t('users:index.pageTitle')}
            actions={
                <Link href="/admin/users/create">
                    <Button icon={UserPlus}>
                        <span className="hidden sm:inline">{t('users:index.addUser')}</span>
                        <span className="sr-only sm:hidden">{t('users:index.addUser')}</span>
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
                        placeholder={t('users:index.searchPlaceholder')}
                        className="flex-1"
                    />
                </FilterBar>

                <Card>
                    {pengguna.data.length === 0 ? (
                        <KeadaanKosong adaPenyaring={chips.length > 0} onReset={bersihkan} />
                    ) : (
                        <>
                            <UserTable pengguna={pengguna.data} idSayaSendiri={penggunaSaatIni.id} />
                            <UserCardList pengguna={pengguna.data} idSayaSendiri={penggunaSaatIni.id} />
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


function KeadaanKosong({ adaPenyaring, onReset }: { adaPenyaring: boolean; onReset: () => void }) {
    const { t } = useTranslation(['users', 'common']);

    if (adaPenyaring) {
        return (
            <EmptyState
                icon={UserSearch}
                title={t('users:index.emptyFiltered.title')}
                description={t('users:index.emptyFiltered.description')}
                action={
                    <button
                        type="button"
                        onClick={onReset}
                        className="text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        {t('users:index.emptyFiltered.clearFilters')}
                    </button>
                }
            />
        );
    }

    return (
        <EmptyState
            icon={Users}
            title={t('users:index.emptyNoUsers.title')}
            description={t('users:index.emptyNoUsers.description')}
            action={
                <Link href="/admin/users/create">
                    <Button icon={UserPlus}>{t('users:index.addUser')}</Button>
                </Link>
            }
        />
    );
}

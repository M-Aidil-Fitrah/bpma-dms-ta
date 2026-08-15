import { ActivityEmpty, ActivityItem } from '@/Components/domain/ActivityItem';
import { FilterBar, type FilterChip, type FilterDefinition } from '@/Components/data/FilterBar';
import { Pagination } from '@/Components/data/Pagination';
import { SearchInput } from '@/Components/data/SearchInput';
import { Card, CardFooter } from '@/Components/ui/Card';
import { AppLayout } from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import { SearchX } from 'lucide-react';
import { useMemo } from 'react';

interface FilterActivity {
    cari: string | null;
    jenis: string | null;
    dari: string | null;
    sampai: string | null;
}

interface ActivityIndexProps {
    aktivitas: Pagination.Paginated<App.Data.ActivityLogData>;
    filter: FilterActivity;
    opsi: { value: string; label: string }[];
}

export default function Index({ aktivitas, filter, opsi }: ActivityIndexProps) {
    function ubah(key: string, value: string) {
        router.get('/activity-log', { ...activeFilter(filter), [key]: value || undefined }, { preserveScroll: true, preserveState: true, replace: true });
    }

    function reset() {
        router.get('/activity-log', {}, { preserveScroll: true, replace: true });
    }

    const definitions = useMemo<FilterDefinition[]>(() => [
        { kunci: 'jenis', label: 'Jenis aktivitas', tipe: 'select', placeholder: 'Semua jenis', options: opsi },
        { kunci: 'dari', label: 'Dari tanggal', tipe: 'date' },
        { kunci: 'sampai', label: 'Sampai tanggal', tipe: 'date' },
    ], [opsi]);
    const chips = useMemo<FilterChip[]>(() => [
        ...(filter.cari ? [{ kunci: 'cari', label: `Kata kunci: ${filter.cari}` }] : []),
        ...(filter.jenis ? [{ kunci: 'jenis', label: `Jenis: ${opsi.find((o) => o.value === filter.jenis)?.label ?? filter.jenis}` }] : []),
        ...(filter.dari ? [{ kunci: 'dari', label: `Dari: ${filter.dari}` }] : []),
        ...(filter.sampai ? [{ kunci: 'sampai', label: `Sampai: ${filter.sampai}` }] : []),
    ], [filter, opsi]);

    return (
        <AppLayout title="Riwayat Aktivitas">
            <div className="space-y-4">
                <FilterBar definisi={definitions} nilai={{ jenis: filter.jenis ?? '', dari: filter.dari ?? '', sampai: filter.sampai ?? '' }} onChange={ubah} onReset={reset} chips={chips} onHapusChip={(key) => ubah(key, '')}>
                    <SearchInput value={filter.cari ?? ''} onChange={(value) => ubah('cari', value)} placeholder="Cari aktivitas…" className="flex-1" />
                </FilterBar>
                <Card>
                    {aktivitas.data.length > 0 ? (
                        <div className="divide-y divide-line">{aktivitas.data.map((activity) => <ActivityItem key={activity.id} activity={activity} />)}</div>
                    ) : chips.length > 0 ? (
                        <div className="flex flex-col items-center py-10 text-center"><SearchX className="size-6 text-ink-subtle" /><p className="mt-3 text-sm font-medium text-ink">Tidak ada aktivitas yang cocok</p><button type="button" onClick={reset} className="mt-2 text-sm font-medium text-brand-700">Bersihkan semua filter</button></div>
                    ) : <ActivityEmpty />}
                    {aktivitas.total > 0 && <CardFooter><Pagination meta={aktivitas} labelItem="aktivitas" /></CardFooter>}
                </Card>
            </div>
        </AppLayout>
    );
}

function activeFilter(filter: FilterActivity): Record<string, string> {
    return {
        ...(filter.cari ? { cari: filter.cari } : {}),
        ...(filter.jenis ? { jenis: filter.jenis } : {}),
        ...(filter.dari ? { dari: filter.dari } : {}),
        ...(filter.sampai ? { sampai: filter.sampai } : {}),
    };
}

import { ActivityItem } from '@/Components/domain/ActivityItem';
import { FilterBar, type FilterChip, type FilterDefinition } from '@/Components/data/FilterBar';
import { Pagination } from '@/Components/data/Pagination';
import { SearchInput } from '@/Components/data/SearchInput';
import { Card, CardFooter } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { useFilters } from '@/hooks/useFilters';
import { AppLayout } from '@/Layouts/AppLayout';
import { History, SearchX } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

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
    const { t } = useTranslation(['activity', 'common']);
    const { ubah, bersihkan } = useFilters('/activity-log', filter);

    const definitions = useMemo<FilterDefinition[]>(() => [
        { kunci: 'jenis', label: t('activity:index.filters.typeLabel'), tipe: 'select', placeholder: t('activity:index.filters.typePlaceholder'), options: opsi },
        { kunci: 'dari', label: t('activity:index.filters.fromLabel'), tipe: 'date' },
        { kunci: 'sampai', label: t('activity:index.filters.toLabel'), tipe: 'date' },
    ], [opsi, t]);
    const chips = useMemo<FilterChip[]>(() => [
        ...(filter.cari ? [{ kunci: 'cari', label: t('activity:index.chips.keyword', { value: filter.cari }) }] : []),
        ...(filter.jenis ? [{ kunci: 'jenis', label: t('activity:index.chips.type', { value: opsi.find((o) => o.value === filter.jenis)?.label ?? filter.jenis }) }] : []),
        ...(filter.dari ? [{ kunci: 'dari', label: t('activity:index.chips.from', { value: filter.dari }) }] : []),
        ...(filter.sampai ? [{ kunci: 'sampai', label: t('activity:index.chips.to', { value: filter.sampai }) }] : []),
    ], [filter, opsi, t]);

    return (
        <AppLayout title={t('activity:index.pageTitle')}>
            <div className="space-y-4">
                <FilterBar definisi={definitions} nilai={{ jenis: filter.jenis ?? '', dari: filter.dari ?? '', sampai: filter.sampai ?? '' }} onChange={ubah} onReset={bersihkan} chips={chips} onHapusChip={(key) => ubah(key, '')}>
                    <SearchInput value={filter.cari ?? ''} onChange={(value) => ubah('cari', value)} placeholder={t('activity:index.searchPlaceholder')} className="flex-1" />
                </FilterBar>
                <Card>
                    {aktivitas.data.length > 0 ? (
                        <div className="divide-y divide-line">{aktivitas.data.map((activity) => <ActivityItem key={activity.id} activity={activity} />)}</div>
                    ) : chips.length > 0 ? (
                        <EmptyState
                            icon={SearchX}
                            title={t('activity:index.emptyFiltered.title')}
                            description={t('activity:index.emptyFiltered.description')}
                            action={
                                <button type="button" onClick={bersihkan} className="text-sm font-medium text-brand-700 hover:text-brand-800">
                                    {t('activity:index.emptyFiltered.clearFilters')}
                                </button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={History}
                            title={t('activity:index.emptyNone.title')}
                            description={t('activity:index.emptyNone.description')}
                        />
                    )}
                    {aktivitas.total > 0 && <CardFooter><Pagination meta={aktivitas} labelItem="aktivitas" /></CardFooter>}
                </Card>
            </div>
        </AppLayout>
    );
}

import type { PenggunaFilterPilihan } from '@/Components/domain/UserFilterSelect';
import { ActivityItem } from '@/Components/domain/ActivityItem';
import { FilterBar, type FilterChip, type FilterDefinition } from '@/Components/data/FilterBar';
import { Pagination } from '@/Components/data/Pagination';
import { SearchInput } from '@/Components/data/SearchInput';
import { Card, CardFooter } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { useFilters } from '@/hooks/useFilters';
import { AppLayout } from '@/Layouts/AppLayout';
import { Activity, SearchX } from 'lucide-react';
import { useMemo } from 'react';

interface FilterAktivitasAdmin {
    cari: string | null;
    jenis: string | null;
    dari: string | null;
    sampai: string | null;
    pelaku: number | null;
    unit: number | null;
}

interface OpsiAktivitasAdmin {
    jenis: { value: string; label: string }[];
    unit: { id: number; nama: string }[];
    unit_pohon: { id: number; nama: string; parent_id: number | null }[];
    pelaku_terpilih: PenggunaFilterPilihan | null;
}

interface AdminActivityIndexProps {
    aktivitas: Pagination.Paginated<App.Data.ActivityLogData>;
    filter: FilterAktivitasAdmin;
    opsi: OpsiAktivitasAdmin;
}

/**
 * Pemantauan aktivitas lintas pengguna, khusus Superadmin (FEAT-15b).
 *
 * Berbeda dari `Pages/ActivityLog/Index` yang cakupannya "aktivitas yang
 * dapat Anda akses" — halaman ini sengaja menampilkan seluruh pengguna,
 * dengan filter tambahan pelaku dan unit kerja untuk kebutuhan audit.
 */
export default function Index({ aktivitas, filter, opsi }: AdminActivityIndexProps) {
    const { ubah, bersihkan } = useFilters('/admin/activity-log', filter);

    const definitions = useMemo<FilterDefinition[]>(() => [
        { kunci: 'jenis', label: 'Jenis aktivitas', tipe: 'select', placeholder: 'Semua jenis', options: opsi.jenis },
        { kunci: 'pelaku', label: 'Pengguna', tipe: 'user', userSearchUrl: '/admin/activity-log/cari-pengguna', userValue: opsi.pelaku_terpilih },
        { kunci: 'unit', label: 'Unit kerja pengguna', tipe: 'tree', treeUnits: opsi.unit_pohon },
        { kunci: 'dari', label: 'Dari tanggal', tipe: 'date' },
        { kunci: 'sampai', label: 'Sampai tanggal', tipe: 'date' },
    ], [opsi]);

    const chips = useMemo<FilterChip[]>(() => [
        ...(filter.cari ? [{ kunci: 'cari', label: `Kata kunci: ${filter.cari}` }] : []),
        ...(filter.jenis ? [{ kunci: 'jenis', label: `Jenis: ${opsi.jenis.find((o) => o.value === filter.jenis)?.label ?? filter.jenis}` }] : []),
        ...(filter.pelaku ? [{ kunci: 'pelaku', label: `Pengguna: ${opsi.pelaku_terpilih?.nama ?? filter.pelaku}` }] : []),
        ...(filter.unit ? [{ kunci: 'unit', label: `Unit: ${opsi.unit.find((u) => u.id === filter.unit)?.nama ?? filter.unit}` }] : []),
        ...(filter.dari ? [{ kunci: 'dari', label: `Dari: ${filter.dari}` }] : []),
        ...(filter.sampai ? [{ kunci: 'sampai', label: `Sampai: ${filter.sampai}` }] : []),
    ], [filter, opsi]);

    return (
        <AppLayout title="Log Aktivitas">
            <div className="space-y-4">
                <FilterBar
                    definisi={definitions}
                    nilai={{
                        jenis: filter.jenis ?? '',
                        pelaku: filter.pelaku?.toString() ?? '',
                        unit: filter.unit?.toString() ?? '',
                        dari: filter.dari ?? '',
                        sampai: filter.sampai ?? '',
                    }}
                    onChange={ubah}
                    onReset={bersihkan}
                    chips={chips}
                    onHapusChip={(key) => ubah(key, '')}
                >
                    <SearchInput value={filter.cari ?? ''} onChange={(value) => ubah('cari', value)} placeholder="Cari aktivitas…" className="flex-1" />
                </FilterBar>
                <Card>
                    {aktivitas.data.length > 0 ? (
                        <div className="divide-y divide-line">{aktivitas.data.map((activity) => <ActivityItem key={activity.id} activity={activity} />)}</div>
                    ) : chips.length > 0 ? (
                        <EmptyState
                            icon={SearchX}
                            title="Tidak ada aktivitas yang cocok"
                            description="Tidak ada aktivitas yang sesuai dengan penyaring yang sedang aktif. Coba longgarkan atau bersihkan penyaringnya."
                            action={
                                <button type="button" onClick={bersihkan} className="text-sm font-medium text-brand-700 hover:text-brand-800">
                                    Bersihkan semua filter
                                </button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={Activity}
                            title="Belum ada aktivitas"
                            description="Aktivitas seluruh pengguna akan muncul di sini."
                        />
                    )}
                    {aktivitas.total > 0 && <CardFooter><Pagination meta={aktivitas} labelItem="aktivitas" /></CardFooter>}
                </Card>
            </div>
        </AppLayout>
    );
}

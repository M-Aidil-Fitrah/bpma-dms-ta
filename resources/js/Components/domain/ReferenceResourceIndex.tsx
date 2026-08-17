import { FilterBar, type FilterChip, type FilterDefinition } from '@/Components/data/FilterBar';
import { Pagination } from '@/Components/data/Pagination';
import { SearchInput } from '@/Components/data/SearchInput';
import { ReferenceResourceCards } from '@/Components/domain/ReferenceResourceCards';
import { ReferenceResourceTable } from '@/Components/domain/ReferenceResourceTable';
import { Button } from '@/Components/ui/Button';
import { Card, CardFooter } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { useFilters } from '@/hooks/useFilters';
import { Link } from '@inertiajs/react';
import { Plus, SearchX } from 'lucide-react';
import { useMemo } from 'react';

interface ReferenceResourceIndexProps {
    jenis: 'jabatan' | 'unit' | 'kategori';
    judul: string;
    singular: string;
    alamat: string;
    referensi: Pagination.Paginated<App.Data.ReferensiListData>;
    filter: { cari: string | null; status: string | null };
}

const STATUS: FilterDefinition = {
    kunci: 'status',
    label: 'Status',
    tipe: 'select',
    placeholder: 'Semua status',
    options: [
        { value: 'aktif', label: 'Aktif' },
        { value: 'nonaktif', label: 'Nonaktif' },
    ],
};

export function ReferenceResourceIndex({ jenis, judul, singular, alamat, referensi, filter }: ReferenceResourceIndexProps) {
    const chips = useMemo<FilterChip[]>(() => [
        ...(filter.cari ? [{ kunci: 'cari', label: `Kata kunci: ${filter.cari}` }] : []),
        ...(filter.status ? [{ kunci: 'status', label: `Status: ${filter.status === 'aktif' ? 'Aktif' : 'Nonaktif'}` }] : []),
    ], [filter]);

    const { ubah, bersihkan } = useFilters(alamat, filter);

    return (
        <>
            <FilterBar
                definisi={[STATUS]}
                nilai={{ status: filter.status ?? '' }}
                onChange={ubah}
                onReset={bersihkan}
                chips={chips}
                onHapusChip={(kunci) => ubah(kunci, '')}
            >
                <SearchInput value={filter.cari ?? ''} onChange={(nilai) => ubah('cari', nilai)} placeholder={`Cari ${singular}…`} className="flex-1" />
            </FilterBar>
            <Card>
                {referensi.data.length > 0 ? (
                    <>
                        <ReferenceResourceTable jenis={jenis} referensi={referensi.data} />
                        <ReferenceResourceCards jenis={jenis} referensi={referensi.data} />
                    </>
                ) : (
                    <EmptyState
                        icon={chips.length > 0 ? SearchX : Plus}
                        title={chips.length > 0 ? `Tidak ada ${singular} yang cocok` : `Belum ada ${singular}`}
                        description={chips.length > 0 ? 'Coba longgarkan atau bersihkan penyaring yang aktif.' : `Tambahkan ${singular} pertama untuk melengkapi struktur organisasi.`}
                        action={chips.length > 0 ? <button type="button" onClick={bersihkan} className="text-sm font-medium text-brand-700 hover:text-brand-800">Bersihkan semua filter</button> : <Link href={`${alamat}/create`}><Button icon={Plus}>Tambah {judul}</Button></Link>}
                    />
                )}
                {referensi.total > 0 && <CardFooter><Pagination meta={referensi} labelItem={singular} /></CardFooter>}
            </Card>
        </>
    );
}

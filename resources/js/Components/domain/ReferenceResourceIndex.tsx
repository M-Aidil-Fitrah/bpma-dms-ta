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
import { useTranslation } from 'react-i18next';

interface ReferenceResourceIndexProps {
    jenis: 'jabatan' | 'unit' | 'kategori';
    judul: string;
    singular: string;
    alamat: string;
    referensi: Pagination.Paginated<App.Data.ReferensiListData>;
    filter: { cari: string | null; status: string | null };
}

export function ReferenceResourceIndex({ jenis, judul, singular, alamat, referensi, filter }: ReferenceResourceIndexProps) {
    const { t } = useTranslation(['reference', 'common']);

    const STATUS: FilterDefinition = useMemo(() => ({
        kunci: 'status',
        label: t('reference:index.filter.statusLabel'),
        tipe: 'select',
        placeholder: t('reference:index.filter.semuaStatus'),
        options: [
            { value: 'aktif', label: t('common:status.aktif') },
            { value: 'nonaktif', label: t('common:status.nonaktif') },
        ],
    }), [t]);

    const chips = useMemo<FilterChip[]>(() => [
        ...(filter.cari ? [{ kunci: 'cari', label: t('reference:index.filter.chipKataKunci', { kata: filter.cari }) }] : []),
        ...(filter.status ? [{ kunci: 'status', label: t('reference:index.filter.chipStatus', { status: filter.status === 'aktif' ? t('common:status.aktif') : t('common:status.nonaktif') }) }] : []),
    ], [filter, t]);

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
                <SearchInput value={filter.cari ?? ''} onChange={(nilai) => ubah('cari', nilai)} placeholder={t('reference:index.cariPlaceholder', { singular })} className="flex-1" />
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
                        title={chips.length > 0 ? t('reference:index.kosong.tidakCocok', { singular }) : t('reference:index.kosong.belumAda', { singular })}
                        description={chips.length > 0 ? t('reference:index.kosong.deskripsiFilter') : t('reference:index.kosong.deskripsiKosong', { singular })}
                        action={chips.length > 0 ? <button type="button" onClick={bersihkan} className="text-sm font-medium text-brand-700 hover:text-brand-800">{t('reference:index.kosong.bersihkanFilter')}</button> : <Link href={`${alamat}/create`}><Button icon={Plus}>{t('reference:umum.tambahEntitas', { label: judul })}</Button></Link>}
                    />
                )}
                {referensi.total > 0 && <CardFooter><Pagination meta={referensi} labelItem={singular} /></CardFooter>}
            </Card>
        </>
    );
}

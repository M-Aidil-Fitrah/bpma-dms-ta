import { Pagination } from '@/Components/data/Pagination';
import { SearchInput } from '@/Components/data/SearchInput';
import { ViewToggle } from '@/Components/data/ViewToggle';
import { DocumentCardList } from '@/Components/domain/DocumentCardList';
import { DocumentGrid } from '@/Components/domain/DocumentGrid';
import { DocumentTable } from '@/Components/domain/DocumentTable';
import { WorkspaceDocumentActions } from '@/Components/domain/WorkspaceDocumentActions';
import { Card, CardFooter } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { useDocumentFilters, type FilterDokumen } from '@/hooks/useDocumentFilters';
import { AppLayout } from '@/Layouts/AppLayout';
import { Clock3, SearchX } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props {
    title: string;
    dokumen: Pagination.Paginated<App.Data.DocumentListData>;
    filter: FilterDokumen;
}

export default function Collection({ title, dokumen, filter }: Props) {
    const { t } = useTranslation(['workspace', 'common', 'documentBrowse']);

    // `title` dari server berupa literal Indonesia ("Berbintang"/"Terbaru
    // Dibuka") karena backend belum melewati i18n — dipetakan ke kunci
    // terjemahan di sini, bukan ditampilkan apa adanya.
    const judulHalaman = title === 'Berbintang'
        ? t('workspace:collection.judulBerbintang')
        : t('workspace:collection.judulTerbaruDibuka');
    const alamat = title === 'Berbintang' ? '/documents/starred' : '/documents/recent';
    const { ubah, urutkan, ubahTampilan, bersihkan } = useDocumentFilters(filter, alamat);
    const adaPenyaring = Boolean(filter.cari);

    return (
        <AppLayout title={judulHalaman}>
            <div className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <SearchInput value={filter.cari ?? ''} onChange={(nilai) => ubah('cari', nilai)} className="w-full sm:w-64" />
                    <ViewToggle nilai={filter.tampilan} onChange={ubahTampilan} labels={{ tabel: t('workspace:collection.viewToggle.tabel'), grid: t('workspace:collection.viewToggle.grid') }} />
                </div>

                <Card>
                    {dokumen.data.length === 0 ? (
                        <EmptyState
                            icon={adaPenyaring ? SearchX : Clock3}
                            title={adaPenyaring ? t('documentBrowse:index.kosong.tanpaHasil.judul') : t('workspace:collection.kosong.judul')}
                            description={adaPenyaring ? t('documentBrowse:index.kosong.tanpaHasil.deskripsi') : t('workspace:collection.kosong.deskripsi')}
                            action={adaPenyaring ? (
                                <button type="button" onClick={bersihkan} className="text-sm font-medium text-brand-700 hover:text-brand-800">
                                    {t('documentBrowse:index.kosong.tanpaHasil.aksi')}
                                </button>
                            ) : undefined}
                        />
                    ) : filter.tampilan === 'grid' ? (
                        <DocumentGrid
                            dokumen={dokumen.data}
                            aksi={(item) => <WorkspaceDocumentActions document={item} />}
                        />
                    ) : (
                        <>
                            <DocumentTable
                                dokumen={dokumen.data}
                                kunciUrut={filter.urut}
                                arahUrut={filter.arah}
                                onSort={urutkan}
                                aksi={(item) => <WorkspaceDocumentActions document={item} />}
                            />
                            <DocumentCardList
                                dokumen={dokumen.data}
                                aksi={(item) => <WorkspaceDocumentActions document={item} />}
                            />
                        </>
                    )}

                    {dokumen.total > 0 && (
                        <CardFooter>
                            <Pagination meta={dokumen} labelItem={t('documentBrowse:index.labelItemDokumen')} />
                        </CardFooter>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}

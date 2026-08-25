import { Pagination } from '@/Components/data/Pagination';
import { SearchInput } from '@/Components/data/SearchInput';
import { ViewToggle } from '@/Components/data/ViewToggle';
import { DocumentCardList } from '@/Components/domain/DocumentCardList';
import { DocumentGrid } from '@/Components/domain/DocumentGrid';
import { DocumentTable } from '@/Components/domain/DocumentTable';
import { WorkspaceTrashActions } from '@/Components/domain/WorkspaceTrashActions';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardFooter } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { useDocumentFilters, type FilterDokumen } from '@/hooks/useDocumentFilters';
import { AppLayout } from '@/Layouts/AppLayout';
import { formatTanggalPanjang } from '@/lib/format';
import { router } from '@inertiajs/react';
import { CalendarClock, FileText, Folder, RotateCcw, SearchX, ShieldCheck, Trash2, type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';

interface FolderItem { id: number; name: string; purge_after: string | null; }
interface Props {
    dokumen: Pagination.Paginated<App.Data.DocumentListData>;
    filter: FilterDokumen;
    folders: FolderItem[];
}

export default function Trash({ dokumen, filter, folders }: Props) {
    const { t } = useTranslation(['workspace', 'common', 'documentBrowse']);
    const { ubah, urutkan, ubahTampilan, bersihkan } = useDocumentFilters(filter, '/trash');
    const adaPenyaring = Boolean(filter.cari);
    const semuaKosong = folders.length === 0 && dokumen.total === 0 && !adaPenyaring;
    const jumlahItem = dokumen.total + folders.length;

    return (
        <AppLayout title={t('workspace:trash.judulHalaman')}>
            <div className="space-y-5">
                <section className="flex flex-col gap-4 rounded-card border border-warning/20 bg-warning-soft/50 p-4 sm:flex-row sm:items-center">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-warning-soft text-warning-strong"><Trash2 className="size-5" aria-hidden /></span>
                    <div className="min-w-0 flex-1"><h2 className="font-semibold text-ink">{t('workspace:trash.banner.judul')}</h2><p className="mt-1 text-sm text-ink-muted">{t('workspace:trash.banner.deskripsi')}</p></div>
                    {!semuaKosong && <Badge variant="warning" className="self-start sm:self-auto">{t('workspace:trash.jumlahItem', { jumlah: jumlahItem })}</Badge>}
                </section>

                {semuaKosong ? (
                    <EmptyState icon={Trash2} title={t('workspace:trash.kosong.judul')} description={t('workspace:trash.kosong.deskripsi')} />
                ) : (
                    <div className="space-y-5">
                        {folders.length > 0 && (
                            <section>
                                <SectionHeading icon={Folder} label={t('workspace:trash.bagian.folder')} count={folders.length} />
                                <div className="grid gap-3 lg:grid-cols-2">
                                    {folders.map((folder) => (
                                        <FolderTrashItem
                                            key={folder.id}
                                            name={folder.name}
                                            purgeAfter={folder.purge_after}
                                            onRestore={() => router.patch(`/folders/${folder.id}/restore`)}
                                        />
                                    ))}
                                </div>
                            </section>
                        )}

                        <section>
                            <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <SectionHeading icon={FileText} label={t('workspace:trash.bagian.dokumen')} count={dokumen.total} />
                                <div className="flex items-center gap-2">
                                    <SearchInput value={filter.cari ?? ''} onChange={(nilai) => ubah('cari', nilai)} className="w-full sm:w-64" />
                                    <ViewToggle nilai={filter.tampilan} onChange={ubahTampilan} />
                                </div>
                            </div>

                            <Card>
                                {dokumen.data.length === 0 ? (
                                    <EmptyState
                                        icon={adaPenyaring ? SearchX : Trash2}
                                        title={adaPenyaring ? t('documentBrowse:index.kosong.tanpaHasil.judul') : t('workspace:trash.kosong.judul')}
                                        description={adaPenyaring ? t('documentBrowse:index.kosong.tanpaHasil.deskripsi') : t('workspace:trash.kosong.deskripsi')}
                                        action={adaPenyaring ? (
                                            <button type="button" onClick={bersihkan} className="text-sm font-medium text-brand-700 hover:text-brand-800">
                                                {t('documentBrowse:index.kosong.tanpaHasil.aksi')}
                                            </button>
                                        ) : undefined}
                                    />
                                ) : filter.tampilan === 'grid' ? (
                                    <DocumentGrid dokumen={dokumen.data} aksi={(item) => <WorkspaceTrashActions document={item} />} dapatDibuka={false} />
                                ) : (
                                    <>
                                        <DocumentTable
                                            dokumen={dokumen.data}
                                            kunciUrut={filter.urut}
                                            arahUrut={filter.arah}
                                            onSort={urutkan}
                                            aksi={(item) => <WorkspaceTrashActions document={item} />}
                                            dapatDibuka={false}
                                        />
                                        <DocumentCardList dokumen={dokumen.data} aksi={(item) => <WorkspaceTrashActions document={item} />} dapatDibuka={false} />
                                    </>
                                )}

                                {dokumen.total > 0 && (
                                    <CardFooter>
                                        <Pagination meta={dokumen} labelItem={t('documentBrowse:index.labelItemDokumen')} />
                                    </CardFooter>
                                )}
                            </Card>
                        </section>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function SectionHeading({ icon: Icon, label, count }: { icon: LucideIcon; label: string; count: number; }) {
    return <div className="flex items-center gap-2"><Icon className="size-4 text-ink-muted" aria-hidden /><h2 className="text-sm font-semibold text-ink">{label}</h2><Badge variant="neutral" size="sm">{count}</Badge></div>;
}

/** Folder di Sampah sengaja mempertahankan bentuk kartu lama — hanya bagian dokumen yang disamakan dengan Jelajahi Dokumen. */
function FolderTrashItem({ name, purgeAfter, onRestore }: { name: string; purgeAfter: string | null; onRestore: () => void; }) {
    const { t } = useTranslation('workspace');
    const icon: ReactNode = <Folder className="size-5 text-brand-700" aria-hidden />;

    return <article className="flex min-w-0 items-center gap-3 rounded-card border border-line bg-surface p-4 transition-shadow hover:shadow-pop"><span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-sunken">{icon}</span><div className="min-w-0 flex-1"><div className="flex min-w-0 items-center gap-2"><p className="truncate text-sm font-medium text-ink">{name}</p><Badge variant="neutral" size="sm">{t('trash.bagian.folder')}</Badge></div><p className="mt-1 flex items-center gap-1 text-xs text-ink-muted"><CalendarClock className="size-3.5 shrink-0" aria-hidden />{t('trash.dihapusPermanen', { tanggal: formatTanggalPanjang(purgeAfter) })}</p><p className="mt-1 flex items-center gap-1 text-xs text-ink-muted"><ShieldCheck className="size-3.5 shrink-0" aria-hidden />{sisaRetensi(purgeAfter, t)}</p></div><Button variant="secondary" size="sm" icon={RotateCcw} onClick={onRestore}><span className="hidden sm:inline">{t('trash.tombolPulihkan.label')}</span><span className="sr-only sm:hidden">{t('trash.tombolPulihkan.srLabel', { nama: name })}</span></Button></article>;
}

function sisaRetensi(purgeAfter: string | null, t: TFunction): string {
    if (!purgeAfter) return t('trash.retensi.belumTersedia');

    const days = Math.max(0, Math.ceil((new Date(purgeAfter).getTime() - Date.now()) / 86_400_000));

    return days === 0 ? t('trash.retensi.hariIni') : t('trash.retensi.tersisa', { hari: days });
}

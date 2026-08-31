import { Pagination } from '@/Components/data/Pagination';
import { SearchInput } from '@/Components/data/SearchInput';
import { ViewToggle } from '@/Components/data/ViewToggle';
import { DocumentCardList } from '@/Components/domain/DocumentCardList';
import { DocumentGrid } from '@/Components/domain/DocumentGrid';
import { DocumentTable } from '@/Components/domain/DocumentTable';
import { WorkspaceDocumentActions, type WorkspaceFolderOption } from '@/Components/domain/WorkspaceDocumentActions';
import { WorkspaceFolderCard } from '@/Components/domain/WorkspaceFolderCard';
import { Button } from '@/Components/ui/Button';
import { Card, CardFooter } from '@/Components/ui/Card';
import { Dropdown, DropdownItem } from '@/Components/ui/Dropdown';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { useDocumentFilters, type FilterDokumen } from '@/hooks/useDocumentFilters';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { ChevronRight, FileUp, Folder, FolderPlus, Plus, SearchX } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

interface FolderItem { id: number; name: string; }
interface CurrentFolder extends FolderItem { parent_id: number | null; }
interface BreadcrumbItem { label: string; href: string; }
interface Props {
    title: string;
    folder: CurrentFolder | null;
    breadcrumbs: BreadcrumbItem[];
    folders: FolderItem[];
    folder_options: WorkspaceFolderOption[];
    dokumen: Pagination.Paginated<App.Data.DocumentListData>;
    filter: FilterDokumen;
}

export default function Index({ title, folder, breadcrumbs, folders, folder_options: folderOptions, dokumen, filter }: Props) {
    const { t } = useTranslation(['workspace', 'common', 'nav', 'documentBrowse']);
    const alamat = folder === null ? '/documents/mine' : `/folders/${folder.id}`;
    const { ubah, urutkan, ubahTampilan, bersihkan } = useDocumentFilters(filter, alamat);
    const [dialogOpen, setDialogOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', parent_id: folder?.id ?? null as number | null });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/folders', { onSuccess: () => { reset(); setDialogOpen(false); } });
    }

    const adaPenyaring = Boolean(filter.cari);
    const semuaKosong = folders.length === 0 && dokumen.total === 0 && !adaPenyaring;

    return (
        <AppLayout
            // `title` dari server adalah "Dokumen Saya" (literal Indonesia,
            // tidak melewati i18n backend) di akar, atau nama folder asli
            // (data pengguna, bukan teks UI) saat berada di dalam folder.
            // Kasus akar diganti kunci terjemahan; nama folder tetap apa
            // adanya karena itu benar-benar data, bukan salinan antarmuka.
            title={folder === null ? t('nav:item.dokumenSaya') : title}
            actions={
                <Dropdown
                    trigger={<Button icon={Plus} size="sm"><span className="hidden sm:inline">{t('workspace:index.tombolBaru.label')}</span><span className="sr-only sm:hidden">{t('workspace:index.tombolBaru.srLabel')}</span></Button>}
                    panelClassName="w-56"
                >
                    <DropdownItem>
                        <Link href="/documents/create" className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"><FileUp className="size-4" aria-hidden />{t('workspace:index.menu.unggahDokumen')}</Link>
                    </DropdownItem>
                    <DropdownItem>
                        <button type="button" onClick={() => setDialogOpen(true)} className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"><FolderPlus className="size-4" aria-hidden />{t('workspace:index.menu.buatFolder')}</button>
                    </DropdownItem>
                </Dropdown>
            }
        >
            <div className="space-y-5">
                {folder !== null && <nav aria-label={t('workspace:index.ariaLokasiFolder')} className="flex min-w-0 items-center gap-1 overflow-x-auto whitespace-nowrap text-sm">
                    {breadcrumbs.map((breadcrumb, index) => {
                        const current = index === breadcrumbs.length - 1;

                        return (
                            <div key={breadcrumb.href} className="flex items-center gap-1">
                                {index > 0 && <ChevronRight className="size-4 shrink-0 text-ink-subtle" aria-hidden />}
                                {current ? (
                                    <span aria-current="page" className="truncate font-medium text-ink">{breadcrumb.label}</span>
                                ) : (
                                    <Link href={breadcrumb.href} className="rounded px-1 py-0.5 text-ink-muted hover:bg-surface-sunken hover:text-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-700">{breadcrumb.label}</Link>
                                )}
                            </div>
                        );
                    })}
                </nav>}

                {semuaKosong ? (
                    <EmptyState icon={Folder} title={t('workspace:index.kosong.judul')} description={t('workspace:index.kosong.deskripsi')} action={<Link href="/documents/create"><Button>{t('workspace:index.kosong.tombolUnggah')}</Button></Link>} />
                ) : (
                    <div className="space-y-5">
                        {folders.length > 0 && (
                            <section>
                                <h2 className="mb-3 text-sm font-semibold text-ink">{t('workspace:index.bagian.folder')}</h2>
                                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    {folders.map((item) => <WorkspaceFolderCard key={item.id} folder={item} />)}
                                </div>
                            </section>
                        )}

                        <section>
                            <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <h2 className="text-sm font-semibold text-ink">{t('workspace:index.bagian.dokumen')}</h2>
                                <div className="flex items-center gap-2">
                                    <SearchInput value={filter.cari ?? ''} onChange={(nilai) => ubah('cari', nilai)} className="w-full sm:w-64" />
                                    <ViewToggle nilai={filter.tampilan} onChange={ubahTampilan} />
                                </div>
                            </div>

                            <Card>
                                {dokumen.data.length === 0 ? (
                                    <EmptyState
                                        icon={adaPenyaring ? SearchX : Folder}
                                        title={adaPenyaring ? t('documentBrowse:index.kosong.tanpaHasil.judul') : t('workspace:index.kosong.judul')}
                                        description={adaPenyaring ? t('documentBrowse:index.kosong.tanpaHasil.deskripsi') : t('workspace:index.kosong.deskripsi')}
                                        action={adaPenyaring ? (
                                            <button type="button" onClick={bersihkan} className="text-sm font-medium text-brand-700 hover:text-brand-800">
                                                {t('documentBrowse:index.kosong.tanpaHasil.aksi')}
                                            </button>
                                        ) : (
                                            <Link href="/documents/create"><Button>{t('workspace:index.kosong.tombolUnggah')}</Button></Link>
                                        )}
                                    />
                                ) : filter.tampilan === 'grid' ? (
                                    <DocumentGrid
                                        dokumen={dokumen.data}
                                        aksi={(item) => <WorkspaceDocumentActions document={item} folderOptions={folderOptions} currentFolderId={folder?.id ?? null} />}
                                    />
                                ) : (
                                    <>
                                        <DocumentTable
                                            dokumen={dokumen.data}
                                            kunciUrut={filter.urut}
                                            arahUrut={filter.arah}
                                            onSort={urutkan}
                                            aksi={(item) => <WorkspaceDocumentActions document={item} folderOptions={folderOptions} currentFolderId={folder?.id ?? null} />}
                                        />
                                        <DocumentCardList
                                            dokumen={dokumen.data}
                                            aksi={(item) => <WorkspaceDocumentActions document={item} folderOptions={folderOptions} currentFolderId={folder?.id ?? null} />}
                                        />
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
            <Modal terbuka={dialogOpen} onTutup={setDialogOpen} judul={t('workspace:index.dialogFolder.judul')} footer={<><Button variant="secondary" onClick={() => setDialogOpen(false)}>{t('common:aksi.batal')}</Button><Button form="form-folder" type="submit" loading={processing}>{t('workspace:index.dialogFolder.tombolBuat')}</Button></>}>
                <form id="form-folder" onSubmit={submit}>
                    <Field label={t('workspace:index.dialogFolder.labelNama')} error={errors.name} required>
                        {(props) => (
                            <Input
                                {...props}
                                // eslint-disable-next-line jsx-a11y/no-autofocus -- fokus awal ke field nama pada dialog yang baru terbuka (pola dialog WAI-ARIA)
                                autoFocus
                                value={data.name}
                                invalid={Boolean(errors.name)}
                                onChange={(event) => setData('name', event.target.value)}
                            />
                        )}
                    </Field>
                </form>
            </Modal>
        </AppLayout>
    );
}

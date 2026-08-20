import { WorkspaceDocumentCard, type WorkspaceDocument, type WorkspaceFolderOption } from '@/Components/domain/WorkspaceDocumentCard';
import { WorkspaceFolderCard } from '@/Components/domain/WorkspaceFolderCard';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Dropdown, DropdownItem } from '@/Components/ui/Dropdown';
import { ViewToggle, type ModeTampilan } from '@/Components/data/ViewToggle';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { ChevronRight, FileUp, Folder, FolderPlus, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface FolderItem { id: number; name: string; }
interface CurrentFolder extends FolderItem { parent_id: number | null; }
interface BreadcrumbItem { label: string; href: string; }
interface Props {
    title: string;
    folder: CurrentFolder | null;
    breadcrumbs: BreadcrumbItem[];
    folders: FolderItem[];
    folder_options: WorkspaceFolderOption[];
    documents: WorkspaceDocument[];
}

export default function Index({ title, folder, breadcrumbs, folders, folder_options: folderOptions, documents }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [mode, setMode] = useState<ModeTampilan>('grid');
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', parent_id: folder?.id ?? null as number | null });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/folders', { onSuccess: () => { reset(); setDialogOpen(false); } });
    }

    const empty = folders.length === 0 && documents.length === 0;

    return (
        <AppLayout
            title={title}
            actions={
                <div className="flex items-center gap-2">
                    <ViewToggle nilai={mode} onChange={setMode} labels={{ tabel: 'Tampilan daftar', grid: 'Tampilan grid' }} />
                    <Dropdown
                        trigger={<Button icon={Plus} size="sm"><span className="hidden sm:inline">Baru</span><span className="sr-only sm:hidden">Buat baru</span></Button>}
                        panelClassName="w-56"
                    >
                        <DropdownItem>
                            <Link href="/documents/create" className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"><FileUp className="size-4" aria-hidden />Unggah dokumen</Link>
                        </DropdownItem>
                        <DropdownItem>
                            <button type="button" onClick={() => setDialogOpen(true)} className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"><FolderPlus className="size-4" aria-hidden />Buat folder</button>
                        </DropdownItem>
                    </Dropdown>
                </div>
            }
        >
            <div className="space-y-5">
                {folder !== null && <nav aria-label="Lokasi folder" className="flex min-w-0 items-center gap-1 overflow-x-auto whitespace-nowrap text-sm">
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
                {empty ? (
                    <EmptyState icon={Folder} title="Belum ada isi" description="Buat folder untuk mengelompokkan dokumen yang Anda unggah, atau unggah dokumen baru." action={<Link href="/documents/create"><Button>Unggah Dokumen</Button></Link>} />
                ) : (
                    <div className="space-y-5">
                        {folders.length > 0 && <section><h2 className="mb-3 text-sm font-semibold text-ink">Folder</h2><div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{folders.map((item) => <WorkspaceFolderCard key={item.id} folder={item} />)}</div></section>}
                        {documents.length > 0 && <section><h2 className="mb-3 text-sm font-semibold text-ink">Dokumen</h2>{mode === 'grid' ? <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{documents.map((document) => <WorkspaceDocumentCard key={document.id} document={document} folderOptions={folderOptions} currentFolderId={folder?.id ?? null} mode="grid" />)}</div> : <Card><ul className="divide-y divide-line">{documents.map((document) => <li key={document.id}><WorkspaceDocumentCard document={document} folderOptions={folderOptions} currentFolderId={folder?.id ?? null} /></li>)}</ul></Card>}</section>}
                    </div>
                )}
            </div>
            <Modal terbuka={dialogOpen} onTutup={setDialogOpen} judul="Buat folder" footer={<><Button variant="secondary" onClick={() => setDialogOpen(false)}>Batal</Button><Button form="form-folder" type="submit" loading={processing}>Buat folder</Button></>}>
                <form id="form-folder" onSubmit={submit}><Field label="Nama folder" error={errors.name} required>{(props) => <Input {...props} autoFocus value={data.name} invalid={Boolean(errors.name)} onChange={(event) => setData('name', event.target.value)} />}</Field></form>
            </Modal>
        </AppLayout>
    );
}

import { WorkspaceDocumentCard, type WorkspaceDocument } from '@/Components/domain/WorkspaceDocumentCard';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { Folder, FolderPlus } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface FolderItem { id: number; name: string; }
interface CurrentFolder extends FolderItem { parent_id: number | null; }
interface Props {
    title: string;
    folder: CurrentFolder | null;
    folders: FolderItem[];
    documents: WorkspaceDocument[];
}

export default function Index({ title, folder, folders, documents }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', parent_id: folder?.id ?? null as number | null });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/folders', { onSuccess: () => { reset(); setDialogOpen(false); } });
    }

    const empty = folders.length === 0 && documents.length === 0;

    return (
        <AppLayout
            title={title}
            actions={<Button icon={FolderPlus} onClick={() => setDialogOpen(true)}>Buat Folder</Button>}
        >
            <div className="space-y-5">
                {folder && <Link href="/documents/mine" className="text-sm font-medium text-brand-700 hover:text-brand-800">Dokumen Saya</Link>}
                {empty ? (
                    <EmptyState icon={Folder} title="Belum ada isi" description="Buat folder untuk mengelompokkan dokumen yang Anda unggah, atau unggah dokumen baru." action={<Link href="/documents/create"><Button>Unggah Dokumen</Button></Link>} />
                ) : (
                    <div className="space-y-5">
                        {folders.length > 0 && <section><h2 className="mb-3 text-sm font-semibold text-ink">Folder</h2><div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{folders.map((item) => <Link key={item.id} href={`/folders/${item.id}`} className="flex min-h-touch items-center gap-3 rounded-lg border border-line bg-surface p-4 font-medium text-ink hover:border-brand-300 hover:bg-brand-50/30"><Folder className="size-5 text-brand-700" aria-hidden />{item.name}</Link>)}</div></section>}
                        {documents.length > 0 && <section><h2 className="mb-3 text-sm font-semibold text-ink">Dokumen</h2><div className="grid gap-3 lg:grid-cols-2">{documents.map((document) => <WorkspaceDocumentCard key={document.id} document={document} />)}</div></section>}
                    </div>
                )}
            </div>
            <Modal terbuka={dialogOpen} onTutup={setDialogOpen} judul="Buat folder" footer={<><Button variant="secondary" onClick={() => setDialogOpen(false)}>Batal</Button><Button form="form-folder" type="submit" loading={processing}>Buat folder</Button></>}>
                <form id="form-folder" onSubmit={submit}><Field label="Nama folder" error={errors.name} required>{(props) => <Input {...props} autoFocus value={data.name} invalid={Boolean(errors.name)} onChange={(event) => setData('name', event.target.value)} />}</Field></form>
            </Modal>
        </AppLayout>
    );
}

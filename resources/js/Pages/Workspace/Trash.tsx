import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { AppLayout } from '@/Layouts/AppLayout';
import { formatTanggalPanjang } from '@/lib/format';
import { router } from '@inertiajs/react';
import { Folder, Trash2 } from 'lucide-react';

interface DocumentItem { id: number; judul: string; purge_after: string | null; }
interface FolderItem { id: number; name: string; purge_after: string | null; }
interface Props { documents: DocumentItem[]; folders: FolderItem[]; }

export default function Trash({ documents, folders }: Props) {
    const empty = documents.length === 0 && folders.length === 0;
    return (
        <AppLayout title="Sampah">
            {empty ? <EmptyState icon={Trash2} title="Sampah kosong" description="Dokumen dan folder yang dipindahkan ke Sampah akan berada di sini selama 30 hari." /> : <div className="space-y-5">
                {folders.length > 0 && <section><h2 className="mb-3 text-sm font-semibold text-ink">Folder</h2><div className="space-y-2">{folders.map((folder) => <Item key={folder.id} name={folder.name} purgeAfter={folder.purge_after} onRestore={() => router.patch(`/folders/${folder.id}/restore`)} />)}</div></section>}
                {documents.length > 0 && <section><h2 className="mb-3 text-sm font-semibold text-ink">Dokumen</h2><div className="space-y-2">{documents.map((document) => <Item key={document.id} name={document.judul} purgeAfter={document.purge_after} onRestore={() => router.patch(`/documents/${document.id}/restore-trash`)} />)}</div></section>}
            </div>}
        </AppLayout>
    );
}

function Item({ name, purgeAfter, onRestore }: { name: string; purgeAfter: string | null; onRestore: () => void; }) {
    return <div className="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4 sm:flex-row sm:items-center"><div className="min-w-0 flex-1"><p className="truncate text-sm font-medium text-ink">{name}</p><p className="text-xs text-ink-muted">Dihapus permanen {formatTanggalPanjang(purgeAfter)}</p></div><Button variant="secondary" onClick={onRestore}>Pulihkan</Button></div>;
}

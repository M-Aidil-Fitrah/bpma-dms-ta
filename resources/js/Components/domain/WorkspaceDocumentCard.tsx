import { Badge } from '@/Components/ui/Badge';
import { IconButton } from '@/Components/ui/IconButton';
import { labelTipeBerkas } from '@/lib/format';
import { Link, router } from '@inertiajs/react';
import { FileText, Star } from 'lucide-react';
import { type ChangeEvent } from 'react';

export interface WorkspaceDocument {
    id: number;
    judul: string;
    nomor: string;
    tipe: string;
    is_private: boolean;
    starred: boolean;
    trashed_at: string | null;
    purge_after: string | null;
}

export interface WorkspaceFolderOption { id: number; name: string; }

export function WorkspaceDocumentCard({
    document,
    folderOptions,
    currentFolderId = null,
}: {
    document: WorkspaceDocument;
    folderOptions?: WorkspaceFolderOption[];
    currentFolderId?: number | null;
}) {
    function toggleStar() {
        if (document.starred) {
            router.delete(`/documents/${document.id}/star`, { preserveScroll: true });

            return;
        }

        router.put(`/documents/${document.id}/star`, {}, { preserveScroll: true });
    }

    function move(event: ChangeEvent<HTMLSelectElement>) {
        const folderId = event.target.value;
        if (folderId === '') {
            router.delete(`/documents/${document.id}/folder`, { preserveScroll: true });

            return;
        }

        router.put(`/documents/${document.id}/folder`, { folder_id: Number(folderId) }, { preserveScroll: true });
    }

    return (
        <article className="flex min-w-0 items-center gap-3 rounded-lg border border-line bg-surface p-3 transition-colors hover:border-brand-300 hover:bg-brand-50/30">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-sunken text-ink-muted">
                <FileText className="size-5" aria-hidden />
            </span>
            <Link href={`/documents/${document.id}`} className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium text-ink">{document.judul}</span>
                <span className="mt-0.5 block truncate text-xs text-ink-muted">{document.nomor}</span>
                <span className="mt-1 flex flex-wrap gap-1.5">
                    <Badge size="sm">{labelTipeBerkas(document.tipe)}</Badge>
                    {document.is_private && <Badge variant="info" size="sm">Hanya saya</Badge>}
                </span>
            </Link>
            <IconButton
                type="button"
                icon={Star}
                label={document.starred ? `Hapus bintang ${document.judul}` : `Beri bintang ${document.judul}`}
                variant="ghost"
                className={document.starred ? 'text-warning-strong' : undefined}
                onClick={toggleStar}
            />
            {folderOptions !== undefined && (
                <select
                    aria-label={`Pindahkan ${document.judul} ke folder`}
                    value={currentFolderId ?? ''}
                    onChange={move}
                    className="min-h-touch max-w-40 rounded-lg border border-line bg-surface px-2 text-xs text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-700 sm:min-h-8"
                >
                    <option value="">Akar Dokumen Saya</option>
                    {folderOptions.map((folder) => <option key={folder.id} value={folder.id}>{folder.name}</option>)}
                </select>
            )}
        </article>
    );
}

import { IconButton } from '@/Components/ui/IconButton';
import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { DocumentThumbnail } from '@/Components/domain/DocumentThumbnail';
import { Badge } from '@/Components/ui/Badge';
import { Link, router } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { type ChangeEvent } from 'react';

export interface WorkspaceDocument {
    id: number;
    judul: string;
    nomor: string;
    tipe: string;
    thumbnail_tersedia: boolean;
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
    mode = 'tabel',
}: {
    document: WorkspaceDocument;
    folderOptions?: WorkspaceFolderOption[];
    currentFolderId?: number | null;
    mode?: 'tabel' | 'grid';
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

    if (mode === 'grid') {
        return (
            <article className="flex h-full min-w-0 flex-col overflow-hidden rounded-card border border-line bg-surface transition-shadow hover:shadow-pop">
                <DocumentThumbnail
                    id={document.id}
                    mime={document.tipe}
                    judul={document.judul}
                    tersedia={document.thumbnail_tersedia}
                    className="h-36 rounded-none"
                />
                <div className="flex min-w-0 flex-1 flex-col p-4">
                    <div className="flex items-start justify-between gap-2">
                        <FileTypeBadge mime={document.tipe} />
                        <IconButton
                            type="button"
                            icon={Star}
                            label={document.starred ? `Hapus bintang ${document.judul}` : `Beri bintang ${document.judul}`}
                            variant="ghost"
                            className={document.starred ? 'text-warning-strong' : undefined}
                            iconClassName={document.starred ? 'fill-warning text-warning-strong' : undefined}
                            onClick={toggleStar}
                        />
                    </div>
                    <Link href={`/documents/${document.id}`} className="mt-3 min-w-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700">
                        <h3 className="line-clamp-2 text-sm font-medium text-ink">{document.judul}</h3>
                        <p className="mt-1 truncate font-mono text-xs text-ink-subtle">{document.nomor}</p>
                    </Link>
                    <div className="mt-auto flex flex-wrap items-center justify-between gap-2 pt-3">
                        {document.is_private ? <Badge variant="info" size="sm">Hanya saya</Badge> : <span />}
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
                    </div>
                </div>
            </article>
        );
    }

    return (
        <article className="flex min-w-0 items-center gap-3 px-4 py-3.5 transition-colors hover:bg-surface-sunken">
            <FileTypeBadge mime={document.tipe} size="md" />
            <Link href={`/documents/${document.id}`} className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium text-ink">{document.judul}</span>
                <span className="mt-0.5 block truncate text-xs text-ink-muted">{document.nomor}</span>
                <span className="mt-1 flex flex-wrap gap-1.5">
                    {document.is_private && <Badge variant="info" size="sm">Hanya saya</Badge>}
                </span>
            </Link>
            <IconButton
                type="button"
                icon={Star}
                label={document.starred ? `Hapus bintang ${document.judul}` : `Beri bintang ${document.judul}`}
                variant="ghost"
                className={document.starred ? 'text-warning-strong' : undefined}
                iconClassName={document.starred ? 'fill-warning text-warning-strong' : undefined}
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

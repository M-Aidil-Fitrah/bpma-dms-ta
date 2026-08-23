import { IconButton } from '@/Components/ui/IconButton';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { DocumentThumbnail } from '@/Components/domain/DocumentThumbnail';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Field } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Link, router } from '@inertiajs/react';
import { FolderInput, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

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
    const { t } = useTranslation('workspace');

    function toggleStar() {
        if (document.starred) {
            router.delete(`/documents/${document.id}/star`, { preserveScroll: true });

            return;
        }

        router.put(`/documents/${document.id}/star`, {}, { preserveScroll: true });
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
                        <div className="flex items-center gap-1">
                            <IconButton
                                type="button"
                                icon={Star}
                                label={document.starred ? t('documentCard.bintang.hapus', { judul: document.judul }) : t('documentCard.bintang.beri', { judul: document.judul })}
                                className={document.starred ? 'text-warning-strong' : undefined}
                                iconClassName={document.starred ? 'fill-warning text-warning-strong' : undefined}
                                onClick={toggleStar}
                            />
                            {folderOptions !== undefined && <TrashDocumentAction document={document} />}
                        </div>
                    </div>
                    <Link href={`/documents/${document.id}`} className="mt-3 min-w-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700">
                        <h3 className="line-clamp-2 text-sm font-medium text-ink">{document.judul}</h3>
                        <p className="mt-1 truncate font-mono text-xs text-ink-subtle">{document.nomor}</p>
                    </Link>
                    <div className="mt-auto flex flex-wrap items-center justify-between gap-2 pt-3">
                        {document.is_private ? <Badge variant="info" size="sm">{t('documentCard.hanyaSaya')}</Badge> : <span />}
                        {folderOptions !== undefined && <MoveDocumentAction document={document} folderOptions={folderOptions} currentFolderId={currentFolderId} />}
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
                    {document.is_private && <Badge variant="info" size="sm">{t('documentCard.hanyaSaya')}</Badge>}
                </span>
            </Link>
            <IconButton
                type="button"
                icon={Star}
                label={document.starred ? t('documentCard.bintang.hapus', { judul: document.judul }) : t('documentCard.bintang.beri', { judul: document.judul })}
                className={document.starred ? 'text-warning-strong' : undefined}
                iconClassName={document.starred ? 'fill-warning text-warning-strong' : undefined}
                onClick={toggleStar}
            />
            {folderOptions !== undefined && <TrashDocumentAction document={document} />}
            {folderOptions !== undefined && <MoveDocumentAction document={document} folderOptions={folderOptions} currentFolderId={currentFolderId} compact />}
        </article>
    );
}

function TrashDocumentAction({ document }: { document: WorkspaceDocument; }) {
    const { t } = useTranslation('workspace');
    const konfirmasikan = usePasswordConfirmation();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function trash() {
        konfirmasikan(() => {
            setProcessing(true);
            router.delete(`/documents/${document.id}`, {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setOpen(false);
                },
            });
        });
    }

    return (
        <>
            <IconButton
                icon={Trash2}
                label={t('documentCard.pindahkanKeSampah.label', { judul: document.judul })}
                variant="danger"
                onClick={() => setOpen(true)}
            />
            <ConfirmDialog
                terbuka={open}
                onTutup={() => setOpen(false)}
                onSetuju={trash}
                judul={t('documentCard.pindahkanKeSampah.dialog.judul')}
                labelSetuju={t('documentCard.pindahkanKeSampah.dialog.labelSetuju')}
                ikon={Trash2}
                memproses={processing}
            >
                <p>{t('documentCard.pindahkanKeSampah.dialog.isiSebelum')} <span className="font-medium text-ink">{document.judul}</span> {t('documentCard.pindahkanKeSampah.dialog.isiSesudah')}</p>
                <p>{t('documentCard.pindahkanKeSampah.dialog.isiKedua')}</p>
            </ConfirmDialog>
        </>
    );
}

function MoveDocumentAction({
    document,
    folderOptions,
    currentFolderId,
    compact = false,
}: {
    document: WorkspaceDocument;
    folderOptions: WorkspaceFolderOption[];
    currentFolderId: number | null;
    compact?: boolean;
}) {
    const { t } = useTranslation(['workspace', 'common']);
    const [open, setOpen] = useState(false);
    const [targetFolderId, setTargetFolderId] = useState<string>(currentFolderId?.toString() ?? 'root');
    const [processing, setProcessing] = useState(false);
    const targetOptions = [
        { value: 'root', label: t('documentCard.pindahkan.dialog.tanpaFolder') },
        ...folderOptions.map((folder) => ({ value: folder.id, label: folder.name })),
    ];

    function openDialog() {
        setTargetFolderId(currentFolderId?.toString() ?? 'root');
        setOpen(true);
    }

    function move() {
        setProcessing(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        };

        if (targetFolderId === 'root') {
            router.delete(`/documents/${document.id}/folder`, options);

            return;
        }

        router.put(`/documents/${document.id}/folder`, { folder_id: Number(targetFolderId) }, options);
    }

    return (
        <>
            {compact ? (
                <IconButton icon={FolderInput} label={t('documentCard.pindahkan.labelDenganNama', { judul: document.judul })} onClick={openDialog} />
            ) : (
                <Button type="button" icon={FolderInput} size="xs" variant="secondary" onClick={openDialog}>{t('documentCard.pindahkan.label')}</Button>
            )}
            <Modal
                terbuka={open}
                onTutup={setOpen}
                judul={t('documentCard.pindahkan.dialog.judul')}
                keterangan={t('documentCard.pindahkan.dialog.keterangan')}
                footer={<><Button variant="secondary" onClick={() => setOpen(false)} disabled={processing}>{t('common:aksi.batal')}</Button><Button icon={FolderInput} onClick={move} loading={processing}>{t('documentCard.pindahkan.dialog.tombolPindahkan')}</Button></>}
            >
                <Field label={t('documentCard.pindahkan.dialog.labelFolder')}>
                    {(props) => <Select {...props} value={targetFolderId} options={targetOptions} onChange={(event) => setTargetFolderId(event.target.value)} />}
                </Field>
            </Modal>
        </>
    );
}

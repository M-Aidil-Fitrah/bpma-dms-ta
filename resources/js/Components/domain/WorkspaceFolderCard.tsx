import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Field } from '@/Components/ui/Field';
import { IconButton } from '@/Components/ui/IconButton';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { FolderSharePicker } from '@/Components/domain/FolderSharePicker';
import type { PenggunaTerpilih } from '@/Components/domain/UserPicker';
import type { UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { Link, router, useForm } from '@inertiajs/react';
import { Folder, Pencil, Share2, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

interface Props {
    folder: {
        id: number;
        name: string;
        unit_entries: { id: number; role: 'viewer' | 'editor' }[];
        user_entries: (PenggunaTerpilih & { role: 'viewer' | 'editor' })[];
        sharing_restricted: boolean;
    };
    accessLevel: 'owner' | 'editor' | 'viewer';
    unitOptions: readonly UnitPilihan[];
}

export function WorkspaceFolderCard({ folder, accessLevel, unitOptions }: Props) {
    const { t } = useTranslation(['workspace', 'common']);
    const [renameOpen, setRenameOpen] = useState(false);
    const [trashOpen, setTrashOpen] = useState(false);
    const [shareOpen, setShareOpen] = useState(false);
    const [shareProcessing, setShareProcessing] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({ name: folder.name });

    // `editor` boleh mengubah nama & (bila tidak dikunci) membagikan folder,
    // tapi tidak boleh memindahkannya ke Sampah. `viewer` tidak punya aksi.
    const canRename = accessLevel === 'owner' || accessLevel === 'editor';
    const canTrash = accessLevel === 'owner';
    const canShare = accessLevel === 'owner' || (accessLevel === 'editor' && !folder.sharing_restricted);

    function rename(event: FormEvent) {
        event.preventDefault();
        patch(`/folders/${folder.id}`, { onSuccess: () => setRenameOpen(false) });
    }

    function share(nilai: {
        units: { id: number; role: 'viewer' | 'editor' }[];
        users: { id: number; role: 'viewer' | 'editor' }[];
        sharing_restricted?: boolean;
    }) {
        setShareProcessing(true);
        router.put(`/folders/${folder.id}/share`, nilai, {
            preserveScroll: true,
            onSuccess: () => setShareOpen(false),
            onFinish: () => setShareProcessing(false),
        });
    }

    return (
        <article className="flex min-h-touch min-w-0 items-center gap-3 rounded-lg border border-line bg-surface p-4 transition-colors hover:border-brand-300 hover:bg-brand-50/30">
            <Link href={`/folders/${folder.id}`} className="flex min-w-0 flex-1 items-center gap-3 font-medium text-ink">
                <Folder className="size-5 shrink-0 text-brand-700" aria-hidden />
                <span className="truncate">{folder.name}</span>
            </Link>
            {(canShare || canRename || canTrash) && (
                <div className="flex shrink-0 items-center gap-1">
                    {canShare && (
                        <IconButton
                            icon={Share2}
                            label={t('workspace:folderCard.bagikan.label', { nama: folder.name })}
                            size="sm"
                            onClick={() => setShareOpen(true)}
                        />
                    )}
                    {canRename && (
                        <IconButton icon={Pencil} label={t('workspace:folderCard.ubahNama.label', { nama: folder.name })} size="sm" onClick={() => setRenameOpen(true)} />
                    )}
                    {canTrash && (
                        <IconButton
                            icon={Trash2}
                            label={t('workspace:folderCard.pindahkanKeSampah.label', { nama: folder.name })}
                            variant="danger"
                            size="sm"
                            onClick={() => setTrashOpen(true)}
                        />
                    )}
                </div>
            )}

            {canRename && (
                <Modal
                    terbuka={renameOpen}
                    onTutup={() => setRenameOpen(false)}
                    judul={t('workspace:folderCard.ubahNama.dialog.judul')}
                    footer={<><Button variant="secondary" onClick={() => setRenameOpen(false)}>{t('common:aksi.batal')}</Button><Button type="submit" form={`ubah-folder-${folder.id}`} loading={processing}>{t('common:aksi.simpan')}</Button></>}
                >
                    <form id={`ubah-folder-${folder.id}`} onSubmit={rename}>
                        <Field label={t('workspace:folderCard.ubahNama.dialog.labelNama')} error={errors.name} required>
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
            )}

            {canTrash && (
                <ConfirmDialog
                    terbuka={trashOpen}
                    onTutup={() => setTrashOpen(false)}
                    onSetuju={() => router.delete(`/folders/${folder.id}`)}
                    judul={t('workspace:folderCard.pindahkanKeSampah.dialog.judul')}
                    labelSetuju={t('workspace:folderCard.pindahkanKeSampah.dialog.labelSetuju')}
                    ikon={Trash2}
                >
                    {t('workspace:folderCard.pindahkanKeSampah.dialog.isiSebelum')} <span className="font-medium">{folder.name}</span> {t('workspace:folderCard.pindahkanKeSampah.dialog.isiSesudah')}
                </ConfirmDialog>
            )}

            {canShare && (
                <FolderSharePicker
                    terbuka={shareOpen}
                    onTutup={() => setShareOpen(false)}
                    onSubmit={share}
                    unitOptions={unitOptions}
                    unitEntries={folder.unit_entries}
                    userEntries={folder.user_entries}
                    canRestrict={accessLevel === 'owner'}
                    sharingRestricted={folder.sharing_restricted}
                    processing={shareProcessing}
                />
            )}
        </article>
    );
}

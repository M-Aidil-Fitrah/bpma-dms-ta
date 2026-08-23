import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Field } from '@/Components/ui/Field';
import { IconButton } from '@/Components/ui/IconButton';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Link, router, useForm } from '@inertiajs/react';
import { Folder, Pencil, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

interface Props {
    folder: { id: number; name: string };
}

export function WorkspaceFolderCard({ folder }: Props) {
    const { t } = useTranslation(['workspace', 'common']);
    const [renameOpen, setRenameOpen] = useState(false);
    const [trashOpen, setTrashOpen] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({ name: folder.name });

    function rename(event: FormEvent) {
        event.preventDefault();
        patch(`/folders/${folder.id}`, { onSuccess: () => setRenameOpen(false) });
    }

    return (
        <article className="flex min-h-touch min-w-0 items-center gap-3 rounded-lg border border-line bg-surface p-4 transition-colors hover:border-brand-300 hover:bg-brand-50/30">
            <Link href={`/folders/${folder.id}`} className="flex min-w-0 flex-1 items-center gap-3 font-medium text-ink">
                <Folder className="size-5 shrink-0 text-brand-700" aria-hidden />
                <span className="truncate">{folder.name}</span>
            </Link>
            <div className="flex shrink-0 items-center gap-1">
                <IconButton icon={Pencil} label={t('workspace:folderCard.ubahNama.label', { nama: folder.name })} size="sm" onClick={() => setRenameOpen(true)} />
                <IconButton
                    icon={Trash2}
                    label={t('workspace:folderCard.pindahkanKeSampah.label', { nama: folder.name })}
                    variant="danger"
                    size="sm"
                    onClick={() => setTrashOpen(true)}
                />
            </div>

            <Modal
                terbuka={renameOpen}
                onTutup={() => setRenameOpen(false)}
                judul={t('workspace:folderCard.ubahNama.dialog.judul')}
                footer={<><Button variant="secondary" onClick={() => setRenameOpen(false)}>{t('common:aksi.batal')}</Button><Button type="submit" form={`ubah-folder-${folder.id}`} loading={processing}>{t('common:aksi.simpan')}</Button></>}
            >
                <form id={`ubah-folder-${folder.id}`} onSubmit={rename}>
                    <Field label={t('workspace:folderCard.ubahNama.dialog.labelNama')} error={errors.name} required>
                        {(props) => <Input {...props} autoFocus value={data.name} invalid={Boolean(errors.name)} onChange={(event) => setData('name', event.target.value)} />}
                    </Field>
                </form>
            </Modal>

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
        </article>
    );
}

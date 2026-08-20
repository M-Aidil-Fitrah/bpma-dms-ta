import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Field } from '@/Components/ui/Field';
import { IconButton } from '@/Components/ui/IconButton';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Link, router, useForm } from '@inertiajs/react';
import { Folder, Pencil, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Props {
    folder: { id: number; name: string };
}

export function WorkspaceFolderCard({ folder }: Props) {
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
                <IconButton icon={Pencil} label={`Ubah nama folder ${folder.name}`} size="sm" onClick={() => setRenameOpen(true)} />
                <IconButton
                    icon={Trash2}
                    label={`Pindahkan folder ${folder.name} ke Sampah`}
                    variant="danger"
                    size="sm"
                    onClick={() => setTrashOpen(true)}
                />
            </div>

            <Modal
                terbuka={renameOpen}
                onTutup={() => setRenameOpen(false)}
                judul="Ubah nama folder"
                footer={<><Button variant="secondary" onClick={() => setRenameOpen(false)}>Batal</Button><Button type="submit" form={`ubah-folder-${folder.id}`} loading={processing}>Simpan</Button></>}
            >
                <form id={`ubah-folder-${folder.id}`} onSubmit={rename}>
                    <Field label="Nama folder" error={errors.name} required>
                        {(props) => <Input {...props} autoFocus value={data.name} invalid={Boolean(errors.name)} onChange={(event) => setData('name', event.target.value)} />}
                    </Field>
                </form>
            </Modal>

            <ConfirmDialog
                terbuka={trashOpen}
                onTutup={() => setTrashOpen(false)}
                onSetuju={() => router.delete(`/folders/${folder.id}`)}
                judul="Pindahkan folder ke Sampah?"
                labelSetuju="Ya, pindahkan"
                ikon={Trash2}
            >
                Folder <span className="font-medium">{folder.name}</span> dan seluruh subfoldernya dapat dipulihkan selama 30 hari.
            </ConfirmDialog>
        </article>
    );
}

import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { Button } from '@/Components/ui/Button';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Dropdown, DropdownItem } from '@/Components/ui/Dropdown';
import { Field } from '@/Components/ui/Field';
import { IconButton } from '@/Components/ui/IconButton';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { cn } from '@/lib/cn';
import { Link, router } from '@inertiajs/react';
import { Download, Eye, FolderInput, MoreHorizontal, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export interface WorkspaceFolderOption { id: number; name: string; }

export interface WorkspaceDocumentActionsProps {
    document: App.Data.DocumentListData;
    /** Diteruskan bila dokumen berada di dalam sebuah workspace ber-folder (Dokumen Saya) — bila tidak diisi, aksi pindah folder disembunyikan (mis. di Terbaru/Berbintang). */
    folderOptions?: WorkspaceFolderOption[];
    currentFolderId?: number | null;
    className?: string;
}

/**
 * Aksi cepat dokumen pada halaman workspace (Dokumen Saya, Terbaru,
 * Berbintang) — menggantikan `DocumentActions` baku lewat prop `aksi` pada
 * `DocumentTable`/`DocumentCardList`/`DocumentGrid`.
 *
 * Lihat/Unduh tetap sama seperti Jelajahi Dokumen; yang berbeda adalah
 * tombol bintang selalu tampil, dan menu "Lainnya" berisi Pindahkan (bila
 * `folderOptions` diisi) serta Pindahkan ke Sampah alih-alih pengaturan
 * akses — dokumen di sini selalu milik pengunggahnya sendiri.
 */
export function WorkspaceDocumentActions({
    document,
    folderOptions,
    currentFolderId = null,
    className,
}: WorkspaceDocumentActionsProps) {
    const { t } = useTranslation(['documentBrowse', 'workspace', 'common']);
    const [dialogPindahkan, setDialogPindahkan] = useState(false);
    const [dialogSampah, setDialogSampah] = useState(false);
    const bisaPindahkan = folderOptions !== undefined;
    // Terbaru/Berbintang dapat memuat dokumen orang lain yang dibagikan —
    // `bisa_dibuang` null berarti backend belum menghitungnya (di luar
    // konteks workspace), jadi diperlakukan sebagai tidak boleh.
    const bisaDibuang = document.bisa_dibuang === true;

    function toggleStar() {
        if (document.starred) {
            router.delete(`/documents/${document.id}/star`, { preserveScroll: true });

            return;
        }

        router.put(`/documents/${document.id}/star`, {}, { preserveScroll: true });
    }

    return (
        <div className={cn('flex items-center justify-end gap-1', className)}>
            <Link href={`/documents/${document.id}`} tabIndex={-1}>
                <IconButton icon={Eye} label={t('documentBrowse:actions.lihatDetail')} size="sm" />
            </Link>

            <a href={`/documents/${document.id}/file`} download tabIndex={-1}>
                <IconButton icon={Download} label={t('documentBrowse:actions.unduhBerkas')} size="sm" />
            </a>

            <IconButton
                icon={Star}
                label={document.starred
                    ? t('workspace:documentCard.bintang.hapus', { judul: document.judul })
                    : t('workspace:documentCard.bintang.beri', { judul: document.judul })}
                size="sm"
                className={document.starred ? 'text-warning-strong' : undefined}
                iconClassName={document.starred ? 'fill-warning text-warning-strong' : undefined}
                onClick={toggleStar}
            />

            {(bisaPindahkan || bisaDibuang) && (
                <Dropdown
                    trigger={<IconButton icon={MoreHorizontal} label={t('documentBrowse:actions.aksiLainnya')} size="sm" />}
                    panelClassName="w-52"
                >
                    {bisaPindahkan && (
                        <DropdownItem>
                            <button
                                type="button"
                                onClick={() => setDialogPindahkan(true)}
                                className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"
                            >
                                <FolderInput className="size-4" aria-hidden />
                                {t('workspace:documentCard.pindahkan.label')}
                            </button>
                        </DropdownItem>
                    )}

                    {bisaDibuang && (
                        <DropdownItem>
                            <button
                                type="button"
                                onClick={() => setDialogSampah(true)}
                                className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-danger data-[focus]:bg-danger-soft sm:min-h-0"
                            >
                                <Trash2 className="size-4" aria-hidden />
                                {t('common:aksi.buang')}
                            </button>
                        </DropdownItem>
                    )}
                </Dropdown>
            )}

            {bisaPindahkan && (
                <DialogPindahkan
                    terbuka={dialogPindahkan}
                    onTutup={() => setDialogPindahkan(false)}
                    document={document}
                    folderOptions={folderOptions ?? []}
                    currentFolderId={currentFolderId}
                />
            )}

            <DialogSampah
                terbuka={dialogSampah}
                onTutup={() => setDialogSampah(false)}
                document={document}
            />
        </div>
    );
}

function DialogSampah({
    terbuka,
    onTutup,
    document,
}: {
    terbuka: boolean;
    onTutup: () => void;
    document: App.Data.DocumentListData;
}) {
    const { t } = useTranslation('workspace');
    const konfirmasikan = usePasswordConfirmation();
    const [processing, setProcessing] = useState(false);

    function trash() {
        konfirmasikan(() => {
            setProcessing(true);
            router.delete(`/documents/${document.id}`, {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    onTutup();
                },
            });
        });
    }

    return (
        <ConfirmDialog
            terbuka={terbuka}
            onTutup={onTutup}
            onSetuju={trash}
            judul={t('documentCard.pindahkanKeSampah.dialog.judul')}
            labelSetuju={t('documentCard.pindahkanKeSampah.dialog.labelSetuju')}
            ikon={Trash2}
            memproses={processing}
        >
            <p>
                {t('documentCard.pindahkanKeSampah.dialog.isiSebelum')}{' '}
                <span className="font-medium text-ink">{document.judul}</span>{' '}
                {t('documentCard.pindahkanKeSampah.dialog.isiSesudah')}
            </p>
            <p>{t('documentCard.pindahkanKeSampah.dialog.isiKedua')}</p>
        </ConfirmDialog>
    );
}

function DialogPindahkan({
    terbuka,
    onTutup,
    document,
    folderOptions,
    currentFolderId,
}: {
    terbuka: boolean;
    onTutup: () => void;
    document: App.Data.DocumentListData;
    folderOptions: WorkspaceFolderOption[];
    currentFolderId: number | null;
}) {
    const { t } = useTranslation(['workspace', 'common']);
    const [targetFolderId, setTargetFolderId] = useState<string>(currentFolderId?.toString() ?? 'root');
    const [processing, setProcessing] = useState(false);
    const targetOptions = [
        { value: 'root', label: t('documentCard.pindahkan.dialog.tanpaFolder') },
        ...folderOptions.map((folder) => ({ value: folder.id, label: folder.name })),
    ];

    function move() {
        setProcessing(true);
        const options = {
            preserveScroll: true,
            onSuccess: onTutup,
            onFinish: () => setProcessing(false),
        };

        if (targetFolderId === 'root') {
            router.delete(`/documents/${document.id}/folder`, options);

            return;
        }

        router.put(`/documents/${document.id}/folder`, { folder_id: Number(targetFolderId) }, options);
    }

    return (
        <Modal
            terbuka={terbuka}
            onTutup={onTutup}
            judul={t('documentCard.pindahkan.dialog.judul')}
            keterangan={t('documentCard.pindahkan.dialog.keterangan')}
            footer={
                <>
                    <Button variant="secondary" onClick={onTutup} disabled={processing}>
                        {t('common:aksi.batal')}
                    </Button>
                    <Button icon={FolderInput} onClick={move} loading={processing}>
                        {t('documentCard.pindahkan.dialog.tombolPindahkan')}
                    </Button>
                </>
            }
        >
            <Field label={t('documentCard.pindahkan.dialog.labelFolder')}>
                {(props) => (
                    <Select
                        {...props}
                        value={targetFolderId}
                        options={targetOptions}
                        onChange={(event) => setTargetFolderId(event.target.value)}
                    />
                )}
            </Field>
        </Modal>
    );
}

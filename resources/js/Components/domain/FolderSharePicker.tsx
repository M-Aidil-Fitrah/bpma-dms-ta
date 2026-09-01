import { UnitTreePicker, type UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { UserPicker, type PenggunaTerpilih } from '@/Components/domain/UserPicker';
import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

export interface FolderSharePickerProps {
    terbuka: boolean;
    onTutup: () => void;
    onSubmit: (nilai: { unit_ids: number[]; shared_user_ids: number[] }) => void;
    unitOptions: readonly UnitPilihan[];
    unitIds: readonly number[];
    sharedUsers: readonly PenggunaTerpilih[];
    processing: boolean;
}

/**
 * Dialog bagikan folder — dua bagian (unit + orang tertentu), tanpa
 * `is_private`/`is_shared_to_all`/jenjang jabatan seperti pada
 * `AccessMechanismPicker` dokumen, karena mekanisme itu tidak berlaku untuk
 * folder (default folder memang privat sampai dibagikan eksplisit).
 */
export function FolderSharePicker({
    terbuka,
    onTutup,
    onSubmit,
    unitOptions,
    unitIds,
    sharedUsers,
    processing,
}: FolderSharePickerProps) {
    const { t } = useTranslation(['workspace', 'common']);
    const [units, setUnits] = useState<number[]>([...unitIds]);
    const [pengguna, setPengguna] = useState<PenggunaTerpilih[]>([...sharedUsers]);

    useEffect(() => {
        if (terbuka) {
            setUnits([...unitIds]);
            setPengguna([...sharedUsers]);
        }
        // Sengaja cuma bergantung pada `terbuka`: menambahkan unitIds/sharedUsers
        // ke dependency akan membuat efek ini jalan ulang setiap kali parent
        // re-render selagi dialog terbuka (kedua array itu bukan referensi yang
        // stabil), menghapus pilihan yang sedang diedit pengguna sebelum sempat
        // disimpan.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [terbuka]);

    if (!terbuka) {
        return null;
    }

    function submit() {
        onSubmit({ unit_ids: units, shared_user_ids: pengguna.map((item) => item.id) });
    }

    return (
        <Modal
            terbuka={terbuka}
            onTutup={onTutup}
            judul={t('workspace:folderCard.bagikan.dialog.judul')}
            keterangan={t('workspace:folderCard.bagikan.dialog.keterangan')}
            footer={
                <>
                    <Button variant="secondary" onClick={onTutup} disabled={processing}>
                        {t('common:aksi.batal')}
                    </Button>
                    <Button onClick={submit} loading={processing}>
                        {t('workspace:folderCard.bagikan.dialog.tombolSimpan')}
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                <div>
                    <h3 className="mb-2 text-sm font-medium text-ink">{t('workspace:folderCard.bagikan.dialog.labelUnit')}</h3>
                    <UnitTreePicker units={unitOptions} terpilih={units} onChange={setUnits} />
                </div>
                <div>
                    <h3 className="mb-2 text-sm font-medium text-ink">{t('workspace:folderCard.bagikan.dialog.labelOrang')}</h3>
                    <UserPicker terpilih={pengguna} onChange={setPengguna} />
                </div>
            </div>
        </Modal>
    );
}

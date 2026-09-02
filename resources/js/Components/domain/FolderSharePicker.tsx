import { UnitTreePicker, type UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { UserPicker, type PenggunaTerpilih } from '@/Components/domain/UserPicker';
import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

export interface FolderSharePickerProps {
    terbuka: boolean;
    onTutup: () => void;
    onSubmit: (nilai: {
        units: { id: number; role: 'viewer' | 'editor' }[];
        users: { id: number; role: 'viewer' | 'editor' }[];
        sharing_restricted?: boolean;
    }) => void;
    unitOptions: readonly UnitPilihan[];
    unitEntries: readonly { id: number; role: 'viewer' | 'editor' }[];
    userEntries: readonly (PenggunaTerpilih & { role: 'viewer' | 'editor' })[];
    canRestrict: boolean;
    sharingRestricted: boolean;
    processing: boolean;
}

function petaRole(
    entries: readonly { id: number; role: 'viewer' | 'editor' }[],
): Record<number, 'viewer' | 'editor'> {
    return Object.fromEntries(entries.map((entry) => [entry.id, entry.role]));
}

function tanpaRole(
    entries: readonly (PenggunaTerpilih & { role: 'viewer' | 'editor' })[],
): PenggunaTerpilih[] {
    return entries.map(({ id, nama, jabatan, unit }) => ({ id, nama, jabatan, unit }));
}

/**
 * Dialog bagikan folder — dua bagian (unit + orang tertentu), tanpa
 * `is_private`/`is_shared_to_all`/jenjang jabatan seperti pada
 * `AccessMechanismPicker` dokumen, karena mekanisme itu tidak berlaku untuk
 * folder (default folder memang privat sampai dibagikan eksplisit).
 *
 * Peran per-penerima (viewer/editor) disimpan sebagai state terpisah dari
 * daftar id `UnitTreePicker`/`UserPicker`, supaya kedua pemilih itu tetap
 * bisa dipakai bersama dengan pembagian dokumen tanpa perubahan.
 */
export function FolderSharePicker({
    terbuka,
    onTutup,
    onSubmit,
    unitOptions,
    unitEntries,
    userEntries,
    canRestrict,
    sharingRestricted,
    processing,
}: FolderSharePickerProps) {
    const { t } = useTranslation(['workspace', 'common']);
    const [units, setUnits] = useState<number[]>(() => unitEntries.map((entry) => entry.id));
    const [pengguna, setPengguna] = useState<PenggunaTerpilih[]>(() => tanpaRole(userEntries));
    const [unitRoles, setUnitRoles] = useState<Record<number, 'viewer' | 'editor'>>(() =>
        petaRole(unitEntries),
    );
    const [userRoles, setUserRoles] = useState<Record<number, 'viewer' | 'editor'>>(() =>
        petaRole(userEntries),
    );
    const [dikunci, setDikunci] = useState(sharingRestricted);

    useEffect(() => {
        if (terbuka) {
            setUnits(unitEntries.map((entry) => entry.id));
            setPengguna(tanpaRole(userEntries));
            setUnitRoles(petaRole(unitEntries));
            setUserRoles(petaRole(userEntries));
            setDikunci(sharingRestricted);
        }
        // Sengaja cuma bergantung pada `terbuka`: menambahkan unitEntries/userEntries
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
        onSubmit({
            units: units.map((id) => ({ id, role: unitRoles[id] ?? 'viewer' })),
            users: pengguna.map((p) => ({ id: p.id, role: userRoles[p.id] ?? 'viewer' })),
            ...(canRestrict ? { sharing_restricted: dikunci } : {}),
        });
    }

    const labelUnit = (id: number) => unitOptions.find((unit) => unit.id === id)?.nama ?? `#${id}`;
    const adaPenerima = units.length > 0 || pengguna.length > 0;

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

                {adaPenerima && (
                    <div>
                        <h3 className="mb-2 text-sm font-medium text-ink">
                            {t('workspace:folderCard.bagikan.dialog.labelRole')}
                        </h3>
                        <div className="space-y-2">
                            {units.map((id) => (
                                <div key={`unit-${id}`} className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-ink">{labelUnit(id)}</span>
                                    <select
                                        aria-label={`${labelUnit(id)} — ${t('workspace:folderCard.bagikan.dialog.labelRole')}`}
                                        className="rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink"
                                        value={unitRoles[id] ?? 'viewer'}
                                        onChange={(e) =>
                                            setUnitRoles((prev) => ({
                                                ...prev,
                                                [id]: e.target.value as 'viewer' | 'editor',
                                            }))
                                        }
                                    >
                                        <option value="viewer">
                                            {t('workspace:folderCard.bagikan.dialog.roleViewer')}
                                        </option>
                                        <option value="editor">
                                            {t('workspace:folderCard.bagikan.dialog.roleEditor')}
                                        </option>
                                    </select>
                                </div>
                            ))}
                            {pengguna.map((p) => (
                                <div key={`user-${p.id}`} className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-ink">{p.nama}</span>
                                    <select
                                        aria-label={`${p.nama} — ${t('workspace:folderCard.bagikan.dialog.labelRole')}`}
                                        className="rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink"
                                        value={userRoles[p.id] ?? 'viewer'}
                                        onChange={(e) =>
                                            setUserRoles((prev) => ({
                                                ...prev,
                                                [p.id]: e.target.value as 'viewer' | 'editor',
                                            }))
                                        }
                                    >
                                        <option value="viewer">
                                            {t('workspace:folderCard.bagikan.dialog.roleViewer')}
                                        </option>
                                        <option value="editor">
                                            {t('workspace:folderCard.bagikan.dialog.roleEditor')}
                                        </option>
                                    </select>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {canRestrict && (
                    <label className="flex items-center gap-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            checked={dikunci}
                            onChange={(e) => setDikunci(e.target.checked)}
                        />
                        {t('workspace:folderCard.bagikan.dialog.kunciPembagian')}
                    </label>
                )}
            </div>
        </Modal>
    );
}

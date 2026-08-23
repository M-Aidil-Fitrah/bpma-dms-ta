import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { IconButton } from '@/Components/ui/IconButton';
import { Link, router } from '@inertiajs/react';
import { Pencil, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export type ReferenceResourceKind = 'jabatan' | 'unit' | 'kategori';

export interface ReferenceResourceActionsProps {
    jenis: ReferenceResourceKind;
    id: number;
    nama: string;
    aktif: boolean;
    dampak: readonly string[];
}

const PATH: Record<ReferenceResourceKind, string> = {
    jabatan: 'jabatans',
    unit: 'units',
    kategori: 'categories',
};

export function ReferenceResourceActions({ jenis, id, nama, aktif, dampak }: ReferenceResourceActionsProps) {
    const { t } = useTranslation('reference');
    const konfirmasikan = usePasswordConfirmation();
    const [konfirmasi, setKonfirmasi] = useState(false);
    const [memproses, setMemproses] = useState(false);
    const path = `/admin/${PATH[jenis]}/${id}`;
    const label = t(`${jenis}.labelKecil`);

    function nonaktifkan() {
        konfirmasikan(jalankanNonaktifkan);
    }

    function jalankanNonaktifkan() {
        setMemproses(true);
        router.delete(path, {
            onFinish: () => {
                setMemproses(false);
                setKonfirmasi(false);
            },
        });
    }

    function aktifkan() {
        konfirmasikan(jalankanAktifkan);
    }

    function jalankanAktifkan() {
        setMemproses(true);
        router.patch(`${path}/restore`, {}, { onFinish: () => setMemproses(false) });
    }

    const labelKapital = label[0].toUpperCase() + label.slice(1);

    return (
        <div className="flex items-center justify-end gap-1">
            <Link href={`${path}/edit`} tabIndex={-1}>
                <IconButton icon={Pencil} label={t('actions.ubahLabel', { label })} size="sm" />
            </Link>

            {aktif ? (
                <IconButton
                    icon={Trash2}
                    label={t('actions.nonaktifkanLabel', { label })}
                    size="sm"
                    variant="danger"
                    onClick={() => setKonfirmasi(true)}
                />
            ) : (
                <IconButton
                    icon={RotateCcw}
                    label={t('actions.aktifkanKembaliLabel', { label })}
                    size="sm"
                    onClick={aktifkan}
                    disabled={memproses}
                />
            )}

            <ConfirmDialog
                terbuka={konfirmasi}
                onTutup={() => setKonfirmasi(false)}
                onSetuju={nonaktifkan}
                judul={t('actions.confirm.judul', { nama })}
                labelSetuju={t('actions.confirm.setuju')}
                ikon={Trash2}
                memproses={memproses}
            >
                <p>
                    {t('actions.confirm.tidakMuncul', { label: labelKapital })}
                </p>
                {dampak.length > 0 ? (
                    <p>
                        {t('actions.confirm.masihTerkaitAwalan')}{' '}
                        <span className="font-medium text-ink">{dampak.join(', ')}</span>
                        {t('actions.confirm.masihTerkaitAkhiran')}
                    </p>
                ) : (
                    <p>{t('actions.confirm.tidakAdaTerkait')}</p>
                )}
            </ConfirmDialog>
        </div>
    );
}

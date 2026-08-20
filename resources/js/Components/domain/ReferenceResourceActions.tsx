import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { IconButton } from '@/Components/ui/IconButton';
import { Link, router } from '@inertiajs/react';
import { Pencil, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';

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
    const konfirmasikan = usePasswordConfirmation();
    const [konfirmasi, setKonfirmasi] = useState(false);
    const [memproses, setMemproses] = useState(false);
    const path = `/admin/${PATH[jenis]}/${id}`;
    const label = jenis === 'unit' ? 'unit kerja' : jenis;

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

    return (
        <div className="flex items-center justify-end gap-1">
            <Link href={`${path}/edit`} tabIndex={-1}>
                <IconButton icon={Pencil} label={`Ubah ${label}`} size="sm" />
            </Link>

            {aktif ? (
                <IconButton
                    icon={Trash2}
                    label={`Nonaktifkan ${label}`}
                    size="sm"
                    variant="danger"
                    onClick={() => setKonfirmasi(true)}
                />
            ) : (
                <IconButton
                    icon={RotateCcw}
                    label={`Aktifkan kembali ${label}`}
                    size="sm"
                    onClick={aktifkan}
                    disabled={memproses}
                />
            )}

            <ConfirmDialog
                terbuka={konfirmasi}
                onTutup={() => setKonfirmasi(false)}
                onSetuju={nonaktifkan}
                judul={`Nonaktifkan ${nama}?`}
                labelSetuju="Ya, nonaktifkan"
                ikon={Trash2}
                memproses={memproses}
            >
                <p>
                    {label[0].toUpperCase() + label.slice(1)} ini tidak akan muncul pada pilihan baru,
                    tetapi tidak dihapus dari riwayat yang sudah ada.
                </p>
                {dampak.length > 0 ? (
                    <p>
                        Masih terkait dengan <span className="font-medium text-ink">{dampak.join(', ')}</span>.
                        Data tersebut tetap utuh.
                    </p>
                ) : (
                    <p>Belum ada pengguna atau dokumen yang terkait dengannya.</p>
                )}
            </ConfirmDialog>
        </div>
    );
}

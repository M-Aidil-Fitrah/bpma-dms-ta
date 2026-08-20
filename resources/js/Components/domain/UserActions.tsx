import { ResetPasswordDialog } from '@/Components/domain/ResetPasswordDialog';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Dropdown, DropdownItem } from '@/Components/ui/Dropdown';
import { IconButton } from '@/Components/ui/IconButton';
import { cn } from '@/lib/cn';
import { Link, router } from '@inertiajs/react';
import { KeyRound, MoreHorizontal, Pencil, UserCheck, UserX } from 'lucide-react';
import { useState } from 'react';

export interface UserActionsProps {
    userId: number;
    nama: string;
    aktif: boolean;
    /** Superadmin tidak dapat menonaktifkan akunnya sendiri. */
    diriSendiri: boolean;
    className?: string;
}

/**
 * Tombol aksi pada tiap baris/kartu di daftar pengguna, dan di bilah atas
 * halaman ubah (FR-26, FR-27, FEAT-13).
 *
 * Sama seperti `DocumentHeaderActions`: tombol yang tidak boleh dipakai
 * tidak dirender, tapi itu semata kerapian. Setiap aksi tetap diperiksa di
 * server lewat middleware `superadmin` dan penjagaan diri-sendiri di
 * `UserController::destroy()` (FR-43).
 */
export function UserActions({ userId, nama, aktif, diriSendiri, className }: UserActionsProps) {
    const konfirmasikan = usePasswordConfirmation();
    const [tanyaNonaktif, setTanyaNonaktif] = useState(false);
    const [resetSandi, setResetSandi] = useState(false);
    const [memproses, setMemproses] = useState(false);

    function nonaktifkan() {
        konfirmasikan(jalankanNonaktifkan);
    }

    function jalankanNonaktifkan() {
        setMemproses(true);
        router.delete(`/admin/users/${userId}`, {
            onFinish: () => {
                setMemproses(false);
                setTanyaNonaktif(false);
            },
        });
    }

    function aktifkan() {
        konfirmasikan(jalankanAktifkan);
    }

    function jalankanAktifkan() {
        setMemproses(true);
        router.patch(
            `/admin/users/${userId}/restore`,
            {},
            { onFinish: () => setMemproses(false) },
        );
    }

    return (
        <div className={cn('flex items-center justify-end gap-1', className)}>
            <Link href={`/admin/users/${userId}/edit`} tabIndex={-1}>
                <IconButton icon={Pencil} label="Ubah pengguna" size="sm" />
            </Link>

            <Dropdown
                trigger={<IconButton icon={MoreHorizontal} label="Aksi lainnya" size="sm" />}
                panelClassName="w-56"
            >
                    <DropdownItem>
                        <button
                            type="button"
                            onClick={() => setResetSandi(true)}
                            className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"
                        >
                            <KeyRound className="size-4" aria-hidden />
                            Atur ulang kata sandi
                        </button>
                    </DropdownItem>

                    {aktif ? (
                        !diriSendiri && (
                            <DropdownItem>
                                <button
                                    type="button"
                                    onClick={() => setTanyaNonaktif(true)}
                                    className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-danger data-[focus]:bg-danger-soft sm:min-h-0"
                                >
                                    <UserX className="size-4" aria-hidden />
                                    Nonaktifkan
                                </button>
                            </DropdownItem>
                        )
                    ) : (
                        <DropdownItem>
                            <button
                                type="button"
                                onClick={aktifkan}
                                className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"
                            >
                                <UserCheck className="size-4" aria-hidden />
                                Aktifkan kembali
                            </button>
                        </DropdownItem>
                    )}
            </Dropdown>

            <ConfirmDialog
                terbuka={tanyaNonaktif}
                onTutup={() => setTanyaNonaktif(false)}
                onSetuju={nonaktifkan}
                judul={`Nonaktifkan ${nama}?`}
                labelSetuju="Ya, nonaktifkan"
                ikon={UserX}
                memproses={memproses}
            >
                <p>
                    Akun <span className="font-medium text-ink">{nama}</span> tidak akan
                    bisa masuk lagi, dan sesi yang sedang berjalan langsung terputus.
                </p>
                <p>
                    Akunnya <span className="font-medium text-ink">tidak dihapus</span>.
                    Riwayat dan dokumen yang pernah diunggahnya tetap tersimpan, dan dapat
                    diaktifkan kembali kapan saja.
                </p>
            </ConfirmDialog>

            <ResetPasswordDialog
                terbuka={resetSandi}
                onTutup={() => setResetSandi(false)}
                userId={userId}
                nama={nama}
            />
        </div>
    );
}

import { ResetPasswordDialog } from '@/Components/domain/ResetPasswordDialog';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { IconButton } from '@/Components/ui/IconButton';
import { cn } from '@/lib/cn';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
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
    const [tanyaNonaktif, setTanyaNonaktif] = useState(false);
    const [resetSandi, setResetSandi] = useState(false);
    const [memproses, setMemproses] = useState(false);

    function nonaktifkan() {
        setMemproses(true);
        router.delete(`/admin/users/${userId}`, {
            onFinish: () => {
                setMemproses(false);
                setTanyaNonaktif(false);
            },
        });
    }

    function aktifkan() {
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

            <Menu as="div" className="relative">
                <MenuButton
                    aria-label="Aksi lainnya"
                    className="inline-flex size-8 min-h-touch min-w-touch items-center justify-center rounded-lg border border-line bg-white text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700 sm:min-h-0 sm:min-w-0"
                >
                    <MoreHorizontal className="size-4" aria-hidden />
                </MenuButton>

                <MenuItems
                    anchor="bottom end"
                    className="z-50 mt-1 w-56 rounded-card border border-line bg-white p-1 shadow-pop focus:outline-none"
                >
                    <MenuItem>
                        <button
                            type="button"
                            onClick={() => setResetSandi(true)}
                            className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"
                        >
                            <KeyRound className="size-4" aria-hidden />
                            Atur ulang kata sandi
                        </button>
                    </MenuItem>

                    {aktif ? (
                        !diriSendiri && (
                            <MenuItem>
                                <button
                                    type="button"
                                    onClick={() => setTanyaNonaktif(true)}
                                    className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-danger data-[focus]:bg-danger-soft sm:min-h-0"
                                >
                                    <UserX className="size-4" aria-hidden />
                                    Nonaktifkan
                                </button>
                            </MenuItem>
                        )
                    ) : (
                        <MenuItem>
                            <button
                                type="button"
                                onClick={aktifkan}
                                className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"
                            >
                                <UserCheck className="size-4" aria-hidden />
                                Aktifkan kembali
                            </button>
                        </MenuItem>
                    )}
                </MenuItems>
            </Menu>

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

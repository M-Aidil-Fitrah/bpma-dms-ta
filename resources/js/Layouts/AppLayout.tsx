import { IconButton } from '@/Components/ui/IconButton';
import { DmsBrand } from '@/Components/ui/DmsBrand';
import { UserMenu } from '@/Layouts/Partials/UserMenu';
import { SidebarNav } from '@/Layouts/Partials/SidebarNav';
import { cn } from '@/lib/cn';
import { Dialog, DialogPanel } from '@headlessui/react';
import { Head, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

export interface AppLayoutProps {
    title: string;
    /** Ikon dan judul di bilah atas; biasanya sama dengan judul halaman. */
    header?: ReactNode;
    /** Aksi di sisi kanan bilah atas, mis. tombol Unggah. */
    actions?: ReactNode;
    children: ReactNode;
}

/**
 * Kerangka halaman untuk seluruh area yang membutuhkan autentikasi.
 *
 * Perilaku responsif — tiga titik henti sesuai `Arsitektur-Frontend.md` §8:
 *
 * - **Ponsel** (< 1024px): bilah sisi disembunyikan, dibuka sebagai laci geser
 *   lewat tombol di bilah atas. Laci menjebak fokus dan tertutup dengan `Esc`.
 * - **Tablet & desktop** (>= 1024px): bilah sisi menetap di kiri.
 *
 * Seluruh ukuran memakai satuan relatif, sehingga tampilan tetap proporsional
 * di sistem operasi dengan pengaturan skala berbeda — tanpa kode khusus per
 * platform (`Tentang_Project.md` §5c).
 */
export function AppLayout({ title, header, actions, children }: AppLayoutProps) {
    const { auth } = usePage().props;

    const [drawerOpen, setDrawerOpen] = useState(false);

    // Laci ditutup setiap kali alamat berubah. Tanpa ini, berpindah halaman di
    // ponsel meninggalkan laci tetap terbuka menutupi isi halaman.
    useEffect(() => {
        setDrawerOpen(false);
    }, [typeof window === 'undefined' ? '' : window.location.pathname]);

    const user = auth.user;

    return (
        <div className="min-h-dvh bg-surface-sunken">
            <Head title={title} />

            {/* -- Bilah sisi menetap: tablet ke atas ------------------------ */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-sidebar flex-col border-r border-line bg-surface lg:flex">
                <SidebarBrand />
                <SidebarNav />
            </aside>

            {/* -- Laci geser: ponsel --------------------------------------- */}
            <Dialog
                open={drawerOpen}
                onClose={setDrawerOpen}
                className="relative z-50 lg:hidden"
            >
                <div className="fixed inset-0 bg-ink/40" aria-hidden />

                <div className="fixed inset-0 flex">
                    <DialogPanel className="flex w-sidebar max-w-[85vw] flex-col bg-surface">
                        <div className="flex h-20 items-center justify-between border-b border-line px-4">
                            <DmsBrand className="flex-1" />
                            <IconButton
                                icon={X}
                                label="Tutup navigasi"
                                variant="ghost"
                                onClick={() => setDrawerOpen(false)}
                            />
                        </div>

                        <SidebarNav onNavigate={() => setDrawerOpen(false)} />
                    </DialogPanel>
                </div>
            </Dialog>

            {/* -- Area konten ---------------------------------------------- */}
            <div className="lg:pl-sidebar">
                <header className="sticky top-0 z-20 border-b border-line bg-surface/95 backdrop-blur">
                    <div className="flex min-h-20 items-center gap-2 px-3 py-3 sm:gap-3 sm:px-6">
                        <IconButton
                            icon={Menu}
                            label="Buka navigasi"
                            variant="ghost"
                            className="lg:hidden"
                            onClick={() => setDrawerOpen(true)}
                        />

                        <div className="min-w-0 flex-1">
                            {header ?? (
                                <h1 className="truncate text-lg font-semibold text-ink">
                                    {title}
                                </h1>
                            )}
                        </div>

                        {actions && (
                            <div className="flex shrink-0 items-center gap-2">{actions}</div>
                        )}

                        {/* Satu-satunya tempat menu pengguna berada. Sempat
                            ada pula di kaki bilah sisi, tapi itu berarti dua
                            jalan menuju hal yang sama pada satu layar —
                            pengguna jadi ragu apakah keduanya berbeda. */}
                        {user && (
                            <div className="shrink-0">
                                <UserMenu user={user} />
                            </div>
                        )}
                    </div>
                </header>

                {/* Pesan kilat ditangani `FlashToast` di `app.tsx`, bukan
                    dirender di sini. Menampilkannya di dua tempat sekaligus
                    membuat satu aksi seolah menghasilkan dua pemberitahuan. */}
                <main className="px-4 py-5 sm:px-6 sm:py-6">{children}</main>
            </div>
        </div>
    );
}

function SidebarBrand({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'flex h-20 shrink-0 items-center justify-center border-b border-line px-4',
                className,
            )}
        >
            <DmsBrand />
        </div>
    );
}

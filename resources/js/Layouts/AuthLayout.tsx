import { DmsBrand } from '@/Components/ui/DmsBrand';
import { Head } from '@inertiajs/react';
import { type ReactNode } from 'react';

export interface AuthLayoutProps {
    title: string;
    subtitle?: string;
    children: ReactNode;
}

/**
 * Kerangka halaman untuk area tanpa autentikasi — hanya masuk dan pemulihan
 * kata sandi, karena tidak ada registrasi publik (FR-24).
 */
export function AuthLayout({ title, subtitle, children }: AuthLayoutProps) {
    return (
        <div className="flex min-h-dvh flex-col bg-surface-sunken">
            <Head title={title} />

            <main className="flex flex-1 items-center justify-center px-4 py-10">
                <div className="w-full max-w-sm">
                    <div className="mb-8 flex flex-col items-center text-center">
                        <DmsBrand className="mb-6 items-center" />
                        <h1 className="text-xl font-semibold text-ink">{title}</h1>
                        {subtitle && (
                            <p className="mt-1 text-sm text-ink-muted">{subtitle}</p>
                        )}
                    </div>

                    <div className="rounded-card border border-line bg-surface p-6 shadow-card">
                        {children}
                    </div>

                    <p className="mt-6 text-center text-xs text-ink-subtle">
                        Data Management System BPMA — akses internal
                    </p>
                </div>
            </main>
        </div>
    );
}

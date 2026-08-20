import '../css/app.css';
import './bootstrap';

import { FlashToast } from '@/Components/ui/FlashToast';
import { ToastProvider } from '@/Components/ui/Toast';
import { PasswordConfirmationProvider } from '@/Components/auth/PasswordConfirmationProvider';
import { memilikiPenggunaTerautentikasi } from '@/types/auth';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        /*
         * `ToastProvider` berada di dalam `<App>` supaya ia dapat membedakan
         * halaman autentikasi (tanpa bilah atas) dari portal (dengan bilah
         * atas). Posisi komponen ini tetap sama saat halaman Inertia berganti,
         * sehingga toast yang muncul sebelum pengalihan tidak ikut hilang.
         *
         * Fungsi render di bawah menggantikan bawaan Inertia, yang tugasnya
         * juga memasang layout persisten lewat `Component.layout`. Aplikasi ini
         * tidak memakainya — tiap halaman merender `<AppLayout>` sendiri di
         * dalam JSX-nya. Bila suatu saat `Component.layout` dipakai, bagian ini
         * harus ikut menanganinya.
         */
        root.render(
            <App {...props}>
                {({ Component, props: propHalaman, key }) => {
                    const beradaDiPortal = memilikiPenggunaTerautentikasi(propHalaman);

                    return (
                        <ToastProvider posisi={beradaDiPortal ? 'portal' : 'auth'}>
                            <PasswordConfirmationProvider>
                                <FlashToast />
                                <Component key={key} {...propHalaman} />
                            </PasswordConfirmationProvider>
                        </ToastProvider>
                    );
                }}
            </App>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

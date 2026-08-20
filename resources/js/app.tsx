import '../css/app.css';
import './bootstrap';

import { FlashToast } from '@/Components/ui/FlashToast';
import { ToastProvider } from '@/Components/ui/Toast';
import { PasswordConfirmationProvider } from '@/Components/auth/PasswordConfirmationProvider';
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
         * `ToastProvider` sengaja di LUAR `<App>`: layout dilepas dan dipasang
         * ulang tiap kali halaman berganti, sehingga toast yang dimunculkan
         * tepat sebelum berpindah akan ikut lenyap bila provider-nya di dalam.
         *
         * `FlashToast` sebaliknya harus di DALAM — ia memakai `usePage()`, dan
         * konteks itu baru tersedia di bawah `<App>`.
         *
         * Fungsi render di bawah menggantikan bawaan Inertia, yang tugasnya
         * juga memasang layout persisten lewat `Component.layout`. Aplikasi ini
         * tidak memakainya — tiap halaman merender `<AppLayout>` sendiri di
         * dalam JSX-nya. Bila suatu saat `Component.layout` dipakai, bagian ini
         * harus ikut menanganinya.
         */
        root.render(
            <ToastProvider>
                <App {...props}>
                    {({ Component, props: propHalaman, key }) => (
                        <>
                            <PasswordConfirmationProvider>
                                <FlashToast />
                                <Component key={key} {...propHalaman} />
                            </PasswordConfirmationProvider>
                        </>
                    )}
                </App>
            </ToastProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

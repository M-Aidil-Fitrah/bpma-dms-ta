import '../css/app.css';
import './bootstrap';

import { FlashToast } from '@/Components/ui/FlashToast';
import { ToastProvider } from '@/Components/ui/Toast';
import { PasswordConfirmationProvider } from '@/Components/auth/PasswordConfirmationProvider';
import { memilikiPenggunaTerautentikasi } from '@/types/auth';
import i18next, { BAHASA_TERSEDIA, terapkanBahasaAwal } from '@/lib/i18n';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { useEffect } from 'react';

terapkanBahasaAwal();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Menyamakan bahasa i18next dengan prop `locale` setiap kali Inertia
 * berpindah halaman.
 *
 * `terapkanBahasaAwal()` hanya berjalan sekali saat aplikasi dimuat — cukup
 * untuk kunjungan pertama, tapi navigasi Inertia berikutnya adalah SPA
 * (tanpa reload dokumen), sehingga `<html lang>` tidak pernah dibaca ulang.
 * Tanpa komponen ini, tamu yang sempat memilih satu bahasa lalu masuk dengan
 * akun berpreferensi bahasa lain akan melihat bilah bahasa berpindah
 * (mengikuti server) sementara ISI HALAMAN tetap pada bahasa lama sampai
 * disegarkan penuh — dua sumber kebenaran yang tidak sinkron.
 */
function SinkronisasiBahasa({ locale }: { locale: string }) {
    useEffect(() => {
        const bahasa = (BAHASA_TERSEDIA as readonly string[]).includes(locale) ? locale : 'id';

        if (bahasa !== i18next.language) {
            void i18next.changeLanguage(bahasa);
        }
    }, [locale]);

    return null;
}

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
                            <SinkronisasiBahasa
                                locale={typeof propHalaman.locale === 'string' ? propHalaman.locale : 'id'}
                            />
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

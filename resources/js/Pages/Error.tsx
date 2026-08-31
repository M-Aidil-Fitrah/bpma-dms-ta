import { Button } from '@/Components/ui/Button';
import { Head } from '@inertiajs/react';
import { Home, RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface ErrorProps {
    status: number;
    /** Detik hingga rate limit dilepas; hanya terisi untuk status 429. */
    retryAfter?: number | null;
}

/**
 * Halaman galat yang dirender server lewat `bootstrap/app.php` untuk status
 * 403/404/429/500 saat `APP_DEBUG` mati. Memakai komponen Inertia — bukan Blade
 * mentah — supaya bahasa dan gaya konsisten dengan sisa aplikasi. Sengaja tanpa
 * `AppLayout`: bilah sisi butuh pengguna terautentikasi, sedangkan 403 juga
 * menimpa tamu.
 */
const KUNCI_STATUS: Record<number, string> = {
    403: 'forbidden',
    404: 'notFound',
    429: 'tooManyRequests',
    500: 'serverError',
};

export default function Error({ status, retryAfter }: ErrorProps) {
    const { t } = useTranslation('common');
    const kunci = KUNCI_STATUS[status] ?? 'serverError';
    const bolehMuatUlang = status === 429 || status >= 500;

    const keterangan =
        status === 429 && retryAfter
            ? t('errorPage.tooManyRequests.descriptionWithRetry', { seconds: retryAfter })
            : t(`errorPage.${kunci}.description`);

    return (
        <main
            role="alert"
            className="mx-auto flex min-h-dvh w-full max-w-xl flex-col items-center justify-center gap-5 p-6 text-center"
        >
            <Head title={t(`errorPage.${kunci}.title`)} />

            <p className="text-5xl font-bold tabular-nums text-ink-subtle">{status}</p>

            <div>
                <h1 className="text-xl font-semibold text-ink">{t(`errorPage.${kunci}.title`)}</h1>
                <p className="mt-2 text-sm text-ink-muted">{keterangan}</p>
            </div>

            <div className="flex flex-wrap justify-center gap-3">
                {bolehMuatUlang && (
                    <Button type="button" icon={RefreshCw} onClick={() => window.location.reload()}>
                        {t('errorBoundary.reload')}
                    </Button>
                )}
                <Button
                    type="button"
                    variant={bolehMuatUlang ? 'secondary' : 'primary'}
                    icon={Home}
                    onClick={() => window.location.assign('/')}
                >
                    {t('errorBoundary.home')}
                </Button>
            </div>
        </main>
    );
}

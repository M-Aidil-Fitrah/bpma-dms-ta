import { Button } from '@/Components/ui/Button';
import { Home, RefreshCw } from 'lucide-react';
import { Component, type ErrorInfo, type ReactNode } from 'react';
import { withTranslation, type WithTranslation } from 'react-i18next';

type VariasiBatasGalat = 'halaman' | 'pratinjau';

interface ErrorBoundaryProps extends WithTranslation {
    children: ReactNode;
    resetKey: string;
    variasi?: VariasiBatasGalat;
}

interface ErrorBoundaryState {
    hasError: boolean;
}

/**
 * Menahan error render agar satu komponen bermasalah tidak mengosongkan
 * seluruh aplikasi. Boundary di-reset saat URL Inertia berpindah, sehingga
 * halaman tujuan tetap mendapatkan kesempatan untuk dirender.
 */
class ErrorBoundaryDasar extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
    public state: ErrorBoundaryState = { hasError: false };

    public static getDerivedStateFromError(): ErrorBoundaryState {
        return { hasError: true };
    }

    public componentDidCatch(error: Error, info: ErrorInfo): void {
        console.error('React error boundary menangkap kesalahan render.', error, info);
    }

    public componentDidUpdate(previousProps: ErrorBoundaryProps): void {
        if (this.state.hasError && previousProps.resetKey !== this.props.resetKey) {
            this.setState({ hasError: false });
        }
    }

    public render(): ReactNode {
        const { children, t, variasi = 'halaman' } = this.props;

        if (!this.state.hasError) return children;

        if (variasi === 'pratinjau') {
            return (
                <div role="alert" className="flex h-full items-center justify-center p-8 text-center">
                    <p className="text-sm font-medium text-ink-muted">{t('previewError')}</p>
                </div>
            );
        }

        return (
            <main role="alert" className="mx-auto flex min-h-screen w-full max-w-xl flex-col items-center justify-center gap-5 p-6 text-center">
                <div>
                    <h1 className="text-xl font-semibold text-ink">{t('errorBoundary.title')}</h1>
                    <p className="mt-2 text-sm text-ink-muted">{t('errorBoundary.description')}</p>
                </div>
                <div className="flex flex-wrap justify-center gap-3">
                    <Button type="button" icon={RefreshCw} onClick={() => window.location.reload()}>
                        {t('errorBoundary.reload')}
                    </Button>
                    <Button type="button" variant="secondary" icon={Home} onClick={() => window.location.assign('/')}>
                        {t('errorBoundary.home')}
                    </Button>
                </div>
            </main>
        );
    }
}

export const ErrorBoundary = withTranslation('common')(ErrorBoundaryDasar);

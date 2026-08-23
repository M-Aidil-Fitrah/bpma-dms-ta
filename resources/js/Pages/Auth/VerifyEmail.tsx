import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { Link, useForm } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation('auth');
    const { post, processing } = useForm({});

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/email/verification-notification');
    }

    return (
        <AuthLayout
            title={t('verifikasiEmail.judul')}
            subtitle={t('verifikasiEmail.subjudul')}
        >
            {status === 'verification-link-sent' && (
                <Alert variant="success" className="mb-5">
                    {t('verifikasiEmail.tautanTerkirim')}
                </Alert>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
                <Button
                    type="submit"
                    icon={MailCheck}
                    loading={processing}
                    className="w-full"
                    size="lg"
                >
                    {t('verifikasiEmail.kirimUlang')}
                </Button>
            </form>

            <Link
                href="/logout"
                method="post"
                as="button"
                className="mt-5 block w-full text-center text-sm font-medium text-ink-muted hover:text-ink"
            >
                {t('verifikasiEmail.keluar')}
            </Link>
        </AuthLayout>
    );
}

import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { Link, useForm } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import { type FormEvent } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/email/verification-notification');
    }

    return (
        <AuthLayout
            title="Verifikasi Surel"
            subtitle="Buka tautan yang kami kirim ke kotak masuk Anda untuk melanjutkan"
        >
            {status === 'verification-link-sent' && (
                <Alert variant="success" className="mb-5">
                    Tautan verifikasi baru sudah dikirim ke surel Anda.
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
                    Kirim Ulang Tautan
                </Button>
            </form>

            <Link
                href="/logout"
                method="post"
                as="button"
                className="mt-5 block w-full text-center text-sm font-medium text-ink-muted hover:text-ink"
            >
                Keluar
            </Link>
        </AuthLayout>
    );
}

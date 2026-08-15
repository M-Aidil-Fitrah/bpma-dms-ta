import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Mail, Send } from 'lucide-react';
import { type FormEvent } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/forgot-password');
    }

    return (
        <AuthLayout
            title="Lupa Kata Sandi"
            subtitle="Kami akan mengirim tautan penyetelan ulang ke surel Anda"
        >
            {status && (
                <Alert variant="success" className="mb-5">
                    {status}
                </Alert>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
                <Field label="Surel" error={errors.email} required>
                    {(props) => (
                        <Input
                            {...props}
                            type="email"
                            name="email"
                            icon={Mail}
                            value={data.email}
                            autoComplete="username"
                            autoFocus
                            placeholder="nama@bpma.internal"
                            invalid={Boolean(errors.email)}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    )}
                </Field>

                <Button
                    type="submit"
                    icon={Send}
                    loading={processing}
                    className="w-full"
                    size="lg"
                >
                    Kirim Tautan
                </Button>
            </form>

            <Link
                href="/login"
                className="mt-5 flex items-center justify-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink"
            >
                <ArrowLeft className="size-4" aria-hidden />
                Kembali ke halaman masuk
            </Link>
        </AuthLayout>
    );
}

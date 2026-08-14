import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { Link, useForm } from '@inertiajs/react';
import { LogIn, Lock, Mail } from 'lucide-react';
import { type FormEvent } from 'react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    }

    return (
        <AuthLayout
            title="Masuk ke DMS BPMA"
            subtitle="Gunakan akun yang dibuatkan administrator sistem"
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

                <Field label="Kata Sandi" error={errors.password} required>
                    {(props) => (
                        <Input
                            {...props}
                            type="password"
                            name="password"
                            icon={Lock}
                            value={data.password}
                            autoComplete="current-password"
                            invalid={Boolean(errors.password)}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    )}
                </Field>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <label className="flex min-h-touch cursor-pointer items-center gap-2 text-sm text-ink-muted sm:min-h-0">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="size-4 rounded border-line text-brand-700 focus:ring-brand-700"
                        />
                        Ingat saya
                    </label>

                    {canResetPassword && (
                        <Link
                            href="/forgot-password"
                            className="text-sm font-medium text-brand-700 hover:text-brand-800"
                        >
                            Lupa kata sandi?
                        </Link>
                    )}
                </div>

                <Button
                    type="submit"
                    icon={LogIn}
                    loading={processing}
                    className="w-full"
                    size="lg"
                >
                    Masuk
                </Button>
            </form>
        </AuthLayout>
    );
}

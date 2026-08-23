import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { useToast } from '@/Components/ui/Toast';
import { useForm } from '@inertiajs/react';
import { LogIn, Lock, Mail } from 'lucide-react';
import { useEffect, useRef, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

interface LoginProps {
    status?: string;
}

export default function Login({ status }: LoginProps) {
    const { t } = useTranslation('auth');
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const { tampilkan } = useToast();
    const terakhirDiberitahukan = useRef<string | null>(null);
    const galatPembatasan = (errors as Partial<Record<string, string>>).rate_limit;
    const pesanAutentikasi = galatPembatasan ?? errors.email ?? errors.password;

    useEffect(() => {
        if (! pesanAutentikasi) {
            terakhirDiberitahukan.current = null;

            return;
        }
        if (terakhirDiberitahukan.current === pesanAutentikasi) return;

        terakhirDiberitahukan.current = pesanAutentikasi;
        tampilkan({
            status: galatPembatasan ? 'warning' : 'error',
            judul: galatPembatasan ? t('masuk.toast.dibatasiJudul') : t('masuk.toast.gagalJudul'),
            keterangan: pesanAutentikasi,
        });
    }, [galatPembatasan, pesanAutentikasi, t, tampilkan]);

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    }

    return (
        <AuthLayout
            title={t('masuk.judul')}
            subtitle={t('masuk.subjudul')}
        >
            {status && (
                <Alert variant="success" className="mb-5">
                    {status}
                </Alert>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
                <Field label={t('masuk.emailLabel')} error={errors.email} required>
                    {(props) => (
                        <Input
                            {...props}
                            type="email"
                            name="email"
                            icon={Mail}
                            value={data.email}
                            autoComplete="username"
                            autoFocus
                            placeholder={t('masuk.emailPlaceholder')}
                            invalid={Boolean(errors.email)}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    )}
                </Field>

                <Field label={t('masuk.passwordLabel')} error={errors.password} required>
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

                {/* Tanpa tautan "Lupa kata sandi?" — aplikasi ini tidak pernah
                    mengirim surel apa pun (lihat `ResetPasswordDialog.tsx`).
                    Reset kata sandi selalu manual lewat Superadmin. */}
                <label className="flex min-h-touch w-fit cursor-pointer items-center gap-2 text-sm text-ink-muted sm:min-h-0">
                    <input
                        type="checkbox"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="size-4 rounded border-line text-brand-700 focus:ring-brand-700"
                    />
                    {t('masuk.ingatSaya')}
                </label>

                <Button
                    type="submit"
                    icon={LogIn}
                    loading={processing}
                    className="w-full"
                    size="lg"
                >
                    {t('masuk.tombolMasuk')}
                </Button>
            </form>
        </AuthLayout>
    );
}

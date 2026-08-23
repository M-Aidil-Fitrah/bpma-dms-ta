import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { useForm } from '@inertiajs/react';
import { Lock, ShieldCheck } from 'lucide-react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

export default function ConfirmPassword() {
    const { t } = useTranslation('auth');
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/confirm-password', { onFinish: () => reset('password') });
    }

    return (
        <AuthLayout
            title={t('konfirmasiPassword.judul')}
            subtitle={t('konfirmasiPassword.subjudul')}
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                <Field label={t('konfirmasiPassword.passwordLabel')} error={errors.password} required>
                    {(props) => (
                        <Input
                            {...props}
                            type="password"
                            name="password"
                            icon={Lock}
                            value={data.password}
                            autoComplete="current-password"
                            autoFocus
                            invalid={Boolean(errors.password)}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    )}
                </Field>

                <Button
                    type="submit"
                    icon={ShieldCheck}
                    loading={processing}
                    className="w-full"
                    size="lg"
                >
                    {t('konfirmasiPassword.tombolKonfirmasi')}
                </Button>
            </form>
        </AuthLayout>
    );
}

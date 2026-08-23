import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { useForm } from '@inertiajs/react';
import { Check, KeyRound } from 'lucide-react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

export function UpdatePasswordForm() {
    const { t } = useTranslation('profile');
    const { data, setData, put, processing, errors, reset, recentlySuccessful } =
        useForm({
            current_password: '',
            password: '',
            password_confirmation: '',
        });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();

        put('/password', {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (formErrors) => {
                // Mengosongkan hanya kolom yang salah, bukan seluruh formulir —
                // memaksa mengetik ulang yang sudah benar hanya menambah kesal.
                if (formErrors.password) reset('password', 'password_confirmation');
                if (formErrors.current_password) reset('current_password');
            },
        });
    }

    return (
        <Card>
            <CardHeader>
                <div>
                    <CardTitle>{t('ubahPassword.judul')}</CardTitle>
                    <p className="mt-0.5 text-sm text-ink-muted">
                        {t('ubahPassword.deskripsi')}
                    </p>
                </div>
            </CardHeader>

            <CardBody>
                <form onSubmit={handleSubmit} className="max-w-lg space-y-4">
                    <Field label={t('ubahPassword.passwordSaatIniLabel')} error={errors.current_password} required>
                        {(props) => (
                            <Input
                                {...props}
                                type="password"
                                value={data.current_password}
                                autoComplete="current-password"
                                invalid={Boolean(errors.current_password)}
                                onChange={(e) => setData('current_password', e.target.value)}
                            />
                        )}
                    </Field>

                    <Field label={t('ubahPassword.passwordBaruLabel')} error={errors.password} required>
                        {(props) => (
                            <Input
                                {...props}
                                type="password"
                                value={data.password}
                                autoComplete="new-password"
                                invalid={Boolean(errors.password)}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                        )}
                    </Field>

                    <Field
                        label={t('ubahPassword.ulangiPasswordLabel')}
                        error={errors.password_confirmation}
                        required
                    >
                        {(props) => (
                            <Input
                                {...props}
                                type="password"
                                value={data.password_confirmation}
                                autoComplete="new-password"
                                invalid={Boolean(errors.password_confirmation)}
                                onChange={(e) =>
                                    setData('password_confirmation', e.target.value)
                                }
                            />
                        )}
                    </Field>

                    <div className="flex items-center gap-3">
                        <Button type="submit" icon={KeyRound} loading={processing}>
                            {t('ubahPassword.tombolPerbarui')}
                        </Button>

                        {recentlySuccessful && (
                            <span className="flex items-center gap-1.5 text-sm text-success">
                                <Check className="size-4" aria-hidden />
                                {t('tersimpan')}
                            </span>
                        )}
                    </div>
                </form>
            </CardBody>
        </Card>
    );
}

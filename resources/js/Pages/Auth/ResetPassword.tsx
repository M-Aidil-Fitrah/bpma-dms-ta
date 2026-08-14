import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { useForm } from '@inertiajs/react';
import { KeyRound, Lock, Mail } from 'lucide-react';
import { type FormEvent } from 'react';

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/reset-password', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthLayout title="Setel Ulang Kata Sandi">
            <form onSubmit={handleSubmit} className="space-y-4">
                <Field label="Surel" error={errors.email}>
                    {(props) => (
                        <Input
                            {...props}
                            type="email"
                            name="email"
                            icon={Mail}
                            value={data.email}
                            autoComplete="username"
                            invalid={Boolean(errors.email)}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    )}
                </Field>

                <Field label="Kata Sandi Baru" error={errors.password} required>
                    {(props) => (
                        <Input
                            {...props}
                            type="password"
                            name="password"
                            icon={Lock}
                            value={data.password}
                            autoComplete="new-password"
                            autoFocus
                            invalid={Boolean(errors.password)}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    )}
                </Field>

                <Field
                    label="Ulangi Kata Sandi Baru"
                    error={errors.password_confirmation}
                    required
                >
                    {(props) => (
                        <Input
                            {...props}
                            type="password"
                            name="password_confirmation"
                            icon={Lock}
                            value={data.password_confirmation}
                            autoComplete="new-password"
                            invalid={Boolean(errors.password_confirmation)}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                        />
                    )}
                </Field>

                <Button
                    type="submit"
                    icon={KeyRound}
                    loading={processing}
                    className="w-full"
                    size="lg"
                >
                    Simpan Kata Sandi
                </Button>
            </form>
        </AuthLayout>
    );
}

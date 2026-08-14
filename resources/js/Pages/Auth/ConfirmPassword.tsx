import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { useForm } from '@inertiajs/react';
import { Lock, ShieldCheck } from 'lucide-react';
import { type FormEvent } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/confirm-password', { onFinish: () => reset('password') });
    }

    return (
        <AuthLayout
            title="Konfirmasi Kata Sandi"
            subtitle="Bagian ini membutuhkan pemastian ulang sebelum dilanjutkan"
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                <Field label="Kata Sandi" error={errors.password} required>
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
                    Konfirmasi
                </Button>
            </form>
        </AuthLayout>
    );
}

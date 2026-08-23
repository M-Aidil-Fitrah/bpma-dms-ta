import { Button } from '@/Components/ui/Button';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

export interface ResetPasswordDialogProps {
    terbuka: boolean;
    onTutup: () => void;
    userId: number;
    nama: string;
}

/**
 * Superadmin mengatur ulang kata sandi akun secara langsung (FEAT-13) —
 * bukan lewat tautan surel, karena aplikasi ini tidak pernah mengirim surel
 * apa pun.
 */
export function ResetPasswordDialog({ terbuka, onTutup, userId, nama }: ResetPasswordDialogProps) {
    const { t } = useTranslation(['users', 'common']);
    const konfirmasikan = usePasswordConfirmation();
    const { data, setData, patch, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        konfirmasikan(kirimReset);
    }

    function kirimReset() {
        patch(`/admin/users/${userId}/password`, {
            onSuccess: () => {
                reset();
                onTutup();
            },
        });
    }

    return (
        <Modal
            terbuka={terbuka}
            onTutup={onTutup}
            judul={t('users:resetPasswordDialog.title', { name: nama })}
            keterangan={t('users:resetPasswordDialog.description')}
            footer={
                <>
                    <Button type="button" variant="secondary" onClick={onTutup} disabled={processing} className="w-full sm:w-auto">
                        {t('users:resetPasswordDialog.cancel')}
                    </Button>
                    <Button type="submit" form={`reset-sandi-${userId}`} loading={processing} className="w-full sm:w-auto">
                        {t('users:resetPasswordDialog.submit')}
                    </Button>
                </>
            }
        >
            <form id={`reset-sandi-${userId}`} onSubmit={handleSubmit} className="space-y-3">
                <Field label={t('users:resetPasswordDialog.newPasswordLabel')} error={errors.password} required>
                    {(props) => (
                        <Input
                            {...props}
                            type="password"
                            value={data.password}
                            invalid={Boolean(errors.password)}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    )}
                </Field>

                <Field label={t('users:resetPasswordDialog.confirmPasswordLabel')} error={errors.password_confirmation}>
                    {(props) => (
                        <Input
                            {...props}
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                    )}
                </Field>
            </form>
        </Modal>
    );
}

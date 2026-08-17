import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { type FormEvent } from 'react';

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
    const { data, setData, patch, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();

        patch(`/admin/users/${userId}/password`, {
            onSuccess: () => {
                reset();
                onTutup();
            },
        });
    }

    return (
        <Dialog open={terbuka} onClose={onTutup} className="relative z-[70]">
            <div className="fixed inset-0 bg-ink/40" aria-hidden />

            <div className="fixed inset-0 flex items-end justify-center p-4 sm:items-center">
                <DialogPanel className="w-full max-w-md rounded-card bg-white p-5 shadow-pop">
                    <div className="flex gap-3">
                        <span
                            aria-hidden
                            className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50"
                        >
                            <KeyRound className="size-5 text-brand-700" />
                        </span>

                        <div className="min-w-0 flex-1">
                            <DialogTitle className="text-base font-semibold text-ink">
                                Atur ulang kata sandi {nama}
                            </DialogTitle>
                            <p className="mt-1.5 text-sm text-ink-muted">
                                Sampaikan kata sandi baru ini langsung kepada pemiliknya —
                                tidak ada tautan surel yang dikirim.
                            </p>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit} className="mt-4 space-y-3">
                        <Field label="Kata Sandi Baru" error={errors.password} required>
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

                        <Field label="Konfirmasi Kata Sandi" error={errors.password_confirmation}>
                            {(props) => (
                                <Input
                                    {...props}
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                />
                            )}
                        </Field>

                        <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={onTutup}
                                disabled={processing}
                            >
                                Batal
                            </Button>

                            <Button type="submit" loading={processing}>
                                Atur Ulang
                            </Button>
                        </div>
                    </form>
                </DialogPanel>
            </div>
        </Dialog>
    );
}

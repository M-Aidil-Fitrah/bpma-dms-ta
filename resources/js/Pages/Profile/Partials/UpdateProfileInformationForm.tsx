import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { useForm, usePage } from '@inertiajs/react';
import { Check, Save } from 'lucide-react';
import { type FormEvent } from 'react';

export function UpdateProfileInformationForm() {
    const { auth } = usePage().props as unknown as {
        auth: { user: App.Data.AuthUserData };
    };

    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        patch('/profile');
    }

    return (
        <Card>
            <CardHeader>
                <div>
                    <CardTitle>Informasi Profil</CardTitle>
                    <p className="mt-0.5 text-sm text-ink-muted">
                        Perbarui nama dan alamat surel akun Anda.
                    </p>
                </div>
            </CardHeader>

            <CardBody>
                <form onSubmit={handleSubmit} className="max-w-lg space-y-4">
                    <Field label="Nama" error={errors.name} required>
                        {(props) => (
                            <Input
                                {...props}
                                value={data.name}
                                autoComplete="name"
                                invalid={Boolean(errors.name)}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                        )}
                    </Field>

                    <Field label="Surel" error={errors.email} required>
                        {(props) => (
                            <Input
                                {...props}
                                type="email"
                                value={data.email}
                                autoComplete="username"
                                invalid={Boolean(errors.email)}
                                onChange={(e) => setData('email', e.target.value)}
                            />
                        )}
                    </Field>

                    {/* Jabatan dan unit ditampilkan sebagai keterangan, bukan
                        kolom isian: keduanya hanya dapat diubah Superadmin
                        (FR-26), karena menentukan cakupan akses dokumen. */}
                    <dl className="grid gap-3 rounded-lg bg-surface-sunken p-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-ink-muted">Jabatan</dt>
                            <dd className="font-medium text-ink">
                                {auth.user.jabatan ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-ink-muted">Unit Kerja</dt>
                            <dd className="font-medium text-ink">{auth.user.unit ?? '—'}</dd>
                        </div>
                    </dl>

                    <div className="flex items-center gap-3">
                        <Button type="submit" icon={Save} loading={processing}>
                            Simpan
                        </Button>

                        {recentlySuccessful && (
                            <span className="flex items-center gap-1.5 text-sm text-success">
                                <Check className="size-4" aria-hidden />
                                Tersimpan
                            </span>
                        )}
                    </div>
                </form>
            </CardBody>
        </Card>
    );
}

import { Button } from '@/Components/ui/Button';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Link, useForm } from '@inertiajs/react';
import { Save, UserPlus } from 'lucide-react';
import { type FormEvent } from 'react';

export interface OpsiFormulirPengguna {
    jabatan: { id: number; nama: string; tingkat_akses: number }[];
    unit: { id: number; nama: string }[];
}

export interface NilaiAwalPengguna {
    name: string;
    email: string;
    jabatan_id: string;
    unit_id: string;
}

export interface UserFormProps {
    opsi: OpsiFormulirPengguna;
    awal: NilaiAwalPengguna;
    /** Alamat tujuan pengiriman formulir. */
    aksi: string;
    /** Alamat tombol batal. */
    batal: string;
    /**
     * `buat` menyertakan kata sandi awal; `ubah` tidak pernah menyentuh kata
     * sandi — atur ulang kata sandi adalah aksi tersendiri.
     */
    mode: 'buat' | 'ubah';
}

/**
 * Formulir akun yang dipakai bersama halaman tambah dan halaman ubah
 * pengguna (FR-25, FR-26) — sama seperti `DocumentForm`.
 *
 * Satu-satunya perbedaan nyata antara kedua mode adalah kata sandi: wajib
 * saat menambah, dan sama sekali tidak ada medannya saat menyunting.
 */
export function UserForm({ opsi, awal, aksi, batal, mode }: UserFormProps) {
    const konfirmasikan = usePasswordConfirmation();
    const { data, setData, post, patch, processing, errors } = useForm<
        NilaiAwalPengguna & { password: string; password_confirmation: string }
    >({
        ...awal,
        password: '',
        password_confirmation: '',
    });
    const jabatanTerpilih = opsi.jabatan.find((jabatan) => jabatan.id.toString() === data.jabatan_id);
    const jabatanTertinggi = jabatanTerpilih?.tingkat_akses === 1;

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        konfirmasikan(simpan);
    }

    function simpan() {
        if (mode === 'buat') {
            post(aksi);
        } else {
            patch(aksi);
        }
    }

    return (
        <form onSubmit={handleSubmit} className="max-w-xl space-y-5">
            <Card>
                <CardHeader>
                    <CardTitle>Informasi Akun</CardTitle>
                </CardHeader>
                <CardBody className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama" error={errors.name} required className="sm:col-span-2">
                        {(props) => (
                            <Input
                                {...props}
                                value={data.name}
                                invalid={Boolean(errors.name)}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                        )}
                    </Field>

                    <Field label="Surel" error={errors.email} required className="sm:col-span-2">
                        {(props) => (
                            <Input
                                {...props}
                                type="email"
                                value={data.email}
                                invalid={Boolean(errors.email)}
                                onChange={(e) => setData('email', e.target.value)}
                            />
                        )}
                    </Field>

                    <Field label="Jabatan" error={errors.jabatan_id} required>
                        {(props) => (
                            <Select
                                {...props}
                                placeholder="Pilih jabatan"
                                value={data.jabatan_id}
                                invalid={Boolean(errors.jabatan_id)}
                                options={opsi.jabatan.map((j) => ({ value: j.id, label: j.nama }))}
                                onChange={(e) => {
                                    const jabatanId = e.target.value;
                                    setData('jabatan_id', jabatanId);

                                    if (opsi.jabatan.find((jabatan) => jabatan.id.toString() === jabatanId)?.tingkat_akses === 1) {
                                        setData('unit_id', '');
                                    }
                                }}
                            />
                        )}
                    </Field>

                    <Field
                        label="Unit Kerja"
                        error={errors.unit_id}
                        required={!jabatanTertinggi}
                        hint={jabatanTertinggi ? 'Pimpinan tingkat tertinggi tidak ditempatkan pada unit kerja.' : undefined}
                    >
                        {(props) => (
                            <Select
                                {...props}
                                placeholder={jabatanTertinggi ? 'Tidak ditempatkan pada unit kerja' : 'Pilih unit'}
                                value={data.unit_id}
                                disabled={jabatanTertinggi}
                                invalid={Boolean(errors.unit_id)}
                                options={opsi.unit.map((u) => ({ value: u.id, label: u.nama }))}
                                onChange={(e) => setData('unit_id', e.target.value)}
                            />
                        )}
                    </Field>
                </CardBody>
            </Card>

            {mode === 'buat' && (
                <Card>
                    <CardHeader>
                        <div>
                            <CardTitle>Kata Sandi Awal</CardTitle>
                            <p className="mt-0.5 text-sm text-ink-muted">
                                Sampaikan langsung kepada pemilik akun. Tidak ada tautan
                                surel — aplikasi ini tidak mengirim surel apa pun.
                            </p>
                        </div>
                    </CardHeader>
                    <CardBody className="grid gap-4 sm:grid-cols-2">
                        <Field label="Kata Sandi" error={errors.password} required>
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
                    </CardBody>
                </Card>
            )}

            <div className="flex flex-col gap-2 sm:flex-row">
                <Button
                    type="submit"
                    icon={mode === 'buat' ? UserPlus : Save}
                    loading={processing}
                    className="w-full sm:w-auto"
                >
                    {mode === 'buat'
                        ? processing
                            ? 'Menyimpan…'
                            : 'Tambah Pengguna'
                        : processing
                          ? 'Menyimpan…'
                          : 'Simpan Perubahan'}
                </Button>

                <Link href={batal} className="w-full sm:w-auto">
                    <Button type="button" variant="secondary" className="w-full">
                        Batal
                    </Button>
                </Link>
            </div>
        </form>
    );
}

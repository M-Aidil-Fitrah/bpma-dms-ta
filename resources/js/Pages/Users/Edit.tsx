import { UserForm, type OpsiFormulirPengguna } from '@/Components/domain/UserForm';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface EditProps {
    pengguna: App.Data.UserEditData;
    opsi: OpsiFormulirPengguna;
}

export default function Edit({ pengguna, opsi }: EditProps) {
    return (
        <AppLayout
            title={`Ubah — ${pengguna.name}`}
            header={
                <div className="flex min-w-0 items-center gap-2 text-sm">
                    <Link
                        href="/admin/users"
                        className="flex shrink-0 items-center gap-1.5 font-medium text-ink-muted hover:text-ink"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                        Pengguna
                    </Link>
                    <span className="text-ink-subtle" aria-hidden>
                        /
                    </span>
                    <span className="truncate font-semibold text-ink">{pengguna.name}</span>
                </div>
            }
        >
            <UserForm
                mode="ubah"
                aksi={`/admin/users/${pengguna.id}`}
                batal="/admin/users"
                opsi={opsi}
                awal={{
                    name: pengguna.name,
                    email: pengguna.email,
                    // Select memakai string; angka akan membuat nilai terpilih
                    // tidak pernah cocok dengan `value` opsinya.
                    jabatan_id: pengguna.jabatan_id?.toString() ?? '',
                    unit_id: pengguna.unit_id?.toString() ?? '',
                }}
            />
        </AppLayout>
    );
}

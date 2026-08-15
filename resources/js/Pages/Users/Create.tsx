import { UserForm, type OpsiFormulirPengguna } from '@/Components/domain/UserForm';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface CreateProps {
    opsi: OpsiFormulirPengguna;
}

export default function Create({ opsi }: CreateProps) {
    return (
        <AppLayout
            title="Tambah Pengguna"
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
                    <span className="truncate font-semibold text-ink">Tambah Pengguna</span>
                </div>
            }
        >
            <UserForm
                mode="buat"
                aksi="/admin/users"
                batal="/admin/users"
                opsi={opsi}
                awal={{ name: '', email: '', jabatan_id: '', unit_id: '' }}
            />
        </AppLayout>
    );
}

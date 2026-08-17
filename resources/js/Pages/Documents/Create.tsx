import {
    DocumentForm,
    type OpsiFormulirDokumen,
} from '@/Components/domain/DocumentForm';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface CreateProps {
    opsi: OpsiFormulirDokumen;
}

export default function Create({ opsi }: CreateProps) {
    return (
        <AppLayout
            title="Unggah Dokumen"
            header={
                <div className="flex min-w-0 items-center gap-2 text-sm">
                    <Link
                        href="/documents"
                        className="flex shrink-0 items-center gap-1.5 font-medium text-ink-muted hover:text-ink"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                        Semua Dokumen
                    </Link>
                    <span className="text-ink-subtle" aria-hidden>
                        /
                    </span>
                    <span className="truncate font-semibold text-ink">Unggah Dokumen</span>
                </div>
            }
        >
            <DocumentForm
                mode="buat"
                aksi="/documents"
                batal="/documents"
                opsi={opsi}
                awal={{
                    nomor: '',
                    judul: '',
                    deskripsi: '',
                    category_id: '',
                    origin_unit_id: '',
                    tanggal: new Date().toISOString().slice(0, 10),
                    masa_berlaku: '',
                    edit_scope: 'owner_only',
                }}
                aksesAwal={{
                    is_shared_to_all: false,
                    min_tingkat_akses: null,
                    unit_ids: [],
                    shared_users: [],
                }}
            />
        </AppLayout>
    );
}

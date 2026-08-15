import {
    DocumentForm,
    type OpsiFormulirDokumen,
} from '@/Components/domain/DocumentForm';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface EditProps {
    dokumen: App.Data.DocumentEditData;
    opsi: OpsiFormulirDokumen;
}

export default function Edit({ dokumen, opsi }: EditProps) {
    return (
        <AppLayout
            title={`Ubah — ${dokumen.judul}`}
            header={
                <div className="flex min-w-0 items-center gap-2 text-sm">
                    <Link
                        href={`/documents/${dokumen.id}`}
                        className="flex shrink-0 items-center gap-1.5 font-medium text-ink-muted hover:text-ink"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                        <span className="hidden sm:inline">Detail Dokumen</span>
                        <span className="sm:hidden">Kembali</span>
                    </Link>
                    <span className="text-ink-subtle" aria-hidden>
                        /
                    </span>
                    <span className="truncate font-semibold text-ink">{dokumen.judul}</span>
                </div>
            }
        >
            <DocumentForm
                mode="ubah"
                aksi={`/documents/${dokumen.id}`}
                batal={`/documents/${dokumen.id}`}
                opsi={opsi}
                berkas={{
                    nama: dokumen.nama_berkas,
                    tipe: dokumen.tipe_berkas,
                    ukuran: dokumen.ukuran_berkas,
                }}
                awal={{
                    nomor: dokumen.nomor,
                    judul: dokumen.judul,
                    deskripsi: dokumen.deskripsi ?? '',
                    // Select memakai string; angka akan membuat nilai terpilih
                    // tidak pernah cocok dengan `value` opsinya.
                    category_id: dokumen.category_id?.toString() ?? '',
                    origin_unit_id: dokumen.origin_unit_id?.toString() ?? '',
                    tanggal: dokumen.tanggal,
                    masa_berlaku: dokumen.masa_berlaku ?? '',
                    edit_scope: dokumen.edit_scope,
                }}
                aksesAwal={{
                    is_shared_to_all: dokumen.is_shared_to_all,
                    min_tingkat_akses: dokumen.min_tingkat_akses,
                    unit_ids: dokumen.unit_ids,
                    shared_users: dokumen.orang_tertentu,
                }}
            />
        </AppLayout>
    );
}

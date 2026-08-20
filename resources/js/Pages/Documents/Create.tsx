import {
    DocumentForm,
    type OpsiFormulirDokumen,
} from '@/Components/domain/DocumentForm';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface CreateProps {
    opsi: OpsiFormulirDokumen;
    pengganti: App.Data.DocumentEditData | null;
}

export default function Create({ opsi, pengganti }: CreateProps) {
    return (
        <AppLayout
            title={pengganti ? 'Unggah Versi Baru' : 'Unggah Dokumen'}
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
                    <span className="truncate font-semibold text-ink">
                        {pengganti ? 'Unggah Versi Baru' : 'Unggah Dokumen'}
                    </span>
                </div>
            }
        >
            <DocumentForm
                mode="buat"
                aksi="/documents"
                batal={pengganti ? `/documents/${pengganti.id}/edit` : '/documents'}
                opsi={opsi}
                awal={{
                    nomor: pengganti?.nomor ?? '',
                    judul: pengganti?.judul ?? '',
                    deskripsi: pengganti?.deskripsi ?? '',
                    category_id: String(pengganti?.category_id ?? ''),
                    origin_unit_id: String(pengganti?.origin_unit_id ?? ''),
                    tanggal: pengganti?.tanggal ?? new Date().toISOString().slice(0, 10),
                    masa_berlaku: pengganti?.masa_berlaku ?? '',
                    edit_scope: pengganti?.edit_scope ?? 'owner_only',
                    version_note: '',
                }}
                aksesAwal={{
                    is_shared_to_all: pengganti?.is_shared_to_all ?? false,
                    min_tingkat_akses: pengganti?.min_tingkat_akses ?? null,
                    unit_ids: pengganti?.unit_ids ?? [],
                    shared_users: pengganti?.orang_tertentu ?? [],
                }}
                replacesDocumentId={pengganti?.id ?? null}
                versiTerbaru={pengganti === null ? undefined : {
                    id: pengganti.id,
                    nama: pengganti.nama_berkas,
                    tipe: pengganti.tipe_berkas,
                    ukuran: pengganti.ukuran_berkas,
                    thumbnailTersedia: pengganti.thumbnail_tersedia,
                }}
            />
        </AppLayout>
    );
}

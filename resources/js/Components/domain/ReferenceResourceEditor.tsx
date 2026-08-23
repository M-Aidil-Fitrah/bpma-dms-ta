import { ReferenceResourceForm, type UnitIndukOption } from '@/Components/domain/ReferenceResourceForm';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { type ReferenceResourceKind } from './ReferenceResourceActions';

interface ReferenceResourceEditorProps {
    jenis: ReferenceResourceKind;
    judul: string;
    alamat: string;
    mode: 'buat' | 'ubah';
    referensi?: App.Data.ReferensiEditData;
    induk?: readonly UnitIndukOption[];
}

export function ReferenceResourceEditor({ jenis, judul, alamat, mode, referensi, induk }: ReferenceResourceEditorProps) {
    const { t } = useTranslation('reference');
    const label = jenis === 'unit' ? t('unit.label') : judul;
    const namaHalaman = mode === 'buat' ? t('umum.tambahEntitas', { label }) : t('editor.ubahNama', { nama: referensi?.nama });

    return (
        <AppLayout
            title={namaHalaman}
            header={<div className="flex min-w-0 items-center gap-2 text-sm"><Link href={alamat} className="flex shrink-0 items-center gap-1.5 font-medium text-ink-muted hover:text-ink"><ArrowLeft className="size-4" aria-hidden />{judul}</Link><span className="text-ink-subtle" aria-hidden>/</span><span className="truncate font-semibold text-ink">{mode === 'buat' ? t('umum.tambahEntitas', { label }) : referensi?.nama}</span></div>}
        >
            <ReferenceResourceForm jenis={jenis} mode={mode} aksi={mode === 'buat' ? alamat : `${alamat}/${referensi?.id}`} batal={alamat} awal={referensi} induk={induk} />
        </AppLayout>
    );
}

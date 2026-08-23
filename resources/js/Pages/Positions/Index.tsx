import { ReferenceResourceIndex } from '@/Components/domain/ReferenceResourceIndex';
import { Button } from '@/Components/ui/Button';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props { referensi: Pagination.Paginated<App.Data.ReferensiListData>; filter: { cari: string | null; status: string | null }; }
export default function Index({ referensi, filter }: Props) {
    const { t } = useTranslation(['reference', 'common']);
    const judul = t('reference:jabatan.label');
    const tambahJudul = `${t('common:aksi.tambah')} ${judul}`;
    return (
        <AppLayout title={judul} actions={<Link href="/admin/jabatans/create"><Button icon={Plus}><span className="hidden sm:inline">{tambahJudul}</span><span className="sr-only sm:hidden">{tambahJudul}</span></Button></Link>}>
            <div className="space-y-4">
                <ReferenceResourceIndex jenis="jabatan" judul={judul} singular={t('reference:jabatan.labelKecil')} alamat="/admin/jabatans" referensi={referensi} filter={filter} />
            </div>
        </AppLayout>
    );
}

import { ReferenceResourceIndex } from '@/Components/domain/ReferenceResourceIndex';
import { Button } from '@/Components/ui/Button';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props { referensi: Pagination.Paginated<App.Data.ReferensiListData>; filter: { cari: string | null; status: string | null }; }
export default function Index({ referensi, filter }: Props) {
    const { t } = useTranslation(['reference', 'common']);
    const judulSingkat = t('reference:unit.labelSingkat');
    const tambahJudul = `${t('common:aksi.tambah')} ${judulSingkat}`;
    return (
        <AppLayout title={t('reference:unit.label')} actions={<Link href="/admin/units/create"><Button icon={Plus}><span className="hidden sm:inline">{tambahJudul}</span><span className="sr-only sm:hidden">{tambahJudul}</span></Button></Link>}>
            <div className="space-y-4">
                <ReferenceResourceIndex jenis="unit" judul={judulSingkat} singular={t('reference:unit.labelKecil')} alamat="/admin/units" referensi={referensi} filter={filter} />
            </div>
        </AppLayout>
    );
}

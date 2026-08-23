import { ReferenceResourceEditor } from '@/Components/domain/ReferenceResourceEditor';
import { useTranslation } from 'react-i18next';

export default function Edit({ referensi }: { referensi: App.Data.ReferensiEditData }) {
    const { t } = useTranslation('reference');
    return <ReferenceResourceEditor jenis="kategori" judul={t('kategori.label')} alamat="/admin/categories" mode="ubah" referensi={referensi} />;
}

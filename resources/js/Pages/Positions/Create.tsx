import { ReferenceResourceEditor } from '@/Components/domain/ReferenceResourceEditor';
import { useTranslation } from 'react-i18next';

export default function Create() {
    const { t } = useTranslation('reference');
    return <ReferenceResourceEditor jenis="jabatan" judul={t('jabatan.label')} alamat="/admin/jabatans" mode="buat" />;
}

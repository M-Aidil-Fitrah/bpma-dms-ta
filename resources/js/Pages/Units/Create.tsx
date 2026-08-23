import { ReferenceResourceEditor } from '@/Components/domain/ReferenceResourceEditor';
import { type UnitIndukOption } from '@/Components/domain/ReferenceResourceForm';
import { useTranslation } from 'react-i18next';

export default function Create({ induk }: { induk: UnitIndukOption[] }) {
    const { t } = useTranslation('reference');
    return <ReferenceResourceEditor jenis="unit" judul={t('unit.label')} alamat="/admin/units" mode="buat" induk={induk} />;
}

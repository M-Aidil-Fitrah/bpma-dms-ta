import { ReferenceResourceEditor } from '@/Components/domain/ReferenceResourceEditor';
import { type UnitIndukOption } from '@/Components/domain/ReferenceResourceForm';
export default function Edit({ referensi, induk }: { referensi: App.Data.ReferensiEditData; induk: UnitIndukOption[] }) { return <ReferenceResourceEditor jenis="unit" judul="Unit Kerja" alamat="/admin/units" mode="ubah" referensi={referensi} induk={induk} />; }

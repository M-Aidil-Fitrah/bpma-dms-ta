import { ReferenceResourceEditor } from '@/Components/domain/ReferenceResourceEditor';
import { type UnitIndukOption } from '@/Components/domain/ReferenceResourceForm';
export default function Create({ induk }: { induk: UnitIndukOption[] }) { return <ReferenceResourceEditor jenis="unit" judul="Unit Kerja" alamat="/admin/units" mode="buat" induk={induk} />; }

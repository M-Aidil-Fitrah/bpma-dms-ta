import { ReferenceResourceEditor } from '@/Components/domain/ReferenceResourceEditor';
export default function Edit({ referensi }: { referensi: App.Data.ReferensiEditData }) { return <ReferenceResourceEditor jenis="jabatan" judul="Jabatan" alamat="/admin/jabatans" mode="ubah" referensi={referensi} />; }

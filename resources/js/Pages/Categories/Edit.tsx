import { ReferenceResourceEditor } from '@/Components/domain/ReferenceResourceEditor';
export default function Edit({ referensi }: { referensi: App.Data.ReferensiEditData }) { return <ReferenceResourceEditor jenis="kategori" judul="Kategori" alamat="/admin/categories" mode="ubah" referensi={referensi} />; }

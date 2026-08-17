import { ReferenceResourceIndex } from '@/Components/domain/ReferenceResourceIndex';
import { Button } from '@/Components/ui/Button';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface Props { referensi: Pagination.Paginated<App.Data.ReferensiListData>; filter: { cari: string | null; status: string | null }; }
export default function Index({ referensi, filter }: Props) { return <AppLayout title="Jabatan" actions={<Link href="/admin/jabatans/create"><Button icon={Plus}>Tambah Jabatan</Button></Link>}><div className="space-y-4"><ReferenceResourceIndex jenis="jabatan" judul="Jabatan" singular="jabatan" alamat="/admin/jabatans" referensi={referensi} filter={filter} /></div></AppLayout>; }

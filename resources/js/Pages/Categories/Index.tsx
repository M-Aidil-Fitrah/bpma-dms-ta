import { ReferenceResourceIndex } from '@/Components/domain/ReferenceResourceIndex';
import { Button } from '@/Components/ui/Button';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface Props { referensi: Pagination.Paginated<App.Data.ReferensiListData>; filter: { cari: string | null; status: string | null }; }
export default function Index({ referensi, filter }: Props) { return <AppLayout title="Kategori" actions={<Link href="/admin/categories/create"><Button icon={Plus}>Tambah Kategori</Button></Link>}><div className="space-y-4"><ReferenceResourceIndex jenis="kategori" judul="Kategori" singular="kategori" alamat="/admin/categories" referensi={referensi} filter={filter} /></div></AppLayout>; }

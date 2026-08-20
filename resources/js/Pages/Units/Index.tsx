import { ReferenceResourceIndex } from '@/Components/domain/ReferenceResourceIndex';
import { Button } from '@/Components/ui/Button';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface Props { referensi: Pagination.Paginated<App.Data.ReferensiListData>; filter: { cari: string | null; status: string | null }; }
export default function Index({ referensi, filter }: Props) { return <AppLayout title="Unit Kerja" actions={<Link href="/admin/units/create"><Button icon={Plus}><span className="hidden sm:inline">Tambah Unit</span><span className="sr-only sm:hidden">Tambah Unit</span></Button></Link>}><div className="space-y-4"><ReferenceResourceIndex jenis="unit" judul="Unit" singular="unit kerja" alamat="/admin/units" referensi={referensi} filter={filter} /></div></AppLayout>; }

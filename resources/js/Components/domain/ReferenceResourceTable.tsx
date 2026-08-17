import { ReferenceResourceActions, type ReferenceResourceKind } from '@/Components/domain/ReferenceResourceActions';
import { Badge } from '@/Components/ui/Badge';

interface ReferenceResourceTableProps {
    jenis: ReferenceResourceKind;
    referensi: readonly App.Data.ReferensiListData[];
}

/** Tabel daftar referensi untuk layar lebar; kartu ponsel dipisah agar tetap ringkas. */
export function ReferenceResourceTable({ jenis, referensi }: ReferenceResourceTableProps) {
    return (
        <div className="hidden overflow-x-auto lg:block">
            <table className="w-full table-fixed">
                <thead className="border-b border-line bg-surface-sunken">
                    <tr>
                        <Header className="w-[40%]">Nama</Header>
                        <Header className="w-[34%]">Keterangan</Header>
                        <Header className="w-[14%]">Status</Header>
                        <Header className="w-[12%] text-right">Aksi</Header>
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {referensi.map((item) => (
                        <tr key={item.id} className="transition-colors hover:bg-surface-sunken">
                            <td className="px-4 py-3">
                                <p
                                    className="truncate text-sm font-medium text-ink"
                                    style={jenis === 'unit' ? { paddingLeft: `${item.kedalaman * 18}px` } : undefined}
                                >
                                    {jenis === 'unit' && item.kedalaman > 0 && <span className="mr-1.5 text-ink-subtle">└</span>}
                                    {item.nama}
                                </p>
                            </td>
                            <td className="px-4 py-3 text-sm text-ink-muted">{item.keterangan || '—'}</td>
                            <td className="px-4 py-3">
                                <Badge variant={item.is_active ? 'success' : 'neutral'} size="sm">
                                    {item.is_active ? 'Aktif' : 'Nonaktif'}
                                </Badge>
                            </td>
                            <td className="px-4 py-3">
                                <ReferenceResourceActions
                                    jenis={jenis}
                                    id={item.id}
                                    nama={item.nama}
                                    aktif={item.is_active}
                                    dampak={item.dampak_nonaktif}
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Header({ children, className }: { children: string; className: string }) {
    return <th scope="col" className={`px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-subtle ${className}`}>{children}</th>;
}

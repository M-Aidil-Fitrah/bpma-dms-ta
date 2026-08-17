import { UserActions } from '@/Components/domain/UserActions';
import { Avatar } from '@/Components/ui/Avatar';
import { Badge } from '@/Components/ui/Badge';
import { memo } from 'react';

export interface UserTableProps {
    pengguna: readonly App.Data.UserListData[];
    idSayaSendiri: number;
}

/**
 * Tabel pengguna untuk layar lebar. Versi ponselnya `UserCardList` — sama
 * seperti dokumen, tabel tidak digulir mendatar di layar sempit.
 */
export function UserTable({ pengguna, idSayaSendiri }: UserTableProps) {
    return (
        <div className="hidden overflow-x-auto lg:block">
            <table className="w-full table-fixed">
                <thead className="border-b border-line bg-surface-sunken">
                    <tr>
                        <th scope="col" className="w-[32%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            Nama & Surel
                        </th>
                        <th scope="col" className="w-[22%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            Jabatan
                        </th>
                        <th scope="col" className="w-[22%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            Unit Kerja
                        </th>
                        <th scope="col" className="w-[12%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            Status
                        </th>
                        <th scope="col" className="w-[12%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody className="divide-y divide-line">
                    {pengguna.map((item) => (
                        <UserTableRow key={item.id} pengguna={item} diriSendiri={item.id === idSayaSendiri} />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

const UserTableRow = memo(function UserTableRow({
    pengguna,
    diriSendiri,
}: {
    pengguna: App.Data.UserListData;
    diriSendiri: boolean;
}) {
    return (
        <tr className="transition-colors hover:bg-surface-sunken">
            <td className="px-4 py-3">
                <div className="flex items-center gap-2.5">
                    <Avatar initials={pengguna.inisial} name={pengguna.name} size="sm" />
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-ink">
                            {pengguna.name}
                            {diriSendiri && <span className="ml-1.5 text-xs text-ink-subtle">(Anda)</span>}
                        </p>
                        <p className="truncate text-xs text-ink-subtle">{pengguna.email}</p>
                    </div>
                </div>
            </td>

            <td className="px-4 py-3 text-sm text-ink-muted">{pengguna.jabatan ?? '—'}</td>
            <td className="px-4 py-3 text-sm text-ink-muted">{pengguna.unit ?? '—'}</td>

            <td className="px-4 py-3">
                <Badge variant={pengguna.is_active ? 'success' : 'neutral'} size="sm">
                    {pengguna.is_active ? 'Aktif' : 'Nonaktif'}
                </Badge>
            </td>

            <td className="px-4 py-3">
                <UserActions
                    userId={pengguna.id}
                    nama={pengguna.name}
                    aktif={pengguna.is_active}
                    diriSendiri={diriSendiri}
                />
            </td>
        </tr>
    );
});

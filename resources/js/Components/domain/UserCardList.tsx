import { UserActions } from '@/Components/domain/UserActions';
import { Avatar } from '@/Components/ui/Avatar';
import { Badge } from '@/Components/ui/Badge';
import { memo } from 'react';

export interface UserCardListProps {
    pengguna: readonly App.Data.UserListData[];
    idSayaSendiri: number;
}

/** Bentuk daftar pengguna untuk layar sempit — kartu bertumpuk, sama seperti dokumen. */
export function UserCardList({ pengguna, idSayaSendiri }: UserCardListProps) {
    return (
        <ul className="divide-y divide-line lg:hidden">
            {pengguna.map((item) => (
                <li key={item.id}>
                    <UserCard pengguna={item} diriSendiri={item.id === idSayaSendiri} />
                </li>
            ))}
        </ul>
    );
}

const UserCard = memo(function UserCard({
    pengguna,
    diriSendiri,
}: {
    pengguna: App.Data.UserListData;
    diriSendiri: boolean;
}) {
    return (
        <div className="flex items-start gap-3 px-4 py-3.5">
            <Avatar initials={pengguna.inisial} name={pengguna.name} size="sm" className="mt-0.5" />

            <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-ink">
                            {pengguna.name}
                            {diriSendiri && <span className="ml-1.5 text-xs text-ink-subtle">(Anda)</span>}
                        </p>
                        <p className="truncate text-xs text-ink-subtle">{pengguna.email}</p>
                    </div>

                    <Badge variant={pengguna.is_active ? 'success' : 'neutral'} size="sm">
                        {pengguna.is_active ? 'Aktif' : 'Nonaktif'}
                    </Badge>
                </div>

                <p className="mt-2 text-xs text-ink-muted">
                    {pengguna.jabatan ?? '—'} · {pengguna.unit ?? '—'}
                </p>

                <div className="mt-2.5">
                    <UserActions
                        userId={pengguna.id}
                        nama={pengguna.name}
                        aktif={pengguna.is_active}
                        diriSendiri={diriSendiri}
                    />
                </div>
            </div>
        </div>
    );
});

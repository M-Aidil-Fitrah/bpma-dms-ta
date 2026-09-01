import { EmptyState } from '@/Components/ui/EmptyState';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { Folder, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface SharedFolder {
    id: number;
    name: string;
    owner_name: string;
}

interface Props {
    folders: SharedFolder[];
}

export default function Shared({ folders }: Props) {
    const { t } = useTranslation(['workspace', 'nav']);

    return (
        <AppLayout title={t('nav:item.dibagikanKeSaya')}>
            {folders.length === 0 ? (
                <EmptyState
                    icon={Users}
                    title={t('workspace:shared.kosong.judul')}
                    description={t('workspace:shared.kosong.deskripsi')}
                />
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {folders.map((folder) => (
                        <Link
                            key={folder.id}
                            href={`/folders/${folder.id}`}
                            className="flex min-h-touch min-w-0 flex-col gap-1 rounded-lg border border-line bg-surface p-4 transition-colors hover:border-brand-300 hover:bg-brand-50/30"
                        >
                            <span className="flex items-center gap-2 truncate font-medium text-ink">
                                <Folder className="size-5 shrink-0 text-brand-700" aria-hidden />
                                {folder.name}
                            </span>
                            <span className="truncate pl-7 text-sm text-ink-muted">
                                {t('workspace:shared.pemilik', { nama: folder.owner_name })}
                            </span>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

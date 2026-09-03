import { EmptyState } from '@/Components/ui/EmptyState';
import { WorkspaceFolderCard } from '@/Components/domain/WorkspaceFolderCard';
import type { UnitPilihan } from '@/Components/domain/UnitTreePicker';
import type { PenggunaTerpilih } from '@/Components/domain/UserPicker';
import { AppLayout } from '@/Layouts/AppLayout';
import { Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface SharedFolder {
    id: number;
    name: string;
    owner_name: string;
    access_level: 'editor' | 'viewer';
    sharing_restricted: boolean;
    unit_entries: { id: number; role: 'viewer' | 'editor' }[];
    user_entries: (PenggunaTerpilih & { role: 'viewer' | 'editor' })[];
}

interface Props {
    folders: SharedFolder[];
    unit_options: UnitPilihan[];
}

export default function Shared({ folders, unit_options: unitOptions }: Props) {
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
                        <div key={folder.id} className="space-y-1">
                            <WorkspaceFolderCard folder={folder} accessLevel={folder.access_level} unitOptions={unitOptions} />
                            <p className="truncate px-1 text-sm text-ink-muted">
                                {t('workspace:shared.pemilik', { nama: folder.owner_name })}
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

import { UserForm, type OpsiFormulirPengguna } from '@/Components/domain/UserForm';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface CreateProps {
    opsi: OpsiFormulirPengguna;
}

export default function Create({ opsi }: CreateProps) {
    const { t } = useTranslation(['users', 'common']);

    return (
        <AppLayout
            title={t('users:create.pageTitle')}
            header={
                <div className="flex min-w-0 items-center gap-2 text-sm">
                    <Link
                        href="/admin/users"
                        className="flex shrink-0 items-center gap-1.5 font-medium text-ink-muted hover:text-ink"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                        {t('users:create.breadcrumbUsers')}
                    </Link>
                    <span className="text-ink-subtle" aria-hidden>
                        /
                    </span>
                    <span className="truncate font-semibold text-ink">{t('users:create.pageTitle')}</span>
                </div>
            }
        >
            <UserForm
                mode="buat"
                aksi="/admin/users"
                batal="/admin/users"
                opsi={opsi}
                awal={{ name: '', email: '', jabatan_id: '', unit_id: '' }}
            />
        </AppLayout>
    );
}

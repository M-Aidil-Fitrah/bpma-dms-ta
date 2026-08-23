import { AppLayout } from '@/Layouts/AppLayout';
import { UpdatePasswordForm } from '@/Pages/Profile/Partials/UpdatePasswordForm';
import { UpdateProfileInformationForm } from '@/Pages/Profile/Partials/UpdateProfileInformationForm';
import { useTranslation } from 'react-i18next';

/**
 * Tidak ada aksi hapus akun sendiri di halaman ini.
 *
 * Penonaktifan akun adalah wewenang Superadmin (FR-27), dan akun tidak pernah
 * dihapus permanen supaya riwayat aktivitas serta dokumen yang pernah diunggah
 * tetap utuh.
 */
export default function Edit() {
    const { t } = useTranslation('profile');

    return (
        <AppLayout title={t('halaman.judul')}>
            <div className="max-w-3xl space-y-5">
                <UpdateProfileInformationForm />
                <UpdatePasswordForm />
            </div>
        </AppLayout>
    );
}

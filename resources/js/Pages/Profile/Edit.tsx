import { AppLayout } from '@/Layouts/AppLayout';
import { UpdatePasswordForm } from '@/Pages/Profile/Partials/UpdatePasswordForm';
import { UpdateProfileInformationForm } from '@/Pages/Profile/Partials/UpdateProfileInformationForm';

/**
 * Tidak ada aksi hapus akun sendiri di halaman ini.
 *
 * Penonaktifan akun adalah wewenang Superadmin (FR-27), dan akun tidak pernah
 * dihapus permanen supaya riwayat aktivitas serta dokumen yang pernah diunggah
 * tetap utuh.
 */
export default function Edit() {
    return (
        <AppLayout title="Profil Saya">
            <div className="max-w-3xl space-y-5">
                <UpdateProfileInformationForm />
                <UpdatePasswordForm />
            </div>
        </AppLayout>
    );
}

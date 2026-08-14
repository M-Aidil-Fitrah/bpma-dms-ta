import { EmptyState } from '@/Components/ui/EmptyState';
import { AppLayout } from '@/Layouts/AppLayout';
import { LayoutDashboard } from 'lucide-react';

/**
 * Penanda tempat sampai FEAT-06 mengisinya dengan statistik sungguhan.
 *
 * Sengaja menyatakan apa adanya alih-alih menampilkan angka contoh — angka
 * palsu di dasbor mudah terbawa sampai demo dan disangka data sungguhan.
 */
export default function Dashboard() {
    return (
        <AppLayout title="Beranda">
            <EmptyState
                icon={LayoutDashboard}
                title="Dasbor sedang disiapkan"
                description="Ringkasan jumlah dokumen, masa evaluasi, dan aktivitas terbaru akan tampil di sini setelah modul dasbor selesai dikerjakan."
            />
        </AppLayout>
    );
}

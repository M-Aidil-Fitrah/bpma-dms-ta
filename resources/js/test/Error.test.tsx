import Error from '@/Pages/Error';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
}));

describe('Error', () => {
    it('menampilkan judul dan keterangan sesuai status', () => {
        render(<Error status={403} />);

        expect(screen.getByRole('heading', { name: 'Akses ditolak' })).toBeInTheDocument();
        expect(screen.getByText('403')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Muat ulang' })).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Kembali ke Beranda' })).toBeInTheDocument();
    });

    it('menawarkan muat ulang untuk galat server dan rate limit', () => {
        render(<Error status={429} retryAfter={30} />);

        expect(screen.getByRole('button', { name: 'Muat ulang' })).toBeInTheDocument();
        expect(
            screen.getByText('Anda mengirim terlalu banyak permintaan dalam waktu singkat. Coba lagi dalam 30 detik.'),
        ).toBeInTheDocument();
    });

    it('memakai teks galat server untuk status yang tidak dikenal', () => {
        render(<Error status={418} />);

        expect(screen.getByRole('heading', { name: 'Terjadi kesalahan' })).toBeInTheDocument();
        expect(screen.getByText('418')).toBeInTheDocument();
    });
});

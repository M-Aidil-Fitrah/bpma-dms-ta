import { FolderSharePicker } from '@/Components/domain/FolderSharePicker';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/Components/domain/UnitTreePicker', () => ({
    UnitTreePicker: ({ terpilih }: { terpilih: readonly number[] }) => (
        <div data-testid="pemilih-unit">{terpilih.join(',')}</div>
    ),
}));

vi.mock('@/Components/domain/UserPicker', () => ({
    UserPicker: ({ terpilih }: { terpilih: readonly { id: number }[] }) => (
        <div data-testid="pemilih-pengguna">{terpilih.map((p) => p.id).join(',')}</div>
    ),
}));

describe('FolderSharePicker', () => {
    it('memanggil onSubmit dengan daftar unit dan pengguna saat disimpan', async () => {
        const user = userEvent.setup();
        const onSubmit = vi.fn();

        render(
            <FolderSharePicker
                terbuka
                onTutup={vi.fn()}
                onSubmit={onSubmit}
                unitOptions={[]}
                unitIds={[7]}
                sharedUsers={[{ id: 3, nama: 'Rani', jabatan: null, unit: null }]}
                processing={false}
            />,
        );

        await user.click(screen.getByRole('button', { name: /simpan akses/i }));

        expect(onSubmit).toHaveBeenCalledWith({ unit_ids: [7], shared_user_ids: [3] });
    });

    it('tidak merender apa pun saat tertutup', () => {
        render(
            <FolderSharePicker
                terbuka={false}
                onTutup={vi.fn()}
                onSubmit={vi.fn()}
                unitOptions={[]}
                unitIds={[]}
                sharedUsers={[]}
                processing={false}
            />,
        );

        expect(screen.queryByRole('button', { name: /simpan akses/i })).not.toBeInTheDocument();
    });

    it('menyegarkan pilihan dari props setiap kali dialog dibuka kembali', () => {
        const { rerender } = render(
            <FolderSharePicker
                terbuka={false}
                onTutup={vi.fn()}
                onSubmit={vi.fn()}
                unitOptions={[]}
                unitIds={[1]}
                sharedUsers={[{ id: 10, nama: 'Budi', jabatan: null, unit: null }]}
                processing={false}
            />,
        );

        rerender(
            <FolderSharePicker
                terbuka
                onTutup={vi.fn()}
                onSubmit={vi.fn()}
                unitOptions={[]}
                unitIds={[1]}
                sharedUsers={[{ id: 10, nama: 'Budi', jabatan: null, unit: null }]}
                processing={false}
            />,
        );

        expect(screen.getByTestId('pemilih-unit')).toHaveTextContent('1');
        expect(screen.getByTestId('pemilih-pengguna')).toHaveTextContent('10');

        // Tutup, lalu buka kembali dengan props yang berbeda — pilihan lama
        // (mis. dari sesi edit yang dibatalkan) tidak boleh tersisa.
        rerender(
            <FolderSharePicker
                terbuka={false}
                onTutup={vi.fn()}
                onSubmit={vi.fn()}
                unitOptions={[]}
                unitIds={[2]}
                sharedUsers={[{ id: 20, nama: 'Sari', jabatan: null, unit: null }]}
                processing={false}
            />,
        );

        rerender(
            <FolderSharePicker
                terbuka
                onTutup={vi.fn()}
                onSubmit={vi.fn()}
                unitOptions={[]}
                unitIds={[2]}
                sharedUsers={[{ id: 20, nama: 'Sari', jabatan: null, unit: null }]}
                processing={false}
            />,
        );

        expect(screen.getByTestId('pemilih-unit')).toHaveTextContent('2');
        expect(screen.getByTestId('pemilih-pengguna')).toHaveTextContent('20');
    });
});

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
    it('memanggil onSubmit dengan units dan users saat disimpan', async () => {
        const user = userEvent.setup();
        const onSubmit = vi.fn();

        render(
            <FolderSharePicker
                terbuka
                onTutup={vi.fn()}
                onSubmit={onSubmit}
                unitOptions={[]}
                unitEntries={[{ id: 7, role: 'viewer' }]}
                userEntries={[{ id: 3, nama: 'Rani', jabatan: null, unit: null, role: 'viewer' }]}
                canRestrict={false}
                sharingRestricted={false}
                processing={false}
            />,
        );

        await user.click(screen.getByRole('button', { name: /simpan akses/i }));

        expect(onSubmit).toHaveBeenCalledWith({
            units: [{ id: 7, role: 'viewer' }],
            users: [{ id: 3, role: 'viewer' }],
        });
    });

    it('tidak merender apa pun saat tertutup', () => {
        render(
            <FolderSharePicker
                terbuka={false}
                onTutup={vi.fn()}
                onSubmit={vi.fn()}
                unitOptions={[]}
                unitEntries={[]}
                userEntries={[]}
                canRestrict={false}
                sharingRestricted={false}
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
                unitEntries={[{ id: 1, role: 'viewer' }]}
                userEntries={[{ id: 10, nama: 'Budi', jabatan: null, unit: null, role: 'viewer' }]}
                canRestrict={false}
                sharingRestricted={false}
                processing={false}
            />,
        );

        rerender(
            <FolderSharePicker
                terbuka
                onTutup={vi.fn()}
                onSubmit={vi.fn()}
                unitOptions={[]}
                unitEntries={[{ id: 1, role: 'viewer' }]}
                userEntries={[{ id: 10, nama: 'Budi', jabatan: null, unit: null, role: 'viewer' }]}
                canRestrict={false}
                sharingRestricted={false}
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
                unitEntries={[{ id: 2, role: 'viewer' }]}
                userEntries={[{ id: 20, nama: 'Sari', jabatan: null, unit: null, role: 'viewer' }]}
                canRestrict={false}
                sharingRestricted={false}
                processing={false}
            />,
        );

        rerender(
            <FolderSharePicker
                terbuka
                onTutup={vi.fn()}
                onSubmit={vi.fn()}
                unitOptions={[]}
                unitEntries={[{ id: 2, role: 'viewer' }]}
                userEntries={[{ id: 20, nama: 'Sari', jabatan: null, unit: null, role: 'viewer' }]}
                canRestrict={false}
                sharingRestricted={false}
                processing={false}
            />,
        );

        expect(screen.getByTestId('pemilih-unit')).toHaveTextContent('2');
        expect(screen.getByTestId('pemilih-pengguna')).toHaveTextContent('20');
    });

    it('mengirim role editor per penerima', async () => {
        const user = userEvent.setup();
        const onSubmit = vi.fn();

        render(
            <FolderSharePicker
                terbuka
                onTutup={vi.fn()}
                onSubmit={onSubmit}
                unitOptions={[]}
                unitEntries={[]}
                userEntries={[{ id: 3, nama: 'Rani', jabatan: null, unit: null, role: 'viewer' }]}
                canRestrict={false}
                sharingRestricted={false}
                processing={false}
            />,
        );

        await user.selectOptions(screen.getByRole('combobox', { name: /rani/i }), 'editor');
        await user.click(screen.getByRole('button', { name: /simpan akses/i }));

        expect(onSubmit).toHaveBeenCalledWith(
            expect.objectContaining({ users: [{ id: 3, role: 'editor' }] }),
        );
    });

    it('menampilkan toggle kunci hanya bila canRestrict', () => {
        const { rerender } = render(
            <FolderSharePicker
                terbuka
                onTutup={vi.fn()}
                onSubmit={vi.fn()}
                unitOptions={[]}
                unitEntries={[]}
                userEntries={[]}
                canRestrict={false}
                sharingRestricted={false}
                processing={false}
            />,
        );

        expect(screen.queryByRole('checkbox', { name: /kunci|hanya pemilik/i })).toBeNull();

        rerender(
            <FolderSharePicker
                terbuka
                onTutup={vi.fn()}
                onSubmit={vi.fn()}
                unitOptions={[]}
                unitEntries={[]}
                userEntries={[]}
                canRestrict
                sharingRestricted={false}
                processing={false}
            />,
        );

        expect(screen.getByRole('checkbox', { name: /kunci|hanya pemilik/i })).toBeInTheDocument();
    });

    it('menyertakan sharing_restricted saat canRestrict dan toggle dinyalakan', async () => {
        const user = userEvent.setup();
        const onSubmit = vi.fn();

        render(
            <FolderSharePicker
                terbuka
                onTutup={vi.fn()}
                onSubmit={onSubmit}
                unitOptions={[]}
                unitEntries={[]}
                userEntries={[]}
                canRestrict
                sharingRestricted={false}
                processing={false}
            />,
        );

        await user.click(screen.getByRole('checkbox', { name: /kunci|hanya pemilik/i }));
        await user.click(screen.getByRole('button', { name: /simpan akses/i }));

        expect(onSubmit).toHaveBeenCalledWith(
            expect.objectContaining({ sharing_restricted: true }),
        );
    });
});

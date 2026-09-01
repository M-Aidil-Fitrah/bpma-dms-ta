import { FolderSharePicker } from '@/Components/domain/FolderSharePicker';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/Components/domain/UnitTreePicker', () => ({
    UnitTreePicker: () => <div data-testid="pemilih-unit" />,
}));

vi.mock('@/Components/domain/UserPicker', () => ({
    UserPicker: () => <div data-testid="pemilih-pengguna" />,
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
});

import { AccessMechanismPicker, type NilaiAkses } from '@/Components/domain/AccessMechanismPicker';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/Components/domain/UnitTreePicker', () => ({
    UnitTreePicker: () => <div data-testid="pemilih-unit" />,
}));

vi.mock('@/Components/domain/UserPicker', () => ({
    UserPicker: () => <div data-testid="pemilih-pengguna" />,
}));

const tanpaAkses: NilaiAkses = {
    is_private: false,
    is_shared_to_all: false,
    min_tingkat_akses: null,
    unit_ids: [],
    shared_users: [],
};

describe('AccessMechanismPicker', () => {
    it('memperingatkan saat belum ada mekanisme akses yang dipilih', () => {
        render(<AccessMechanismPicker nilai={tanpaAkses} onChange={vi.fn()} units={[]} jenjang={[]} />);

        expect(screen.getByRole('status')).toBeInTheDocument();
    });

    it('menjadikan akses pribadi eksklusif dan mengosongkan mekanisme lain', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        const nilaiAwal: NilaiAkses = {
            is_private: false,
            is_shared_to_all: true,
            min_tingkat_akses: 2,
            unit_ids: [7],
            shared_users: [{ id: 3, nama: 'Rani', jabatan: null, unit: null }],
        };

        render(<AccessMechanismPicker nilai={nilaiAwal} onChange={onChange} units={[]} jenjang={[]} />);

        await user.click(screen.getByRole('button', { name: /Hanya saya/ }));

        expect(onChange).toHaveBeenLastCalledWith(tanpaAksesDenganPribadi());
    });
});

function tanpaAksesDenganPribadi(): NilaiAkses {
    return { ...tanpaAkses, is_private: true };
}

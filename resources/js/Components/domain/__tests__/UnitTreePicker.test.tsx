import { UnitTreePicker, type UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const units: UnitPilihan[] = [
    { id: 1, nama: 'Direktorat', parent_id: null },
    { id: 2, nama: 'Divisi A', parent_id: 1 },
    { id: 3, nama: 'Divisi B', parent_id: 1 },
];

describe('UnitTreePicker', () => {
    it('mencerminkan cascade induk dan pengecualian divisi pada nilai yang diemisi', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        const { rerender } = render(<UnitTreePicker units={units} terpilih={[]} onChange={onChange} />);

        await user.click(screen.getByRole('button', { name: 'Direktorat' }));
        expect(onChange).toHaveBeenLastCalledWith([1, 2, 3]);

        onChange.mockClear();
        rerender(<UnitTreePicker units={units} terpilih={[1, 2, 3]} onChange={onChange} />);
        await user.click(screen.getByRole('button', { name: 'Buka divisi Direktorat' }));
        await user.click(screen.getByRole('button', { name: 'Divisi A' }));
        expect(onChange).toHaveBeenLastCalledWith([1, 3]);

        onChange.mockClear();
        rerender(<UnitTreePicker units={units} terpilih={[1, 2, 3]} onChange={onChange} />);
        await user.click(screen.getByRole('button', { name: 'Direktorat' }));
        expect(onChange).toHaveBeenLastCalledWith([]);
    });
});

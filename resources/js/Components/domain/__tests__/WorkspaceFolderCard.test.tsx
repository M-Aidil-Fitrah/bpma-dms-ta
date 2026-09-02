import { WorkspaceFolderCard } from '@/Components/domain/WorkspaceFolderCard';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/Components/domain/FolderSharePicker', () => ({
    FolderSharePicker: () => <div data-testid="share-picker" />,
}));

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, ...props }: { children: React.ReactNode }) => <a {...props}>{children}</a>,
    router: { put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
    useForm: () => ({
        data: { name: 'F' },
        setData: vi.fn(),
        patch: vi.fn(),
        processing: false,
        errors: {},
    }),
}));

const folder = { id: 1, name: 'F', unit_entries: [], user_entries: [], sharing_restricted: false };

describe('WorkspaceFolderCard', () => {
    it('owner: Bagikan + Ubah nama + Pindahkan ke Sampah', () => {
        render(<WorkspaceFolderCard folder={folder} accessLevel="owner" unitOptions={[]} />);

        expect(screen.getByRole('button', { name: /bagikan/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /ubah nama/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /sampah/i })).toBeInTheDocument();
    });

    it('editor: Ubah nama ada, Pindahkan ke Sampah tidak', () => {
        render(<WorkspaceFolderCard folder={folder} accessLevel="editor" unitOptions={[]} />);

        expect(screen.getByRole('button', { name: /ubah nama/i })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /sampah/i })).toBeNull();
    });

    it('editor + sharing_restricted: tombol Bagikan hilang', () => {
        render(
            <WorkspaceFolderCard
                folder={{ ...folder, sharing_restricted: true }}
                accessLevel="editor"
                unitOptions={[]}
            />,
        );

        expect(screen.queryByRole('button', { name: /bagikan/i })).toBeNull();
        expect(screen.getByRole('button', { name: /ubah nama/i })).toBeInTheDocument();
    });

    it('editor tanpa restricted: tombol Bagikan ada', () => {
        render(<WorkspaceFolderCard folder={folder} accessLevel="editor" unitOptions={[]} />);

        expect(screen.getByRole('button', { name: /bagikan/i })).toBeInTheDocument();
    });

    it('viewer: tidak ada tombol aksi', () => {
        render(<WorkspaceFolderCard folder={folder} accessLevel="viewer" unitOptions={[]} />);

        expect(screen.queryByRole('button', { name: /bagikan/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /ubah nama/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /sampah/i })).toBeNull();
    });
});

import { IconButton } from '@/Components/ui/IconButton';
import { cn } from '@/lib/cn';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import { Download, Eye, Info, MoreHorizontal, Share2 } from 'lucide-react';

export interface DocumentActionsProps {
    document: App.Data.DocumentListData;
    className?: string;
}

/**
 * Tombol aksi cepat pada tiap baris dan kartu dokumen.
 *
 * Unduhan memakai tautan biasa, bukan navigasi Inertia: yang dikembalikan
 * server adalah aliran berkas, bukan halaman — memaksanya lewat Inertia hanya
 * akan menghasilkan respons yang tidak dapat dirender.
 */
export function DocumentActions({ document, className }: DocumentActionsProps) {
    return (
        <div className={cn('flex items-center justify-end gap-1', className)}>
            <Link href={`/documents/${document.id}`} tabIndex={-1}>
                <IconButton icon={Eye} label="Lihat detail" size="sm" />
            </Link>

            <a href={`/documents/${document.id}/file`} download tabIndex={-1}>
                <IconButton icon={Download} label="Unduh berkas" size="sm" />
            </a>

            <Menu as="div" className="relative">
                <MenuButton
                    aria-label="Aksi lainnya"
                    className="inline-flex size-8 min-h-touch min-w-touch items-center justify-center rounded-lg border border-line bg-white text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700 sm:min-h-0 sm:min-w-0"
                >
                    <MoreHorizontal className="size-4" aria-hidden />
                </MenuButton>

                <MenuItems
                    anchor="bottom end"
                    className="z-50 mt-1 w-52 rounded-card border border-line bg-white p-1 shadow-pop focus:outline-none"
                >
                    <MenuItem>
                        <a
                            href={`/documents/${document.id}/preview`}
                            target="_blank"
                            rel="noreferrer"
                            className="flex min-h-touch items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"
                        >
                            <Eye className="size-4" aria-hidden />
                            Pratinjau di tab baru
                        </a>
                    </MenuItem>

                    <MenuItem>
                        <Link
                            href={`/documents/${document.id}#akses`}
                            className="flex min-h-touch items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"
                        >
                            <Share2 className="size-4" aria-hidden />
                            Lihat pengaturan akses
                        </Link>
                    </MenuItem>

                    <MenuItem>
                        <Link
                            href={`/documents/${document.id}`}
                            className="flex min-h-touch items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"
                        >
                            <Info className="size-4" aria-hidden />
                            Informasi lengkap
                        </Link>
                    </MenuItem>
                </MenuItems>
            </Menu>
        </div>
    );
}

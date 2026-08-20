import { Dropdown, DropdownItem } from '@/Components/ui/Dropdown';
import { IconButton } from '@/Components/ui/IconButton';
import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import { Download, Eye, MoreHorizontal, Share2 } from 'lucide-react';

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

            <Dropdown
                trigger={<IconButton icon={MoreHorizontal} label="Aksi lainnya" size="sm" />}
                panelClassName="w-52"
            >
                    {document.bisa_pratinjau_di_tab_baru && <DropdownItem>
                        <a
                            href={`/documents/${document.id}/preview`}
                            target="_blank"
                            rel="noreferrer"
                            className="flex min-h-touch items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"
                        >
                            <Eye className="size-4" aria-hidden />
                            Pratinjau di tab baru
                        </a>
                    </DropdownItem>}

                    <DropdownItem>
                        <Link
                            href={`/documents/${document.id}#akses`}
                            className="flex min-h-touch items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink sm:min-h-0"
                        >
                            <Share2 className="size-4" aria-hidden />
                            Lihat pengaturan akses
                        </Link>
                    </DropdownItem>
            </Dropdown>
        </div>
    );
}

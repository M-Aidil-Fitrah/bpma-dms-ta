import { Avatar } from '@/Components/ui/Avatar';
import { DocumentStatusBadge } from '@/Components/domain/DocumentStatusBadge';
import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { formatTanggal } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { memo } from 'react';

export interface DocumentRowProps {
    document: App.Data.DocumentListData;
}

/**
 * Satu baris dokumen dalam daftar ringkas — dipakai kartu-kartu di dasbor.
 *
 * Dibungkus `memo` karena muncul berkali-kali dalam satu halaman: tanpa itu,
 * perubahan satu kolom penyaring merender ulang seluruh baris walau isinya
 * tidak berubah sedikit pun.
 */
export const DocumentRow = memo(function DocumentRow({ document }: DocumentRowProps) {
    return (
        <Link
            href={`/documents/${document.id}`}
            className="flex min-h-touch items-center gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-surface-sunken focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
        >
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-ink">{document.judul}</p>
                <p className="mt-0.5 truncate font-mono text-xs text-ink-subtle">
                    {document.nomor}
                </p>
            </div>

            <div className="hidden shrink-0 items-center gap-2 sm:flex">
                <FileTypeBadge mime={document.tipe_berkas} />
                <span className="w-20 text-right font-mono text-xs text-ink-muted">
                    {formatTanggal(document.tanggal)}
                </span>
            </div>

            <DocumentStatusBadge status={document.status} size="sm" />

            <Avatar
                initials={document.inisial_pengunggah}
                name={document.pengunggah ?? undefined}
                size="sm"
                className="hidden md:inline-flex"
            />
        </Link>
    );
});

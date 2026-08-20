import { DocumentActions } from '@/Components/domain/DocumentActions';
import { DocumentStatusBadge } from '@/Components/domain/DocumentStatusBadge';
import { DocumentSearchMatch } from '@/Components/domain/DocumentSearchMatch';
import { DocumentThumbnail } from '@/Components/domain/DocumentThumbnail';
import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { Avatar } from '@/Components/ui/Avatar';
import { formatTanggal, formatUkuranBerkas } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { memo } from 'react';

export interface DocumentGridProps {
    dokumen: readonly App.Data.DocumentListData[];
}

/**
 * Tampilan kartu berjajar, alternatif dari tabel.
 *
 * Berguna ketika pengguna memindai berdasarkan jenis berkas — ikon besar
 * berwarna terbaca jauh lebih cepat daripada satu lencana kecil di dalam baris
 * tabel. Sebaliknya, tabel lebih unggul saat membandingkan tanggal atau
 * pengunggah antar dokumen. Keduanya disediakan karena keduanya punya
 * kegunaannya sendiri.
 */
export function DocumentGrid({ dokumen }: DocumentGridProps) {
    return (
        <ul className="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            {dokumen.map((item) => (
                <li key={item.id}>
                    <DocumentGridCard document={item} />
                </li>
            ))}
        </ul>
    );
}

const DocumentGridCard = memo(function DocumentGridCard({
    document,
}: {
    document: App.Data.DocumentListData;
}) {
    return (
        <article className="flex h-full flex-col rounded-card border border-line bg-surface transition-shadow hover:shadow-pop">
            <DocumentThumbnail
                id={document.id}
                mime={document.tipe_berkas}
                judul={document.judul}
                tersedia={document.thumbnail_tersedia}
            />

            <div className="flex flex-1 flex-col p-4">
                <div className="flex items-start justify-between gap-2">
                    <FileTypeBadge mime={document.tipe_berkas} />
                    <Avatar
                        initials={document.inisial_pengunggah}
                        name={document.pengunggah ?? undefined}
                        size="sm"
                    />
                </div>

                <Link
                    href={`/documents/${document.id}`}
                    className="mt-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
                >
                    <h3 className="line-clamp-2 text-sm font-medium text-ink">
                        {document.judul}
                    </h3>
                </Link>

                <p className="mt-1 truncate font-mono text-xs text-ink-subtle">
                    {document.nomor}
                </p>

                <DocumentSearchMatch
                    kecocokan={document.kecocokan_pencarian}
                    cuplikan={document.cuplikan_pencarian}
                    jumlahFrasa={document.jumlah_frasa_pencarian}
                />

                <dl className="mt-2 space-y-0.5 text-xs text-ink-muted">
                    <div className="truncate">
                        <dt className="sr-only">Pengunggah</dt>
                        <dd className="truncate">{document.pengunggah ?? '—'}</dd>
                    </div>
                    <div className="truncate">
                        <dt className="sr-only">Unit asal</dt>
                        <dd className="truncate text-ink-subtle">
                            {document.unit_asal ?? '—'}
                        </dd>
                    </div>
                </dl>

                <p className="mt-2 font-mono text-xs text-ink-subtle">
                    {formatTanggal(document.tanggal)} · {formatUkuranBerkas(document.ukuran_berkas)}
                </p>

                {document.alasan_terlihat && (
                    <p className="mt-1.5 flex items-center gap-1 text-xs text-ink-subtle">
                        <Eye className="size-3 shrink-0" aria-hidden />
                        <span className="truncate">Terlihat karena: {document.alasan_terlihat}</span>
                    </p>
                )}

                {/* `mt-auto` menahan baris ini tetap di dasar kartu, sehingga
                    seluruh kartu dalam satu baris grid berakhir sejajar walau
                    panjang judulnya berbeda-beda. */}
                <div className="mt-auto flex items-center justify-between gap-2 pt-3">
                    <DocumentStatusBadge status={document.status} size="sm" />
                    <DocumentActions document={document} />
                </div>
            </div>
        </article>
    );
});

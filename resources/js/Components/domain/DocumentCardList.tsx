import { AccessSummary } from '@/Components/domain/AccessSummary';
import { DocumentStatusBadge } from '@/Components/domain/DocumentStatusBadge';
import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { Avatar } from '@/Components/ui/Avatar';
import { formatTanggal, formatUkuranBerkas } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { memo } from 'react';

export interface DocumentCardListProps {
    dokumen: readonly App.Data.DocumentListData[];
}

/**
 * Bentuk daftar dokumen untuk layar sempit.
 *
 * Tiap dokumen menjadi satu kartu bertumpuk, bukan baris tabel yang digulir
 * mendatar. Kolom yang berada di luar layar praktis tidak pernah dilihat orang,
 * jadi informasinya disusun ulang menurun sesuai kepentingannya.
 */
export function DocumentCardList({ dokumen }: DocumentCardListProps) {
    return (
        <ul className="divide-y divide-line lg:hidden">
            {dokumen.map((item) => (
                <li key={item.id}>
                    <DocumentCard document={item} />
                </li>
            ))}
        </ul>
    );
}

const DocumentCard = memo(function DocumentCard({
    document,
}: {
    document: App.Data.DocumentListData;
}) {
    return (
        <Link
            href={`/documents/${document.id}`}
            className="block px-4 py-3.5 transition-colors hover:bg-surface-sunken focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-700"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-ink">{document.judul}</p>
                    <p className="mt-0.5 font-mono text-xs text-ink-subtle">
                        {document.nomor}
                    </p>
                </div>

                <DocumentStatusBadge status={document.status} size="sm" />
            </div>

            <div className="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-ink-muted">
                <FileTypeBadge mime={document.tipe_berkas} />
                <span className="font-mono">{formatTanggal(document.tanggal)}</span>
                <span className="font-mono">{formatUkuranBerkas(document.ukuran_berkas)}</span>
            </div>

            <div className="mt-2.5 flex items-start gap-2">
                <Avatar
                    initials={document.inisial_pengunggah}
                    name={document.pengunggah ?? undefined}
                    size="sm"
                    className="mt-0.5"
                />

                <div className="min-w-0">
                    <p className="truncate text-xs font-medium text-ink">
                        {document.pengunggah ?? '—'}
                    </p>
                    <p className="truncate text-xs text-ink-muted">
                        {document.jabatan_pengunggah ?? '—'}
                    </p>
                    <p className="truncate text-xs text-ink-subtle">
                        {document.unit_asal ?? '—'}
                    </p>
                </div>
            </div>

            <div className="mt-2.5">
                <AccessSummary ringkasan={document.ringkasan_akses} ringkas />
            </div>

            {document.alasan_terlihat && (
                <p className="mt-1.5 flex items-center gap-1 text-xs text-ink-subtle">
                    {/* Beda dari AccessSummary di atas: itu daftar SELURUH
                        mekanisme yang aktif pada dokumen, ini alasan
                        SPESIFIK kenapa pengguna yang sedang login bisa
                        melihatnya (FEAT-12). Dua orang berbeda bisa melihat
                        dokumen yang sama lewat alasan yang berbeda pula. */}
                    <Eye className="size-3 shrink-0" aria-hidden />
                    Terlihat karena: {document.alasan_terlihat}
                </p>
            )}
        </Link>
    );
});

import { AccessSummary } from '@/Components/domain/AccessSummary';
import { DocumentStatusBadge } from '@/Components/domain/DocumentStatusBadge';
import { DocumentSearchMatch } from '@/Components/domain/DocumentSearchMatch';
import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { Avatar } from '@/Components/ui/Avatar';
import { formatTanggal, formatUkuranBerkas } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { memo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

export interface DocumentCardListProps {
    dokumen: readonly App.Data.DocumentListData[];
    /** Menimpa `DocumentActions` baku — dipakai halaman workspace yang butuh aksi berbeda (mis. lepas bintang, pulihkan). */
    aksi?: (document: App.Data.DocumentListData) => ReactNode;
    /** Sampah hanya mendukung pemulihan; detailnya tidak dapat dibuka sebelum dipulihkan. */
    dapatDibuka?: boolean;
}

/**
 * Bentuk daftar dokumen untuk layar sempit.
 *
 * Tiap dokumen menjadi satu kartu bertumpuk, bukan baris tabel yang digulir
 * mendatar. Kolom yang berada di luar layar praktis tidak pernah dilihat orang,
 * jadi informasinya disusun ulang menurun sesuai kepentingannya.
 */
export function DocumentCardList({ dokumen, aksi, dapatDibuka = true }: DocumentCardListProps) {
    return (
        <ul className="divide-y divide-line lg:hidden">
            {dokumen.map((item) => (
                <li key={item.id}>
                    <DocumentCard document={item} aksi={aksi} dapatDibuka={dapatDibuka} />
                </li>
            ))}
        </ul>
    );
}

const DocumentCard = memo(function DocumentCard({
    document,
    aksi,
    dapatDibuka,
}: {
    document: App.Data.DocumentListData;
    aksi?: (document: App.Data.DocumentListData) => ReactNode;
    dapatDibuka: boolean;
}) {
    const { t } = useTranslation('documentBrowse');

    // Tanpa `aksi`: seluruh kartu tetap satu `<Link>`, persis seperti semula
    // (Jelajahi Dokumen tidak butuh aksi cepat di kartu ponsel). Dengan
    // `aksi`: kartu dibungkus `<div>` dan tautannya "diregangkan" penuh lewat
    // `absolute inset-0`, sehingga tombol aksi dapat berdiri di atasnya
    // (`relative z-10`) tanpa menaruh elemen interaktif di dalam `<a>` —
    // menaruh tombol di dalam tautan membuat keduanya berebut klik yang sama.
    const isi = (
        <>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-ink">{document.judul}</p>
                    <p className="mt-0.5 font-mono text-xs text-ink-subtle">
                        {document.nomor}
                    </p>
                </div>

                <DocumentStatusBadge status={document.status} size="sm" />
            </div>

            <DocumentSearchMatch
                kecocokan={document.kecocokan_pencarian}
                cuplikan={document.cuplikan_pencarian}
                jumlahFrasa={document.jumlah_frasa_pencarian}
                masaBerlaku={document.masa_berlaku}
            />

            <div className="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-ink-muted">
                <FileTypeBadge mime={document.tipe_berkas} namaBerkas={document.nama_berkas} />
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
                    {t('documentBrowse:shared.terlihatKarena', { alasan: document.alasan_terlihat })}
                </p>
            )}
        </>
    );

    if (!aksi && dapatDibuka) {
        return (
            <Link
                href={`/documents/${document.id}`}
                className="block px-4 py-3.5 transition-colors hover:bg-surface-sunken focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-700"
            >
                {isi}
            </Link>
        );
    }

    if (!aksi) return <div className="px-4 py-3.5">{isi}</div>;

    return (
        <div className="relative px-4 py-3.5 transition-colors hover:bg-surface-sunken">
            {dapatDibuka && <Link
                href={`/documents/${document.id}`}
                className="absolute inset-0 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-700"
                aria-label={document.judul}
            />}
            {isi}
            <div className="relative z-10 mt-2.5 flex justify-end">{aksi(document)}</div>
        </div>
    );
});

import { SortableHeader } from '@/Components/data/SortableHeader';
import { DocumentActions } from '@/Components/domain/DocumentActions';
import { DocumentStatusBadge } from '@/Components/domain/DocumentStatusBadge';
import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { Avatar } from '@/Components/ui/Avatar';
import { formatTanggal, formatUkuranBerkas } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { memo } from 'react';

export interface DocumentTableProps {
    dokumen: readonly App.Data.DocumentListData[];
    kunciUrut: string;
    arahUrut: 'asc' | 'desc';
    onSort: (kunci: string, arah: 'asc' | 'desc') => void;
}

/**
 * Tabel dokumen untuk layar lebar.
 *
 * Versi ponselnya adalah `DocumentCardList`, bukan tabel yang digulir
 * mendatar — menggulir tabel ke samping di layar sempit hampir selalu berakhir
 * dengan pengguna tidak menemukan kolom yang dicarinya.
 */
export function DocumentTable({
    dokumen,
    kunciUrut,
    arahUrut,
    onSort,
}: DocumentTableProps) {
    return (
        <div className="hidden overflow-x-auto lg:block">
            {/* `table-fixed` menahan kolom agar tidak melebar mengikuti isi
                terpanjang — tanpa itu, satu nama unit yang panjang mendorong
                kolom status keluar layar. */}
            <table className="w-full table-fixed">
                <thead className="border-b border-line bg-surface-sunken">
                    <tr>
                        <SortableHeader
                            label="Nama Dokumen"
                            kunci="judul"
                            kunciAktif={kunciUrut}
                            arah={arahUrut}
                            onSort={onSort}
                            className="w-[31%]"
                        />
                        <th scope="col" className="w-[11%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            Tipe
                        </th>
                        <SortableHeader
                            label="Tanggal"
                            kunci="tanggal"
                            kunciAktif={kunciUrut}
                            arah={arahUrut}
                            onSort={onSort}
                            className="w-[11%]"
                        />
                        <th scope="col" className="w-[24%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            Pengunggah & Unit Asal
                        </th>
                        <th scope="col" className="w-[11%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            Status
                        </th>
                        <th scope="col" className="w-[12%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody className="divide-y divide-line">
                    {dokumen.map((item) => (
                        <DocumentTableRow key={item.id} document={item} />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * Dibungkus `memo` karena muncul dua puluh kali per halaman: tanpa itu,
 * mengetik satu huruf di kolom pencarian merender ulang seluruh baris.
 */
const DocumentTableRow = memo(function DocumentTableRow({
    document,
}: {
    document: App.Data.DocumentListData;
}) {
    return (
        <tr className="transition-colors hover:bg-surface-sunken">
            <td className="px-4 py-3">
                <Link
                    href={`/documents/${document.id}`}
                    className="block focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
                >
                    <p className="truncate text-sm font-medium text-ink">{document.judul}</p>
                    <p className="mt-0.5 truncate font-mono text-xs text-ink-subtle">
                        {document.nomor}
                    </p>
                </Link>
            </td>

            <td className="px-4 py-3">
                {/* Status ekstraksi sengaja tidak ditampilkan di daftar.
                    Informasinya baru berguna saat seseorang benar-benar hendak
                    membuka dokumennya, dan menampilkan dua lencana per baris
                    membuat kolom ini ramai tanpa menambah kejelasan. Tersedia
                    lengkap di halaman detail. */}
                <FileTypeBadge mime={document.tipe_berkas} />
                <p className="mt-1.5 font-mono text-xs text-ink-subtle">
                    {formatUkuranBerkas(document.ukuran_berkas)}
                </p>
            </td>

            <td className="whitespace-nowrap px-4 py-3 font-mono text-sm text-ink-muted">
                {formatTanggal(document.tanggal)}
            </td>

            <td className="px-4 py-3">
                {/* Tiga baris: siapa yang mengunggah, jabatannya, lalu unit asal
                    dokumen. Nama saja tidak cukup — pada organisasi bertingkat,
                    jabatan dan unit itulah yang menjelaskan kenapa dokumen ini
                    diterbitkan dari sana. */}
                <div className="flex items-start gap-2">
                    <Avatar
                        initials={document.inisial_pengunggah}
                        name={document.pengunggah ?? undefined}
                        size="sm"
                        className="mt-0.5"
                    />

                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-ink">
                            {document.pengunggah ?? '—'}
                        </p>
                        <p
                            className="truncate text-xs text-ink-muted"
                            title={document.jabatan_pengunggah ?? undefined}
                        >
                            {document.jabatan_pengunggah ?? '—'}
                        </p>
                        <p
                            className="truncate text-xs text-ink-subtle"
                            title={document.unit_asal ?? undefined}
                        >
                            {document.unit_asal ?? '—'}
                        </p>
                    </div>
                </div>
            </td>

            <td className="px-4 py-3">
                <DocumentStatusBadge status={document.status} size="sm" />
            </td>

            {/* Ringkasan mekanisme akses tidak lagi ditampilkan di daftar:
                isinya panjang, jarang menjadi alasan orang memindai tabel, dan
                tersaji utuh di halaman detail. Ruangnya dipakai tombol aksi
                yang jauh lebih sering dibutuhkan. */}
            <td className="px-4 py-3">
                <DocumentActions document={document} />
            </td>
        </tr>
    );
});

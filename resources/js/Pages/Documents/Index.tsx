import { FilterBar, type FilterChip, type FilterDefinition } from '@/Components/data/FilterBar';
import { Pagination } from '@/Components/data/Pagination';
import { SearchInput } from '@/Components/data/SearchInput';
import { ViewToggle } from '@/Components/data/ViewToggle';
import { DocumentCardList } from '@/Components/domain/DocumentCardList';
import { DocumentGrid } from '@/Components/domain/DocumentGrid';
import { DocumentTable } from '@/Components/domain/DocumentTable';
import { Button } from '@/Components/ui/Button';
import { Card, CardFooter } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { useDocumentFilters, type FilterDokumen } from '@/hooks/useDocumentFilters';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { FileText, SearchX, Upload } from 'lucide-react';
import { useMemo } from 'react';

interface OpsiFilter {
    kategori: { id: number; nama: string }[];
    unit: { id: number; nama: string }[];
    unit_pohon: { id: number; nama: string; parent_id: number | null }[];
    pengunggah: { id: number; name: string }[];
}

interface DocumentsIndexProps {
    dokumen: Pagination.Paginated<App.Data.DocumentListData>;
    filter: FilterDokumen;
    opsi?: OpsiFilter;
}

const STATUS_OPTIONS = [
    { value: 'berlaku', label: 'Berlaku' },
    { value: 'kadaluarsa', label: 'Kadaluarsa' },
] as const;
const EKSTRAKSI_OPTIONS = [
    { value: 'completed', label: 'Dapat dicari' },
    { value: 'review_required', label: 'Perlu ditinjau' },
    { value: 'pending', label: 'Memproses' },
    { value: 'failed', label: 'Gagal' },
    { value: 'not_applicable', label: 'Lampiran biasa' },
] as const;
const TIPE_OPTIONS = [
    { value: 'pdf', label: 'PDF' }, { value: 'gambar', label: 'Gambar' },
    { value: 'word', label: 'Word' }, { value: 'teks', label: 'Teks' }, { value: 'lainnya', label: 'Lainnya' },
] as const;

export default function Index({ dokumen, filter, opsi }: DocumentsIndexProps) {
    const { ubah, urutkan, ubahTampilan, bersihkan } = useDocumentFilters(filter);

    const definisi = useMemo<FilterDefinition[]>(
        () => [
            {
                kunci: 'kategori',
                label: 'Kategori',
                tipe: 'select',
                placeholder: 'Semua kategori',
                options: (opsi?.kategori ?? []).map((k) => ({ value: k.id, label: k.nama })),
            },
            { kunci: 'pengunggah', label: 'Pengunggah', tipe: 'select', placeholder: 'Semua pengunggah', options: (opsi?.pengunggah ?? []).map((p) => ({ value: p.id, label: p.name })) },
            {
                kunci: 'unit',
                label: 'Unit Asal',
                tipe: 'tree',
                treeUnits: opsi?.unit_pohon ?? [],
            },
            { kunci: 'tipe', label: 'Tipe Berkas', tipe: 'select', placeholder: 'Semua tipe', options: TIPE_OPTIONS },
            { kunci: 'status_ekstraksi', label: 'Pencarian Isi', tipe: 'select', placeholder: 'Semua status', options: EKSTRAKSI_OPTIONS },
            {
                kunci: 'status',
                label: 'Status',
                tipe: 'select',
                placeholder: 'Semua status',
                options: STATUS_OPTIONS,
            },
            { kunci: 'dari', label: 'Tanggal Mulai', tipe: 'date' },
            { kunci: 'sampai', label: 'Tanggal Akhir', tipe: 'date' },
        ],
        [opsi],
    );

    const chips = useMemo<FilterChip[]>(
        () => susunChip(filter, opsi),
        [filter, opsi],
    );

    const nilaiFilter = useMemo<Record<string, string>>(
        () => ({
            kategori: filter.kategori?.toString() ?? '',
            unit: filter.unit?.toString() ?? '',
            status: filter.status ?? '',
            pengunggah: filter.pengunggah?.toString() ?? '',
            tipe: filter.tipe ?? '',
            status_ekstraksi: filter.status_ekstraksi ?? '',
            dari: filter.dari ?? '',
            sampai: filter.sampai ?? '',
        }),
        [filter],
    );

    const adaPenyaring = chips.length > 0;

    return (
        <AppLayout
            title="Semua Dokumen"
            actions={
                <Link href="/documents/create">
                    <Button icon={Upload}>
                        {/* Di ponsel hanya ikonnya yang tersisa; label penuh
                            memakan hampir separuh lebar bilah atas. */}
                        <span className="hidden sm:inline">Unggah Dokumen</span>
                        <span className="sr-only sm:hidden">Unggah Dokumen</span>
                    </Button>
                </Link>
            }
        >
            <div className="space-y-4">
                <FilterBar
                    definisi={definisi}
                    nilai={nilaiFilter}
                    onChange={ubah}
                    onReset={bersihkan}
                    chips={chips}
                    onHapusChip={(kunci) => ubah(kunci, '')}
                >
                    <SearchInput
                        value={filter.cari ?? ''}
                        onChange={(nilai) => ubah('cari', nilai)}
                        placeholder="Cari nomor, judul, deskripsi, atau isi dokumen…"
                        className="flex-1"
                    />

                    <ViewToggle nilai={filter.tampilan} onChange={ubahTampilan} />
                </FilterBar>

                <Card>
                    {dokumen.data.length === 0 ? (
                        <KeadaanKosong adaPenyaring={adaPenyaring} onReset={bersihkan} />
                    ) : (
                        <>
                            {filter.tampilan === 'grid' ? (
                                <DocumentGrid dokumen={dokumen.data} />
                            ) : (
                                <>
                                    <DocumentTable
                                        dokumen={dokumen.data}
                                        kunciUrut={filter.urut}
                                        arahUrut={filter.arah}
                                        onSort={urutkan}
                                    />
                                    {/* Di layar sempit tabel selalu berubah
                                        menjadi kartu bertumpuk, apa pun mode
                                        yang dipilih — tabel enam kolom tidak
                                        akan pernah terbaca di lebar 360px. */}
                                    <DocumentCardList dokumen={dokumen.data} />
                                </>
                            )}
                        </>
                    )}

                    {dokumen.total > 0 && (
                        <CardFooter>
                            <Pagination meta={dokumen} labelItem="dokumen" />
                        </CardFooter>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}

/**
 * Dua keadaan kosong yang berbeda, dan perbedaannya penting.
 *
 * "Belum ada dokumen sama sekali" dan "penyaring tidak menemukan apa pun"
 * menuntut tindakan yang berbeda dari pengguna. Menyamakan keduanya membuat
 * orang mengira sistemnya rusak padahal ia hanya lupa mematikan satu penyaring.
 */
function KeadaanKosong({
    adaPenyaring,
    onReset,
}: {
    adaPenyaring: boolean;
    onReset: () => void;
}) {
    if (adaPenyaring) {
        return (
            <EmptyState
                icon={SearchX}
                title="Tidak ada dokumen yang cocok"
                description="Tidak ada dokumen yang sesuai dengan penyaring yang sedang aktif. Coba longgarkan atau bersihkan penyaringnya."
                action={
                    <button
                        type="button"
                        onClick={onReset}
                        className="text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        Bersihkan semua filter
                    </button>
                }
            />
        );
    }

    return (
        <EmptyState
            icon={FileText}
            title="Belum ada dokumen"
            description="Belum ada dokumen yang dapat Anda akses. Dokumen akan tampil di sini setelah diunggah dan dibagikan kepada Anda."
            action={
                <Link href="/documents/create">
                    <Button icon={Upload}>Unggah Dokumen Pertama</Button>
                </Link>
            }
        />
    );
}

function susunChip(filter: FilterDokumen, opsi?: OpsiFilter): FilterChip[] {
    const chips: FilterChip[] = [];

    if (filter.cari) {
        chips.push({ kunci: 'cari', label: `Kata kunci: ${filter.cari}` });
    }

    if (filter.kategori) {
        const nama = opsi?.kategori.find((k) => k.id === filter.kategori)?.nama;
        chips.push({ kunci: 'kategori', label: `Kategori: ${nama ?? filter.kategori}` });
    }

    if (filter.unit) {
        const nama = opsi?.unit.find((u) => u.id === filter.unit)?.nama;
        chips.push({ kunci: 'unit', label: `Unit: ${nama ?? filter.unit}` });
    }

    if (filter.status) {
        const label = STATUS_OPTIONS.find((s) => s.value === filter.status)?.label;
        chips.push({ kunci: 'status', label: `Status: ${label ?? filter.status}` });
    }

    if (filter.dari) chips.push({ kunci: 'dari', label: `Sejak ${filter.dari}` });
    if (filter.sampai) chips.push({ kunci: 'sampai', label: `Hingga ${filter.sampai}` });

    return chips;
}

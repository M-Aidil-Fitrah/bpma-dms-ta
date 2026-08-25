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
import type { TFunction } from 'i18next';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

interface OpsiFilter {
    kategori: { id: number; nama: string }[];
    unit: { id: number; nama: string }[];
    unit_pohon: { id: number; nama: string; parent_id: number | null }[];
    pengunggah: { id: number; name: string }[];
    evaluasi_pilihan: number[];
}

interface DocumentsIndexProps {
    dokumen: Pagination.Paginated<App.Data.DocumentListData>;
    filter: FilterDokumen;
    opsi?: OpsiFilter;
}

function buatOpsiStatus(t: TFunction) {
    return [
        { value: 'berlaku', label: t('common:status.berlaku') },
        { value: 'kadaluarsa', label: t('common:status.kedaluwarsa') },
    ] as const;
}

function buatOpsiTipe(t: TFunction) {
    return [
        { value: 'pdf', label: t('documentBrowse:index.filter.tipe.options.pdf') },
        { value: 'gambar', label: t('documentBrowse:index.filter.tipe.options.gambar') },
        { value: 'word', label: t('documentBrowse:index.filter.tipe.options.word') },
        { value: 'excel', label: t('documentBrowse:index.filter.tipe.options.excel') },
        { value: 'ppt', label: t('documentBrowse:index.filter.tipe.options.ppt') },
        { value: 'teks', label: t('documentBrowse:index.filter.tipe.options.teks') },
        { value: 'lainnya', label: t('documentBrowse:index.filter.tipe.options.lainnya') },
    ] as const;
}

export default function Index({ dokumen, filter, opsi }: DocumentsIndexProps) {
    const { t } = useTranslation(['documentBrowse', 'common']);
    const { ubah, urutkan, ubahTampilan, bersihkan } = useDocumentFilters(filter);

    const statusOptions = useMemo(() => buatOpsiStatus(t), [t]);
    const tipeOptions = useMemo(() => buatOpsiTipe(t), [t]);
    const evaluasiOptions = useMemo(
        () => (opsi?.evaluasi_pilihan ?? []).map((hari) => ({ value: hari, label: t('documentBrowse:index.filter.evaluasi.hari', { hari }) })),
        [opsi, t],
    );

    const definisi = useMemo<FilterDefinition[]>(
        () => [
            {
                kunci: 'kategori',
                label: t('documentBrowse:index.filter.kategori.label'),
                tipe: 'select',
                placeholder: t('documentBrowse:index.filter.kategori.placeholder'),
                options: (opsi?.kategori ?? []).map((k) => ({ value: k.id, label: k.nama })),
            },
            {
                kunci: 'pengunggah',
                label: t('documentBrowse:index.filter.pengunggah.label'),
                tipe: 'select',
                placeholder: t('documentBrowse:index.filter.pengunggah.placeholder'),
                options: (opsi?.pengunggah ?? []).map((p) => ({ value: p.id, label: p.name })),
            },
            {
                kunci: 'unit',
                label: t('documentBrowse:index.filter.unit.label'),
                tipe: 'tree',
                treeUnits: opsi?.unit_pohon ?? [],
            },
            {
                kunci: 'tipe',
                label: t('documentBrowse:index.filter.tipe.label'),
                tipe: 'select',
                placeholder: t('documentBrowse:index.filter.tipe.placeholder'),
                options: tipeOptions,
            },
            {
                kunci: 'status',
                label: t('documentBrowse:index.filter.status.label'),
                tipe: 'select',
                placeholder: t('documentBrowse:index.filter.status.placeholder'),
                options: statusOptions,
            },
            { kunci: 'dari', label: t('documentBrowse:index.filter.dari.label'), tipe: 'date' },
            { kunci: 'sampai', label: t('documentBrowse:index.filter.sampai.label'), tipe: 'date' },
            {
                kunci: 'evaluasi',
                label: t('documentBrowse:index.filter.evaluasi.label'),
                tipe: 'segmented',
                segmentedOptions: evaluasiOptions,
            },
        ],
        [opsi, t, tipeOptions, evaluasiOptions, statusOptions],
    );

    const chips = useMemo<FilterChip[]>(
        () => susunChip(filter, opsi, t, statusOptions, tipeOptions, evaluasiOptions),
        [filter, opsi, t, statusOptions, tipeOptions, evaluasiOptions],
    );

    const nilaiFilter = useMemo<Record<string, string>>(
        () => ({
            kategori: filter.kategori?.toString() ?? '',
            unit: filter.unit?.toString() ?? '',
            status: filter.status ?? '',
            pengunggah: filter.pengunggah?.toString() ?? '',
            tipe: filter.tipe ?? '',
            evaluasi: filter.evaluasi?.toString() ?? '',
            dari: filter.dari ?? '',
            sampai: filter.sampai ?? '',
        }),
        [filter],
    );

    const adaPenyaring = chips.length > 0;

    return (
        <AppLayout
            title={t('documentBrowse:index.title')}
            actions={
                <Link href="/documents/create">
                    <Button icon={Upload}>
                        {/* Di ponsel hanya ikonnya yang tersisa; label penuh
                            memakan hampir separuh lebar bilah atas. */}
                        <span className="hidden sm:inline">{t('documentBrowse:index.uploadButton')}</span>
                        <span className="sr-only sm:hidden">{t('documentBrowse:index.uploadButton')}</span>
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
                        placeholder={t('documentBrowse:index.searchPlaceholder')}
                        className="flex-1"
                    />

                    <ViewToggle nilai={filter.tampilan} onChange={ubahTampilan} />
                </FilterBar>

                <Card>
                    {dokumen.data.length === 0 ? (
                        <KeadaanKosong adaPenyaring={adaPenyaring} onReset={bersihkan} t={t} />
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
                            <Pagination meta={dokumen} labelItem={t('documentBrowse:index.labelItemDokumen')} />
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
    t,
}: {
    adaPenyaring: boolean;
    onReset: () => void;
    t: TFunction;
}) {
    if (adaPenyaring) {
        return (
            <EmptyState
                icon={SearchX}
                title={t('documentBrowse:index.kosong.tanpaHasil.judul')}
                description={t('documentBrowse:index.kosong.tanpaHasil.deskripsi')}
                action={
                    <button
                        type="button"
                        onClick={onReset}
                        className="text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        {t('documentBrowse:index.kosong.tanpaHasil.aksi')}
                    </button>
                }
            />
        );
    }

    return (
        <EmptyState
            icon={FileText}
            title={t('documentBrowse:index.kosong.belumAdaDokumen.judul')}
            description={t('documentBrowse:index.kosong.belumAdaDokumen.deskripsi')}
            action={
                <Link href="/documents/create">
                    <Button icon={Upload}>{t('documentBrowse:index.kosong.belumAdaDokumen.aksi')}</Button>
                </Link>
            }
        />
    );
}

function susunChip(
    filter: FilterDokumen,
    opsi: OpsiFilter | undefined,
    t: TFunction,
    statusOptions: ReturnType<typeof buatOpsiStatus>,
    tipeOptions: ReturnType<typeof buatOpsiTipe>,
    evaluasiOptions: { value: number; label: string }[],
): FilterChip[] {
    const chips: FilterChip[] = [];

    if (filter.cari) {
        chips.push({ kunci: 'cari', label: t('documentBrowse:index.chip.kataKunci', { nilai: filter.cari }) });
    }

    if (filter.kategori) {
        const nama = opsi?.kategori.find((k) => k.id === filter.kategori)?.nama;
        chips.push({ kunci: 'kategori', label: t('documentBrowse:index.chip.kategori', { nilai: nama ?? filter.kategori }) });
    }

    if (filter.unit) {
        const nama = opsi?.unit.find((u) => u.id === filter.unit)?.nama;
        chips.push({ kunci: 'unit', label: t('documentBrowse:index.chip.unit', { nilai: nama ?? filter.unit }) });
    }

    if (filter.status) {
        const label = statusOptions.find((s) => s.value === filter.status)?.label;
        chips.push({ kunci: 'status', label: t('documentBrowse:index.chip.status', { nilai: label ?? filter.status }) });
    }

    if (filter.pengunggah) {
        const nama = opsi?.pengunggah.find((pengunggah) => pengunggah.id === filter.pengunggah)?.name;
        chips.push({ kunci: 'pengunggah', label: t('documentBrowse:index.chip.pengunggah', { nilai: nama ?? filter.pengunggah }) });
    }

    if (filter.tipe) {
        const label = tipeOptions.find((tipe) => tipe.value === filter.tipe)?.label;
        chips.push({ kunci: 'tipe', label: t('documentBrowse:index.chip.tipe', { nilai: label ?? filter.tipe }) });
    }

    if (filter.evaluasi) {
        const label = evaluasiOptions.find((opsi) => opsi.value === filter.evaluasi)?.label;
        chips.push({ kunci: 'evaluasi', label: t('documentBrowse:index.chip.evaluasi', { nilai: label ?? filter.evaluasi }) });
    }

    if (filter.dari) chips.push({ kunci: 'dari', label: t('documentBrowse:index.chip.sejak', { nilai: filter.dari }) });
    if (filter.sampai) chips.push({ kunci: 'sampai', label: t('documentBrowse:index.chip.hingga', { nilai: filter.sampai }) });

    return chips;
}

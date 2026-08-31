import {
    AccessMechanismPicker,
    type JenjangJabatan,
    type NilaiAkses,
} from '@/Components/domain/AccessMechanismPicker';
import { FileDropzone } from '@/Components/domain/FileDropzone';
import { DocumentThumbnail } from '@/Components/domain/DocumentThumbnail';
import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import type { UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { UploadProgress } from '@/Components/domain/UploadProgress';
import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Textarea } from '@/Components/ui/Textarea';
import { formatUkuranBerkas } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { Lock, Save, Upload } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';

export interface OpsiFormulirDokumen {
    kategori: { id: number; nama: string }[];
    unit: { id: number; nama: string }[];
    unit_pohon: UnitPilihan[];
    jenjang_jabatan: JenjangJabatan[];
    batas_unggah_kb: number | null;
    batas_unggah_label: string;
    batas_dijanjikan_label: string;
    lingkungan_kurang: boolean;
    unit_akun_id: number | null;
    unit_akun_nama: string | null;
    unit_kerja_wajib: boolean;
}

export interface NilaiAwalDokumen {
    nomor: string;
    judul: string;
    deskripsi: string;
    category_id: string;
    origin_unit_id: string;
    tanggal: string;
    masa_berlaku: string;
    edit_scope: string;
    version_note: string;
}

export interface DocumentFormProps {
    opsi: OpsiFormulirDokumen;
    awal: NilaiAwalDokumen;
    aksesAwal: NilaiAkses;
    /** Alamat tujuan pengiriman formulir. */
    aksi: string;
    /** Alamat tombol batal. */
    batal: string;
    /**
     * `buat` mengirim berkas lewat POST; `ubah` tidak pernah mengirim berkas
     * sama sekali (FR-42).
     */
    mode: 'buat' | 'ubah';
    /** Keterangan berkas yang sudah tersimpan — hanya pada mode `ubah`. */
    berkas?: RingkasanBerkas;
    /** Versi terbaru tetap tampak saat pengguna memilih berkas pengganti. */
    versiTerbaru?: RingkasanBerkas;
    /** Jalur membuat pengganti berkas saat menyunting metadata versi aktif. */
    unggahVersiBaru?: string;
    replacesDocumentId?: number | null;
}

/**
 * Formulir dokumen yang dipakai bersama oleh halaman unggah dan halaman ubah.
 *
 * Keduanya memakai medan, aturan, dan pengatur akses yang sama persis. Menyalin
 * formulir ini ke dua halaman berarti setiap penyesuaian kecil — satu medan
 * baru, satu pesan galat yang diperbaiki — harus diingat untuk dikerjakan dua
 * kali, dan yang terlupa menghasilkan dua halaman yang berperilaku berbeda
 * untuk hal yang sama.
 *
 * Satu-satunya perbedaan nyata di antara keduanya adalah berkas: wajib saat
 * mengunggah, dan tidak dapat diganti saat menyunting.
 */
export function DocumentForm({
    opsi,
    awal,
    aksesAwal,
    aksi,
    batal,
    mode,
    berkas,
    versiTerbaru,
    unggahVersiBaru,
    replacesDocumentId = null,
}: DocumentFormProps) {
    const { t } = useTranslation(['documentForm', 'common']);
    const [akses, setAkses] = useState<NilaiAkses>(aksesAwal);

    const { data, setData, post, patch, processing, progress, errors, transform } =
        useForm<NilaiAwalDokumen & { file: File | null; replaces_document_id: number | null }>({
            ...awal,
            file: null,
            replaces_document_id: replacesDocumentId,
        });
    const unitKerjaDitentukan = opsi.unit_akun_id !== null || !opsi.unit_kerja_wajib;
    const keteranganUnitKerja = t('documentForm:form.unitKerja.keteranganPilih');

    function handleSubmit(event: FormEvent) {
        event.preventDefault();

        // Nilai mekanisme akses digabungkan tepat sebelum dikirim, bukan
        // disimpan di state formulir. Bentuk yang dipakai antarmuka (daftar
        // objek pengguna) berbeda dari yang dibutuhkan server (daftar id), dan
        // menyatukannya sejak awal membuat salah satunya harus berkompromi.
        transform((formulir) => {
            const { file, ...tanpaBerkas } = formulir;

            const akhir = {
                ...tanpaBerkas,
                is_private: akses.is_private,
                is_shared_to_all: akses.is_shared_to_all,
                min_tingkat_akses: akses.min_tingkat_akses,
                unit_ids: akses.unit_ids,
                shared_user_ids: akses.shared_users.map((p) => p.id),
            };

            // Pada mode ubah, medan berkas dibuang seluruhnya — bukan dikirim
            // bernilai null. Mengirimnya membuat server harus membedakan
            // "tidak diubah" dari "dikosongkan", padahal keduanya tidak pernah
            // menjadi pilihan di sini.
            return mode === 'buat' ? { ...akhir, file } : akhir;
        });

        if (mode === 'buat') {
            post(aksi, { forceFormData: true });
        } else {
            patch(aksi);
        }
    }

    const sedangMengunggah = mode === 'buat' && processing && data.file !== null;

    /*
     * `akses` adalah kunci galat yang ditambahkan server ketika tidak ada satu
     * pun mekanisme akses aktif. Ia sengaja tidak punya medan formulir
     * padanannya — yang salah bukan satu kolom, melainkan kombinasi beberapa
     * kolom sekaligus. Karena itu tipenya dibaca terpisah, bukan dipaksa masuk
     * ke bentuk data formulir.
     */
    const galatAkses = (errors as Partial<Record<string, string>>).akses;

    return (
        // `grid-cols-1` eksplisit (bukan hanya mengandalkan `xl:grid-cols-3`)
        // penting: tanpa definisi kolom di bawah breakpoint `xl`, track grid
        // implisit memakai sizing `auto` (berbasis max-content bawaan CSS
        // Grid) alih-alih `minmax(0,1fr)` milik Tailwind — kolom menolak
        // menyusut di bawah lebar intrinsik kontennya dan seluruh formulir
        // meluber horizontal di layar sempit. Pola serupa (grid/flex item
        // menolak menyusut) pernah ditemukan di pemilih unit, lihat §5.2
        // Progres-dan-Lanjutan.md.
        <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-5 xl:grid-cols-3">
            <div className="min-w-0 space-y-5 xl:col-span-2">
                {mode === 'buat' && opsi.lingkungan_kurang && (
                    <Alert variant="warning" title={t('documentForm:form.peringatanBatasUnggah.judul')}>
                        {t('documentForm:form.peringatanBatasUnggah.keterangan', {
                            batasDijanjikan: opsi.batas_dijanjikan_label,
                            batasUnggah: opsi.batas_unggah_label,
                        })}
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('documentForm:form.kartuBerkas.judul')}</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {mode === 'ubah' && berkas !== undefined ? (
                            <BerkasTerkunci berkas={berkas} unggahVersiBaru={unggahVersiBaru} />
                        ) : sedangMengunggah && progress ? (
                            <UploadProgress
                                persen={progress.percentage ?? null}
                                terkirim={progress.loaded ?? 0}
                                total={progress.total ?? data.file?.size ?? 0}
                                namaBerkas={data.file?.name ?? ''}
                            />
                        ) : (
                            <div className="space-y-4">
                                {replacesDocumentId !== null && versiTerbaru !== undefined && (
                                    <>
                                        <Alert variant="warning" title={t('documentForm:form.peringatanFormatVersi.judul')}>
                                            {t('documentForm:form.peringatanFormatVersi.keterangan', {
                                                format: labelFormat(versiTerbaru.tipe, t),
                                            })}
                                        </Alert>
                                        <VersiTerbaruTersimpan berkas={versiTerbaru} />
                                    </>
                                )}
                                <FileDropzone
                                    berkas={data.file}
                                    onChange={(file) => setData('file', file)}
                                    batasKb={opsi.batas_unggah_kb}
                                    batasLabel={opsi.batas_unggah_label}
                                    error={errors.file}
                                />
                            </div>
                        )}
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('documentForm:form.kartuInformasi.judul')}</CardTitle>
                    </CardHeader>
                    <CardBody className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('documentForm:form.nomor.label')} error={errors.nomor} required>
                            {(props) => (
                                <Input
                                    {...props}
                                    value={data.nomor}
                                    placeholder={t('documentForm:form.nomor.placeholder')}
                                    invalid={Boolean(errors.nomor)}
                                    onChange={(e) => setData('nomor', e.target.value)}
                                />
                            )}
                        </Field>

                        <Field label={t('documentForm:form.tanggal.label')} error={errors.tanggal} required>
                            {(props) => (
                                <Input
                                    {...props}
                                    type="date"
                                    value={data.tanggal}
                                    invalid={Boolean(errors.tanggal)}
                                    onChange={(e) => setData('tanggal', e.target.value)}
                                />
                            )}
                        </Field>

                        <Field
                            label={t('documentForm:form.judul.label')}
                            error={errors.judul}
                            required
                            className="sm:col-span-2"
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    value={data.judul}
                                    invalid={Boolean(errors.judul)}
                                    onChange={(e) => setData('judul', e.target.value)}
                                />
                            )}
                        </Field>

                        <div className="space-y-1.5 sm:col-span-2">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('documentForm:form.kategori.label')} error={errors.category_id} required>
                                    {(props) => (
                                        <Select
                                            {...props}
                                            placeholder={t('documentForm:form.kategori.placeholder')}
                                            value={data.category_id}
                                            invalid={Boolean(errors.category_id)}
                                            options={opsi.kategori.map((k) => ({
                                                value: k.id,
                                                label: k.nama,
                                            }))}
                                            onChange={(e) => setData('category_id', e.target.value)}
                                        />
                                    )}
                                </Field>

                                {/* Tanpa `hint` di sini: dulu penjelasan tampil di antara
                                    label dan kendali, sehingga kotak dropdown ini turun
                                    lebih rendah daripada Kategori di sebelahnya dan
                                    keduanya tidak sejajar. Penjelasannya sekarang satu
                                    baris di bawah, meliputi kedua kolom, sama seperti
                                    pola pada Masa Berlaku di bawah. */}
                                <Field
                                    label={t('documentForm:form.unitKerja.label')}
                                    error={errors.origin_unit_id}
                                    required={!unitKerjaDitentukan}
                                >
                                    {(props) => unitKerjaDitentukan ? (
                                        <Input
                                            {...props}
                                            value={opsi.unit_akun_nama ?? 'Pimpinan BPMA'}
                                            readOnly
                                            aria-readonly="true"
                                        />
                                    ) : (
                                        <Select
                                            {...props}
                                            placeholder={t('documentForm:form.unitKerja.placeholderSelect')}
                                            value={data.origin_unit_id}
                                            invalid={Boolean(errors.origin_unit_id)}
                                            options={opsi.unit.map((u) => ({
                                                value: u.id,
                                                label: u.nama,
                                            }))}
                                            onChange={(e) => setData('origin_unit_id', e.target.value)}
                                        />
                                    )}
                                </Field>
                            </div>
                            <p className="text-xs text-ink-muted">
                                {unitKerjaDitentukan
                                    ? t('documentForm:form.unitKerja.keteranganDitentukan')
                                    : keteranganUnitKerja}
                            </p>
                        </div>

                        <div className="space-y-1.5 sm:col-span-2">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('documentForm:form.masaBerlaku.label')} optional error={errors.masa_berlaku}>
                                    {(props) => (
                                        <Input
                                            {...props}
                                            type="date"
                                            value={data.masa_berlaku}
                                            invalid={Boolean(errors.masa_berlaku)}
                                            onChange={(e) => setData('masa_berlaku', e.target.value)}
                                        />
                                    )}
                                </Field>

                                <Field label={t('documentForm:form.editScope.label')} error={errors.edit_scope}>
                                    {(props) => (
                                        <Select
                                            {...props}
                                            value={data.edit_scope}
                                            options={[
                                                { value: 'owner_only', label: t('documentForm:form.editScope.opsi.pemilikSaja') },
                                                {
                                                    value: 'match_visibility',
                                                    label: t('documentForm:form.editScope.opsi.samaSepertiAkses'),
                                                },
                                            ]}
                                            onChange={(e) => setData('edit_scope', e.target.value)}
                                        />
                                    )}
                                </Field>
                            </div>
                            <p className="text-xs text-ink-muted">{t('documentForm:form.masaBerlaku.keterangan')}</p>
                        </div>

                        <Field
                            label={t('documentForm:form.deskripsi.label')}
                            error={errors.deskripsi}
                            className="sm:col-span-2"
                        >
                            {(props) => (
                                <Textarea
                                    {...props}
                                    rows={3}
                                    value={data.deskripsi}
                                    invalid={Boolean(errors.deskripsi)}
                                    onChange={(e) => setData('deskripsi', e.target.value)}
                                />
                            )}
                        </Field>

                        {(mode === 'ubah' || replacesDocumentId !== null) && (
                            <Field
                                label={t('documentForm:form.catatanVersi.label')}
                                hint={mode === 'ubah'
                                    ? t('documentForm:form.catatanVersi.hintUbah')
                                    : t('documentForm:form.catatanVersi.hintVersiBaru')}
                                error={errors.version_note}
                                required
                                className="sm:col-span-2"
                            >
                                {(props) => (
                                    <Textarea
                                        {...props}
                                        rows={3}
                                        value={data.version_note}
                                        invalid={Boolean(errors.version_note)}
                                        onChange={(e) => setData('version_note', e.target.value)}
                                        placeholder={t('documentForm:form.catatanVersi.placeholder')}
                                    />
                                )}
                            </Field>
                        )}
                    </CardBody>
                </Card>
            </div>

            <div className="space-y-5">
                <Card>
                    <CardHeader>
                        <div>
                            <CardTitle>{t('documentForm:form.kartuAkses.judul')}</CardTitle>
                            <p className="mt-0.5 text-sm text-ink-muted">
                                {t('documentForm:form.kartuAkses.keterangan')}
                            </p>
                        </div>
                    </CardHeader>
                    <CardBody>
                        <AccessMechanismPicker
                            nilai={akses}
                            onChange={setAkses}
                            units={opsi.unit_pohon}
                            jenjang={opsi.jenjang_jabatan}
                            error={galatAkses}
                        />
                    </CardBody>
                </Card>

                <div className="grid grid-cols-2 gap-2">
                    <Link href={batal}>
                        <Button type="button" variant="secondary" size="lg" className="w-full">
                            {t('common:aksi.batal')}
                        </Button>
                    </Link>

                    <Button
                        type="submit"
                        icon={mode === 'buat' ? Upload : Save}
                        loading={processing}
                        size="lg"
                        className="w-full"
                    >
                        {mode === 'buat'
                            ? processing
                                ? t('documentForm:form.tombol.mengunggah')
                                : replacesDocumentId === null
                                  ? t('documentForm:form.tombol.unggahDokumen')
                                  : t('documentForm:form.tombol.unggahVersiBaru')
                            : processing
                              ? t('documentForm:form.tombol.menyimpan')
                              : t('common:aksi.simpanPerubahan')}
                    </Button>
                </div>
            </div>
        </form>
    );
}

/**
 * Berkas yang sudah tersimpan, ditampilkan tapi tidak dapat diganti.
 *
 * Ditampilkan — bukan disembunyikan — supaya penyunting tahu dokumen mana yang
 * sedang ia ubah. Alasannya ikut disebutkan, karena tanpa itu tampilannya
 * terbaca seperti kerusakan: kolom yang ada tapi tidak bisa disentuh.
 */
function BerkasTerkunci({
    berkas,
    unggahVersiBaru,
}: {
    berkas: RingkasanBerkas;
    unggahVersiBaru?: string;
}) {
    const { t } = useTranslation('documentForm');

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-3 rounded-card border border-line bg-surface-sunken p-3">
                <FileTypeBadge mime={berkas.tipe} namaBerkas={berkas.nama} size="md" />

                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-ink">{berkas.nama}</p>
                    <p className="font-mono text-xs text-ink-subtle">
                        {formatUkuranBerkas(berkas.ukuran)}
                    </p>
                </div>

                <Lock className="size-4 shrink-0 text-ink-subtle" aria-hidden />
            </div>

            <p className="text-xs text-ink-muted">
                {t('documentForm:form.berkasTerkunci.keterangan')}
            </p>

            {unggahVersiBaru && (
                <Link href={unggahVersiBaru} className="inline-flex">
                    <Button type="button" variant="secondary" size="sm" icon={Upload}>
                        {t('documentForm:form.tombol.unggahVersiBaru')}
                    </Button>
                </Link>
            )}
        </div>
    );
}

interface RingkasanBerkas {
    id: number;
    nama: string;
    tipe: string;
    ukuran: number;
    thumbnailTersedia: boolean;
}

/** Pembanding yang tidak pernah berubah saat pengguna memilih berkas baru. */
function VersiTerbaruTersimpan({ berkas }: { berkas: RingkasanBerkas }) {
    const { t } = useTranslation('documentForm');

    return (
        <div className="overflow-hidden rounded-card border border-line bg-surface">
            <p className="border-b border-line bg-surface-sunken px-3 py-2 text-xs font-semibold text-ink-muted">
                {t('documentForm:form.versiTerbaru.label')}
            </p>
            <div className="flex min-w-0 items-center gap-3 p-3">
                <DocumentThumbnail
                    id={berkas.id}
                    mime={berkas.tipe}
                    namaBerkas={berkas.nama}
                    judul={berkas.nama}
                    tersedia={berkas.thumbnailTersedia}
                    className="h-16 w-20 shrink-0 rounded-card"
                />
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-ink">{berkas.nama}</p>
                    <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                        <FileTypeBadge mime={berkas.tipe} namaBerkas={berkas.nama} />
                        <span className="font-mono">{formatUkuranBerkas(berkas.ukuran)}</span>
                    </p>
                </div>
            </div>
        </div>
    );
}

function labelFormat(mime: string, t: TFunction): string {
    if (mime.startsWith('image/')) return t('documentForm:form.formatBerkas.gambar');
    if (mime === 'application/pdf') return t('documentForm:form.formatBerkas.pdf');
    if (mime.includes('word')) return t('documentForm:form.formatBerkas.word');

    return mime;
}

import {
    AccessMechanismPicker,
    type JenjangJabatan,
    type NilaiAkses,
} from '@/Components/domain/AccessMechanismPicker';
import { FileDropzone } from '@/Components/domain/FileDropzone';
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

export interface OpsiFormulirDokumen {
    kategori: { id: number; nama: string }[];
    unit: { id: number; nama: string }[];
    unit_pohon: UnitPilihan[];
    jenjang_jabatan: JenjangJabatan[];
    batas_unggah_kb: number | null;
    batas_unggah_label: string;
    batas_dijanjikan_label: string;
    lingkungan_kurang: boolean;
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
    berkas?: { nama: string; tipe: string; ukuran: number };
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
}: DocumentFormProps) {
    const [akses, setAkses] = useState<NilaiAkses>(aksesAwal);

    const { data, setData, post, patch, processing, progress, errors, transform } =
        useForm<NilaiAwalDokumen & { file: File | null }>({
            ...awal,
            file: null,
        });

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
        <form onSubmit={handleSubmit} className="grid gap-5 xl:grid-cols-3">
            <div className="space-y-5 xl:col-span-2">
                {mode === 'buat' && opsi.lingkungan_kurang && (
                    <Alert variant="warning" title="Batas unggahan di bawah semestinya">
                        Aplikasi menetapkan {opsi.batas_dijanjikan_label}, tapi mesin ini
                        hanya sanggup {opsi.batas_unggah_label}. Setelan PHP atau server
                        web perlu dinaikkan — lihat README bagian Batas Ukuran Unggahan.
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Berkas Dokumen</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {mode === 'ubah' && berkas !== undefined ? (
                            <BerkasTerkunci berkas={berkas} />
                        ) : sedangMengunggah && progress ? (
                            <UploadProgress
                                persen={progress.percentage ?? null}
                                terkirim={progress.loaded ?? 0}
                                total={progress.total ?? data.file?.size ?? 0}
                                namaBerkas={data.file?.name ?? ''}
                            />
                        ) : (
                            <FileDropzone
                                berkas={data.file}
                                onChange={(file) => setData('file', file)}
                                batasKb={opsi.batas_unggah_kb}
                                batasLabel={opsi.batas_unggah_label}
                                error={errors.file}
                            />
                        )}
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Dokumen</CardTitle>
                    </CardHeader>
                    <CardBody className="grid gap-4 sm:grid-cols-2">
                        <Field label="Nomor Dokumen" error={errors.nomor} required>
                            {(props) => (
                                <Input
                                    {...props}
                                    value={data.nomor}
                                    placeholder="042/BPMA/DPR-TPL/VIII/2026"
                                    invalid={Boolean(errors.nomor)}
                                    onChange={(e) => setData('nomor', e.target.value)}
                                />
                            )}
                        </Field>

                        <Field label="Tanggal Dokumen" error={errors.tanggal} required>
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
                            label="Judul"
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

                        <Field label="Kategori" error={errors.category_id} required>
                            {(props) => (
                                <Select
                                    {...props}
                                    placeholder="Pilih kategori"
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

                        <Field label="Unit Asal" error={errors.origin_unit_id}>
                            {(props) => (
                                <Select
                                    {...props}
                                    placeholder="Pilih unit asal"
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

                        <Field
                            label="Masa Berlaku"
                            hint="Kosongkan bila berlaku tanpa batas waktu."
                            error={errors.masa_berlaku}
                        >
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

                        <Field label="Siapa yang Boleh Mengubah" error={errors.edit_scope}>
                            {(props) => (
                                <Select
                                    {...props}
                                    value={data.edit_scope}
                                    options={[
                                        { value: 'owner_only', label: 'Hanya saya' },
                                        {
                                            value: 'match_visibility',
                                            label: 'Sama seperti akses',
                                        },
                                    ]}
                                    onChange={(e) => setData('edit_scope', e.target.value)}
                                />
                            )}
                        </Field>

                        <Field
                            label="Deskripsi"
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
                    </CardBody>
                </Card>
            </div>

            <div className="space-y-5">
                <Card>
                    <CardHeader>
                        <div>
                            <CardTitle>Pengaturan Akses</CardTitle>
                            <p className="mt-0.5 text-sm text-ink-muted">
                                Boleh mengaktifkan lebih dari satu sekaligus.
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

                <div className="flex flex-col gap-2 sm:flex-row xl:flex-col">
                    <Button
                        type="submit"
                        icon={mode === 'buat' ? Upload : Save}
                        loading={processing}
                        size="lg"
                        className="flex-1"
                    >
                        {mode === 'buat'
                            ? processing
                                ? 'Mengunggah…'
                                : 'Unggah Dokumen'
                            : processing
                              ? 'Menyimpan…'
                              : 'Simpan Perubahan'}
                    </Button>

                    <Link href={batal} className="flex-1">
                        <Button type="button" variant="secondary" size="lg" className="w-full">
                            Batal
                        </Button>
                    </Link>
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
}: {
    berkas: { nama: string; tipe: string; ukuran: number };
}) {
    return (
        <div className="space-y-2">
            <div className="flex items-center gap-3 rounded-card border border-line bg-surface-sunken p-3">
                <FileTypeBadge mime={berkas.tipe} size="md" />

                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-ink">{berkas.nama}</p>
                    <p className="font-mono text-xs text-ink-subtle">
                        {formatUkuranBerkas(berkas.ukuran)}
                    </p>
                </div>

                <Lock className="size-4 shrink-0 text-ink-subtle" aria-hidden />
            </div>

            <p className="text-xs text-ink-muted">
                Berkas tidak dapat diganti. Bila isinya berubah, unggah sebagai dokumen
                baru supaya riwayat dokumen ini tetap utuh.
            </p>
        </div>
    );
}

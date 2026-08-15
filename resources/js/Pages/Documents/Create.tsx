import {
    AccessMechanismPicker,
    type JenjangJabatan,
    type NilaiAkses,
} from '@/Components/domain/AccessMechanismPicker';
import { FileDropzone } from '@/Components/domain/FileDropzone';
import { UploadProgress } from '@/Components/domain/UploadProgress';
import type { UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { AppLayout } from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Upload } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface OpsiFormulir {
    kategori: { id: number; nama: string }[];
    unit: { id: number; nama: string }[];
    unit_pohon: UnitPilihan[];
    jenjang_jabatan: JenjangJabatan[];
    batas_unggah_kb: number | null;
    batas_unggah_label: string;
    batas_dijanjikan_label: string;
    lingkungan_kurang: boolean;
}

interface CreateProps {
    opsi: OpsiFormulir;
}

export default function Create({ opsi }: CreateProps) {
    const [akses, setAkses] = useState<NilaiAkses>({
        is_shared_to_all: false,
        min_tingkat_akses: null,
        unit_ids: [],
        shared_users: [],
    });

    const { data, setData, post, processing, progress, errors, transform } = useForm<{
        nomor: string;
        judul: string;
        deskripsi: string;
        category_id: string;
        origin_unit_id: string;
        tanggal: string;
        masa_berlaku: string;
        edit_scope: string;
        file: File | null;
    }>({
        nomor: '',
        judul: '',
        deskripsi: '',
        category_id: '',
        origin_unit_id: '',
        tanggal: new Date().toISOString().slice(0, 10),
        masa_berlaku: '',
        edit_scope: 'owner_only',
        file: null,
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();

        // Nilai mekanisme akses digabungkan tepat sebelum dikirim, bukan
        // disimpan di state formulir. Bentuk yang dipakai antarmuka (daftar
        // objek pengguna) berbeda dari yang dibutuhkan server (daftar id), dan
        // menyatukannya sejak awal membuat salah satunya harus berkompromi.
        transform((formulir) => ({
            ...formulir,
            is_shared_to_all: akses.is_shared_to_all,
            min_tingkat_akses: akses.min_tingkat_akses,
            unit_ids: akses.unit_ids,
            shared_user_ids: akses.shared_users.map((p) => p.id),
        }));

        post('/documents', { forceFormData: true });
    }

    const sedangMengunggah = processing && data.file !== null;

    /*
     * `akses` adalah kunci galat yang ditambahkan server ketika tidak ada satu
     * pun mekanisme akses aktif. Ia sengaja tidak punya medan formulir
     * padanannya — yang salah bukan satu kolom, melainkan kombinasi beberapa
     * kolom sekaligus. Karena itu tipenya dibaca terpisah, bukan dipaksa masuk
     * ke bentuk data formulir.
     */
    const galatAkses = (errors as Partial<Record<string, string>>).akses;

    return (
        <AppLayout
            title="Unggah Dokumen"
            header={
                <div className="flex min-w-0 items-center gap-2 text-sm">
                    <Link
                        href="/documents"
                        className="flex shrink-0 items-center gap-1.5 font-medium text-ink-muted hover:text-ink"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                        Semua Dokumen
                    </Link>
                    <span className="text-ink-subtle" aria-hidden>
                        /
                    </span>
                    <span className="truncate font-semibold text-ink">Unggah Dokumen</span>
                </div>
            }
        >
            <form onSubmit={handleSubmit} className="grid gap-5 xl:grid-cols-3">
                <div className="space-y-5 xl:col-span-2">
                    {opsi.lingkungan_kurang && (
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
                            {sedangMengunggah && progress ? (
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
                                        onChange={(e) =>
                                            setData('origin_unit_id', e.target.value)
                                        }
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
                                    <textarea
                                        {...props}
                                        rows={3}
                                        value={data.deskripsi}
                                        onChange={(e) => setData('deskripsi', e.target.value)}
                                        className="block w-full rounded-lg border border-line bg-white px-3 py-2 text-sm text-ink placeholder:text-ink-subtle focus:border-brand-700 focus:ring-1 focus:ring-brand-700"
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
                            icon={Upload}
                            loading={processing}
                            size="lg"
                            className="flex-1"
                        >
                            {processing ? 'Mengunggah…' : 'Unggah Dokumen'}
                        </Button>

                        <Link href="/documents" className="flex-1">
                            <Button type="button" variant="secondary" size="lg" className="w-full">
                                Batal
                            </Button>
                        </Link>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}

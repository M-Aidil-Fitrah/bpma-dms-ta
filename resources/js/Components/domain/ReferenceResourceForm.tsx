import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Textarea } from '@/Components/ui/Textarea';
import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { type FormEvent } from 'react';
import { type ReferenceResourceKind } from './ReferenceResourceActions';

export interface UnitIndukOption {
    id: number;
    nama: string;
    kedalaman: number;
}

interface ReferenceResourceFormProps {
    jenis: ReferenceResourceKind;
    mode: 'buat' | 'ubah';
    aksi: string;
    batal: string;
    awal?: App.Data.ReferensiEditData;
    induk?: readonly UnitIndukOption[];
}

/**
 * Satu formulir untuk ketiga data referensi. Medan spesifik dirender hanya
 * pada jenis yang membutuhkannya; aturan CRUD dan tombol tetap satu tempat.
 */
export function ReferenceResourceForm({ jenis, mode, aksi, batal, awal, induk = [] }: ReferenceResourceFormProps) {
    const { data, setData, post, patch, processing, errors } = useForm({
        nama: awal?.nama ?? '',
        tingkat_akses: awal?.tingkat_akses?.toString() ?? '',
        parent_id: awal?.parent_id?.toString() ?? '',
        tipe: awal?.tipe ?? 'divisi',
        deskripsi: awal?.deskripsi ?? '',
    });
    const label = labelJenis(jenis);

    function submit(event: FormEvent) {
        event.preventDefault();
        if (mode === 'buat') post(aksi);
        else patch(aksi);
    }

    return (
        <form onSubmit={submit} className="max-w-xl space-y-5">
            <Card>
                <CardHeader>
                    <CardTitle>Informasi {label}</CardTitle>
                </CardHeader>
                <CardBody className="grid gap-4 sm:grid-cols-2">
                    <Field label={`Nama ${label}`} error={errors.nama} required className="sm:col-span-2">
                        {(props) => <Input {...props} value={data.nama} invalid={Boolean(errors.nama)} onChange={(event) => setData('nama', event.target.value)} />}
                    </Field>

                    {jenis === 'jabatan' && (
                        <Field label="Tingkat akses" error={errors.tingkat_akses} required hint="1 adalah jenjang tertinggi; angka lebih besar berarti wewenang lebih rendah." className="sm:col-span-2">
                            {(props) => <Input {...props} type="number" min="1" max="255" value={data.tingkat_akses} invalid={Boolean(errors.tingkat_akses)} onChange={(event) => setData('tingkat_akses', event.target.value)} />}
                        </Field>
                    )}

                    {jenis === 'unit' && (
                        <>
                            <Field label="Unit induk" error={errors.parent_id} hint="Kosongkan untuk unit tingkat atas.">
                                {(props) => <Select {...props} placeholder="Tidak ada unit induk" value={data.parent_id} invalid={Boolean(errors.parent_id)} options={induk.map((unit) => ({ value: unit.id, label: `${'— '.repeat(unit.kedalaman)}${unit.nama}` }))} onChange={(event) => setData('parent_id', event.target.value)} />}
                            </Field>
                            <Field label="Tipe unit" error={errors.tipe} required>
                                {(props) => <Select {...props} value={data.tipe} invalid={Boolean(errors.tipe)} options={[{ value: 'sekretaris', label: 'Sekretaris' }, { value: 'deputi', label: 'Deputi' }, { value: 'divisi', label: 'Divisi' }]} onChange={(event) => setData('tipe', event.target.value)} />}
                            </Field>
                        </>
                    )}

                    {jenis === 'kategori' && (
                        <Field label="Deskripsi" error={errors.deskripsi} hint="Opsional; jelaskan jenis dokumen yang masuk ke kategori ini." className="sm:col-span-2">
                            {(props) => <Textarea {...props} rows={5} maxLength={2000} value={data.deskripsi} invalid={Boolean(errors.deskripsi)} onChange={(event) => setData('deskripsi', event.target.value)} />}
                        </Field>
                    )}
                </CardBody>
            </Card>

            <div className="flex gap-2">
                <Button type="submit" icon={Save} loading={processing}>
                    {processing ? 'Menyimpan…' : mode === 'buat' ? `Tambah ${label}` : 'Simpan Perubahan'}
                </Button>
                <Link href={batal}><Button type="button" variant="secondary">Batal</Button></Link>
            </div>
        </form>
    );
}

function labelJenis(jenis: ReferenceResourceKind): string {
    return jenis === 'unit' ? 'Unit Kerja' : jenis[0].toUpperCase() + jenis.slice(1);
}

import { Button } from '@/Components/ui/Button';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Textarea } from '@/Components/ui/Textarea';
import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
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
    const { t } = useTranslation(['reference', 'common']);
    const konfirmasikan = usePasswordConfirmation();
    const { data, setData, post, patch, processing, errors } = useForm({
        nama: awal?.nama ?? '',
        tingkat_akses: awal?.tingkat_akses?.toString() ?? '',
        parent_id: awal?.parent_id?.toString() ?? '',
        tipe: awal?.tipe ?? 'divisi',
        deskripsi: awal?.deskripsi ?? '',
    });
    const label = t(`reference:${jenis}.label`);

    function submit(event: FormEvent) {
        event.preventDefault();
        konfirmasikan(simpan);
    }

    function simpan() {
        if (mode === 'buat') post(aksi);
        else patch(aksi);
    }

    return (
        <form onSubmit={submit} className="max-w-xl space-y-5">
            <Card>
                <CardHeader>
                    <CardTitle>{t('reference:form.informasiJudul', { label })}</CardTitle>
                </CardHeader>
                <CardBody className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('reference:form.namaLabel', { label })} error={errors.nama} required className="sm:col-span-2">
                        {(props) => <Input {...props} value={data.nama} invalid={Boolean(errors.nama)} onChange={(event) => setData('nama', event.target.value)} />}
                    </Field>

                    {jenis === 'jabatan' && (
                        <Field label={t('reference:form.tingkatAkses.label')} error={errors.tingkat_akses} required hint={t('reference:form.tingkatAkses.hint')} className="sm:col-span-2">
                            {(props) => <Input {...props} type="number" min="1" max="255" value={data.tingkat_akses} invalid={Boolean(errors.tingkat_akses)} onChange={(event) => setData('tingkat_akses', event.target.value)} />}
                        </Field>
                    )}

                    {jenis === 'unit' && (
                        <>
                            <Field label={t('reference:form.unitInduk.label')} error={errors.parent_id} hint={t('reference:form.unitInduk.hint')}>
                                {(props) => <Select {...props} placeholder={t('reference:form.unitInduk.placeholder')} value={data.parent_id} invalid={Boolean(errors.parent_id)} options={induk.map((unit) => ({ value: unit.id, label: `${'— '.repeat(unit.kedalaman)}${unit.nama}` }))} onChange={(event) => setData('parent_id', event.target.value)} />}
                            </Field>
                            <Field label={t('reference:form.tipeUnit.label')} error={errors.tipe} required>
                                {(props) => <Select {...props} value={data.tipe} invalid={Boolean(errors.tipe)} options={[{ value: 'sekretaris', label: t('reference:form.tipeUnit.opsi.sekretaris') }, { value: 'deputi', label: t('reference:form.tipeUnit.opsi.deputi') }, { value: 'divisi', label: t('reference:form.tipeUnit.opsi.divisi') }]} onChange={(event) => setData('tipe', event.target.value)} />}
                            </Field>
                        </>
                    )}

                    {jenis === 'kategori' && (
                        <Field label={t('reference:form.deskripsi.label')} error={errors.deskripsi} hint={t('reference:form.deskripsi.hint')} className="sm:col-span-2">
                            {(props) => <Textarea {...props} rows={5} maxLength={2000} value={data.deskripsi} invalid={Boolean(errors.deskripsi)} onChange={(event) => setData('deskripsi', event.target.value)} />}
                        </Field>
                    )}
                </CardBody>
            </Card>

            <div className="flex flex-col gap-2 sm:flex-row">
                <Button type="submit" icon={Save} loading={processing} className="w-full sm:w-auto">
                    {processing ? t('reference:form.menyimpan') : mode === 'buat' ? t('reference:umum.tambahEntitas', { label }) : t('common:aksi.simpanPerubahan')}
                </Button>
                <Link href={batal} className="w-full sm:w-auto"><Button type="button" variant="secondary" className="w-full">{t('common:aksi.batal')}</Button></Link>
            </div>
        </form>
    );
}

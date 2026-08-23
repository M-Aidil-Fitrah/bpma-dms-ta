import { Alert } from '@/Components/ui/Alert';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { AppLayout } from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';
import { RotateCcw, Save } from 'lucide-react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';

interface SettingsProps {
    pengaturan: App.Data.PengaturanFormData;
}

/** Pengaturan yang memang dapat diubah Superadmin, dibatasi allowlist backend. */
export default function Index({ pengaturan }: SettingsProps) {
    const { t } = useTranslation(['users', 'common']);
    const konfirmasikan = usePasswordConfirmation();
    const { data, setData, patch, processing, errors } = useForm({
        unggah_batas_kb: String(pengaturan.unggah_batas_kb),
        dokumen_per_halaman: String(pengaturan.dokumen_per_halaman),
        dokumen_rentang_evaluasi_awal: String(pengaturan.dokumen_rentang_evaluasi_awal),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        konfirmasikan(() => patch('/admin/settings'));
    }

    function kembaliKeBawaan() {
        // String kosong dikonversi Laravel menjadi null. PengaturanService
        // kemudian menghapus override, sehingga perubahan config di masa depan
        // tetap diikuti — tidak sekadar menyalin angka bawaan hari ini.
        setData({ unggah_batas_kb: '', dokumen_per_halaman: '', dokumen_rentang_evaluasi_awal: '' });
    }

    return (
        <AppLayout title={t('users:settings.pageTitle')}>
            <form onSubmit={submit} className="max-w-2xl space-y-5">
                {pengaturan.unggah_dibatasi_php && (
                    <Alert variant="warning">
                        {t('users:settings.phpLimitWarning', { value: formatKilobyte(pengaturan.unggah_batas_efektif_kb, t) })}
                    </Alert>
                )}

                <Card>
                    <CardHeader><CardTitle>{t('users:settings.filesSection.title')}</CardTitle></CardHeader>
                    <CardBody className="space-y-4">
                        <Field label={t('users:settings.filesSection.uploadLimitLabel')} required error={errors.unggah_batas_kb} hint={t('users:settings.filesSection.uploadLimitHint', { value: formatKilobyte(pengaturan.unggah_batas_kb_bawaan, t) })}>
                            {(props) => <Input {...props} type="number" min="1024" max="1048576" value={data.unggah_batas_kb} placeholder={String(pengaturan.unggah_batas_kb_bawaan)} invalid={Boolean(errors.unggah_batas_kb)} onChange={(event) => setData('unggah_batas_kb', event.target.value)} />}
                        </Field>
                        <Field label={t('users:settings.filesSection.docsPerPageLabel')} required error={errors.dokumen_per_halaman} hint={t('users:settings.filesSection.docsPerPageHint', { value: pengaturan.dokumen_per_halaman_bawaan })}>
                            {(props) => <Select {...props} placeholder={String(pengaturan.dokumen_per_halaman_bawaan)} value={data.dokumen_per_halaman} invalid={Boolean(errors.dokumen_per_halaman)} options={[10, 20, 50, 100].map((nilai) => ({ value: nilai, label: t('users:settings.filesSection.docsUnit', { value: nilai }) }))} onChange={(event) => setData('dokumen_per_halaman', event.target.value)} />}
                        </Field>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader><CardTitle>{t('users:settings.dashboardSection.title')}</CardTitle></CardHeader>
                    <CardBody>
                        <Field label={t('users:settings.dashboardSection.evalRangeLabel')} required error={errors.dokumen_rentang_evaluasi_awal} hint={t('users:settings.dashboardSection.evalRangeHint', { value: pengaturan.dokumen_rentang_evaluasi_awal_bawaan })}>
                            {(props) => <Select {...props} placeholder={String(pengaturan.dokumen_rentang_evaluasi_awal_bawaan)} value={data.dokumen_rentang_evaluasi_awal} invalid={Boolean(errors.dokumen_rentang_evaluasi_awal)} options={pengaturan.rentang_evaluasi_pilihan.map((nilai) => ({ value: nilai, label: t('users:settings.dashboardSection.daysUnit', { value: nilai }) }))} onChange={(event) => setData('dokumen_rentang_evaluasi_awal', event.target.value)} />}
                        </Field>
                    </CardBody>
                </Card>

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Button type="submit" icon={Save} loading={processing} className="w-full sm:w-auto">{processing ? t('users:settings.saving') : t('users:settings.save')}</Button>
                    <Button type="button" variant="secondary" icon={RotateCcw} onClick={kembaliKeBawaan} disabled={processing} className="w-full sm:w-auto">{t('users:settings.useDefault')}</Button>
                </div>
                <p className="text-sm text-ink-muted">{t('users:settings.resetNote')}</p>
            </form>
        </AppLayout>
    );
}

function formatKilobyte(nilai: number | null, t: TFunction): string {
    if (nilai === null) return t('users:settings.unlimited');
    if (nilai >= 1024 * 1024) return `${nilai / 1024 / 1024} GB`;
    if (nilai >= 1024) return `${nilai / 1024} MB`;
    return `${nilai} KB`;
}

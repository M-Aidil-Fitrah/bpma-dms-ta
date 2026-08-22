import { Select } from '@/Components/ui/Select';
import { BAHASA_TERSEDIA, type Bahasa } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

/**
 * Pemilih bahasa aktif — tersedia di halaman masuk maupun di dalam portal
 * (FEAT bilingual).
 *
 * Bahasa diubah dua kali sekaligus dan sengaja tidak menunggu giliran: i18next
 * lebih dulu supaya teks di layar berganti seketika, baru permintaan ke server
 * menyusul untuk menyimpan preferensinya (`users.locale` bagi yang sudah
 * masuk, cookie bagi tamu). Menunggu balasan server dulu berarti pengguna
 * melihat jeda kosong sebelum satu kata pun berubah.
 */
export function LanguageSwitcher() {
    const { t, i18n } = useTranslation('common');
    const locale = usePage().props.locale;

    function ubahBahasa(bahasa: Bahasa) {
        if (bahasa === locale) return;

        void i18n.changeLanguage(bahasa);
        router.put('/locale', { locale: bahasa }, { preserveScroll: true, preserveState: true });
    }

    return (
        <Select
            aria-label={t('bahasa.label')}
            value={locale}
            className="w-auto"
            onChange={(e) => ubahBahasa(e.target.value as Bahasa)}
            options={BAHASA_TERSEDIA.map((kode) => ({
                value: kode,
                label: kode === 'id' ? t('bahasa.indonesia') : t('bahasa.inggris'),
            }))}
        />
    );
}

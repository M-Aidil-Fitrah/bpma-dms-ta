import { Dropdown, DropdownItem } from '@/Components/ui/Dropdown';
import { cn } from '@/lib/cn';
import { BAHASA_TERSEDIA, type Bahasa } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const BENDERA: Record<Bahasa, string> = {
    id: '🇮🇩',
    en: '🇬🇧',
};

/**
 * Pemilih bahasa aktif — tersedia di halaman masuk maupun di dalam portal
 * (FEAT bilingual).
 *
 * Berbentuk menu dengan bendera, bukan `<select>` polos: dua pilihan bahasa
 * jauh lebih cepat dikenali lewat benderanya daripada dengan membaca teksnya
 * satu per satu, terutama sekilas dari sudut mata di bilah atas yang padat.
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

    const labelBahasa = (kode: Bahasa) => (kode === 'id' ? t('bahasa.indonesia') : t('bahasa.inggris'));

    return (
        <Dropdown
            trigger={
                <button
                    type="button"
                    aria-label={t('bahasa.label')}
                    className={cn(
                        'flex min-h-touch items-center gap-1.5 rounded-lg border border-line bg-surface px-2.5 py-1.5 text-sm font-medium text-ink transition-colors',
                        'hover:bg-surface-sunken focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700',
                    )}
                >
                    <span aria-hidden className="text-base leading-none">{BENDERA[locale]}</span>
                    <span className="hidden sm:inline">{locale.toUpperCase()}</span>
                    <ChevronDown className="size-3.5 shrink-0 text-ink-subtle" aria-hidden />
                </button>
            }
            panelClassName="w-44"
        >
            {BAHASA_TERSEDIA.map((kode) => (
                <DropdownItem key={kode}>
                    <button
                        type="button"
                        onClick={() => ubahBahasa(kode)}
                        className="flex min-h-touch w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-ink data-[focus]:bg-surface-sunken"
                    >
                        <span aria-hidden className="text-base leading-none">{BENDERA[kode]}</span>
                        <span className="flex-1">{labelBahasa(kode)}</span>
                        {kode === locale && <Check className="size-4 shrink-0 text-brand-700" aria-hidden />}
                    </button>
                </DropdownItem>
            ))}
        </Dropdown>
    );
}

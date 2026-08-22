import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';

import commonId from '@/lang/id/common.json';
import commonEn from '@/lang/en/common.json';
import navId from '@/lang/id/nav.json';
import navEn from '@/lang/en/nav.json';

export const BAHASA_TERSEDIA = ['id', 'en'] as const;
export type Bahasa = (typeof BAHASA_TERSEDIA)[number];

/**
 * Satu-satunya tempat namespace didaftarkan. Menambah area baru (mis.
 * `documents`) berarti menambah pasangan impor + entri di sini — bukan
 * membuat instans i18next kedua.
 */
void i18next.use(initReactI18next).init({
    resources: {
        id: { common: commonId, nav: navId },
        en: { common: commonEn, nav: navEn },
    },
    // Bahasa awal ditimpa `terapkanBahasaAwal()` sebelum React merender apa
    // pun, dibaca dari `<html lang>` yang sudah ditentukan server
    // (`SetLocale` middleware) — bukan dari sini.
    lng: 'id',
    fallbackLng: 'id',
    defaultNS: 'common',
    ns: ['common', 'nav'],
    interpolation: {
        // React sudah meng-escape output-nya sendiri; escaping kedua di sini
        // hanya akan menggandakan entitas HTML pada teks yang mengandung "&".
        escapeValue: false,
    },
    returnNull: false,
});

/**
 * Menyamakan bahasa i18next dengan yang sudah ditentukan server untuk
 * permintaan ini, dibaca dari `<html lang>` (`app.blade.php`). Dipanggil
 * sekali di `app.tsx` sebelum render pertama, supaya tidak ada kedipan dari
 * bahasa bawaan ke bahasa sesungguhnya.
 */
export function terapkanBahasaAwal(): void {
    const dariHtml = document.documentElement.lang.split('-')[0];
    const bahasa = (BAHASA_TERSEDIA as readonly string[]).includes(dariHtml) ? dariHtml : 'id';

    if (bahasa !== i18next.language) {
        void i18next.changeLanguage(bahasa);
    }
}

export default i18next;

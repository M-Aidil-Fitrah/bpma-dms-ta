import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';

import commonId from '@/lang/id/common.json';
import commonEn from '@/lang/en/common.json';
import navId from '@/lang/id/nav.json';
import navEn from '@/lang/en/nav.json';
import authId from '@/lang/id/auth.json';
import authEn from '@/lang/en/auth.json';
import profileId from '@/lang/id/profile.json';
import profileEn from '@/lang/en/profile.json';
import documentFormId from '@/lang/id/documentForm.json';
import documentFormEn from '@/lang/en/documentForm.json';
import documentBrowseId from '@/lang/id/documentBrowse.json';
import documentBrowseEn from '@/lang/en/documentBrowse.json';
import workspaceId from '@/lang/id/workspace.json';
import workspaceEn from '@/lang/en/workspace.json';
import referenceId from '@/lang/id/reference.json';
import referenceEn from '@/lang/en/reference.json';
import usersId from '@/lang/id/users.json';
import usersEn from '@/lang/en/users.json';
import activityId from '@/lang/id/activity.json';
import activityEn from '@/lang/en/activity.json';
import dashboardId from '@/lang/id/dashboard.json';
import dashboardEn from '@/lang/en/dashboard.json';

export const BAHASA_TERSEDIA = ['id', 'en'] as const;
export type Bahasa = (typeof BAHASA_TERSEDIA)[number];

const NAMESPACES = [
    'common',
    'nav',
    'auth',
    'profile',
    'documentForm',
    'documentBrowse',
    'workspace',
    'reference',
    'users',
    'activity',
    'dashboard',
] as const;

/**
 * Satu-satunya tempat namespace didaftarkan. Menambah area baru berarti
 * menambah pasangan impor + entri di sini — bukan membuat instans i18next
 * kedua.
 */
void i18next.use(initReactI18next).init({
    resources: {
        id: {
            common: commonId,
            nav: navId,
            auth: authId,
            profile: profileId,
            documentForm: documentFormId,
            documentBrowse: documentBrowseId,
            workspace: workspaceId,
            reference: referenceId,
            users: usersId,
            activity: activityId,
            dashboard: dashboardId,
        },
        en: {
            common: commonEn,
            nav: navEn,
            auth: authEn,
            profile: profileEn,
            documentForm: documentFormEn,
            documentBrowse: documentBrowseEn,
            workspace: workspaceEn,
            reference: referenceEn,
            users: usersEn,
            activity: activityEn,
            dashboard: dashboardEn,
        },
    },
    // Bahasa awal ditimpa `terapkanBahasaAwal()` sebelum React merender apa
    // pun, dibaca dari `<html lang>` yang sudah ditentukan server
    // (`SetLocale` middleware) — bukan dari sini.
    lng: 'id',
    fallbackLng: 'id',
    defaultNS: 'common',
    ns: [...NAMESPACES],
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

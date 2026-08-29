import axios from 'axios';
import { tampilkanToastGlobal } from '@/lib/toastEvents';
import i18next from '@/lib/i18n';
import { router } from '@inertiajs/react';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/*
 * Validation errors tetap ditangani formulir masing-masing. Dua event ini
 * hanya untuk kegagalan transport atau respons yang tidak dapat diproses
 * Inertia; preventDefault mencegah halaman error bawaan mengambil alih UI
 * setelah umpan balik yang jelas sudah diberikan.
 */
router.on('exception', (event) => {
    event.preventDefault();
    tampilkanToastGlobal({ status: 'error', judul: i18next.t('networkError') });
});

router.on('invalid', (event) => {
    event.preventDefault();
    tampilkanToastGlobal({ status: 'error', judul: i18next.t('unexpectedResponse') });
});

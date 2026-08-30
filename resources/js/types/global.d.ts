import '@inertiajs/core';
import type { AxiosInstance } from 'axios';
import { route as ziggyRoute } from 'ziggy-js';
import type { SharedPageProps } from './';

declare global {
    interface Window {
        axios: AxiosInstance;
    }

    // Augmentasi global ambient: `var` wajib di sini — `let`/`const` tidak
    // mendaftarkan properti pada tipe `globalThis`.
    var route: typeof ziggyRoute;
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedPageProps;
    }
}

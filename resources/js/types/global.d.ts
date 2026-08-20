import '@inertiajs/core';
import type { AxiosInstance } from 'axios';
import { route as ziggyRoute } from 'ziggy-js';
import type { SharedPageProps } from './';

declare global {
    interface Window {
        axios: AxiosInstance;
    }

    /* eslint-disable no-var */
    var route: typeof ziggyRoute;
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedPageProps;
    }
}

import type { SharedPageProps } from '@/types';

/** Rute portal selalu diautentikasi; gagal di sini berarti kontrak server rusak. */
export function wajibPenggunaTerautentikasi(props: SharedPageProps): App.Data.AuthUserData {
    if (props.auth.user === null) {
        throw new Error('Halaman portal membutuhkan pengguna yang sudah masuk.');
    }

    return props.auth.user;
}

/** Memeriksa props mentah pada batas bootstrap Inertia sebelum mengaksesnya. */
export function memilikiPenggunaTerautentikasi(props: Record<string, unknown>): boolean {
    const auth = props.auth;

    return typeof auth === 'object'
        && auth !== null
        && 'user' in auth
        && auth.user !== null;
}

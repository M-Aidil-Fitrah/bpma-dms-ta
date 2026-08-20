export interface SharedPageProps {
    [key: string]: unknown;
    auth: {
        user: App.Data.AuthUserData | null;
        password_confirmed_until: string | null;
    };
    flash: {
        id: string;
        success: string | null;
        error: string | null;
        warning: string | null;
        info: string | null;
    };
}

export type PageProps<T extends Record<string, unknown> = Record<string, never>> = SharedPageProps & T;

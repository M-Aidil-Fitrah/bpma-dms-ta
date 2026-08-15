/**
 * Bentuk paginator Laravel sebagaimana dikirim ke Inertia.
 *
 * Ditulis tangan karena strukturnya berasal dari framework, bukan dari DTO
 * aplikasi — sehingga tidak ikut tergenerate bersama tipe lain.
 */
declare namespace Pagination {
    interface Link {
        url: string | null;
        label: string;
        active: boolean;
    }

    interface Paginated<T> {
        data: T[];
        links: Link[];
        current_page: number;
        last_page: number;
        per_page: number;
        from: number | null;
        to: number | null;
        total: number;
        first_page_url: string | null;
        last_page_url: string | null;
        next_page_url: string | null;
        prev_page_url: string | null;
        path: string;
    }
}

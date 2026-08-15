import { cn } from '@/lib/cn';
import { formatAngka } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export interface PaginationProps {
    /** Menerima bentuk paginator Laravel apa adanya, tanpa perlu dipetakan. */
    meta: Omit<Pagination.Paginated<unknown>, 'data'>;
    /** Kata benda untuk keterangan jumlah, mis. "dokumen". */
    labelItem?: string;
}

/**
 * Kendali pindah halaman.
 *
 * Nomor halaman diringkas dengan elipsis ketika jumlahnya banyak — daftar 208
 * halaman tidak mungkin ditampilkan seluruhnya, dan mencobanya akan merusak
 * tata letak di layar sempit.
 */
export function Pagination({ meta, labelItem = 'data' }: PaginationProps) {
    if (meta.last_page <= 1) {
        return (
            <p className="w-full text-sm text-ink-muted">
                Menampilkan {formatAngka(meta.total)} {labelItem}
            </p>
        );
    }

    const halaman = susunNomorHalaman(meta.current_page, meta.last_page);

    return (
        <nav
            aria-label="Navigasi halaman"
            // `w-full` penting: tanpa itu nav menyusut mengikuti isinya dan
            // seluruh kendali menempel di kiri, sehingga `justify-between`
            // tidak berpengaruh apa pun.
            className="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p className="text-sm text-ink-muted">
                Menampilkan{' '}
                <span className="font-medium text-ink">
                    {formatAngka(meta.from ?? 0)}–{formatAngka(meta.to ?? 0)}
                </span>{' '}
                dari <span className="font-medium text-ink">{formatAngka(meta.total)}</span>{' '}
                {labelItem}
            </p>

            <div className="flex items-center gap-1">
                <TombolArah
                    url={meta.prev_page_url}
                    label="Halaman sebelumnya"
                    arah="prev"
                />

                {halaman.map((nomor, index) =>
                    nomor === null ? (
                        <span
                            key={`elipsis-${index}`}
                            aria-hidden
                            className="px-1 text-sm text-ink-subtle"
                        >
                            …
                        </span>
                    ) : (
                        <Link
                            key={nomor}
                            href={urlHalaman(meta.path, nomor)}
                            preserveScroll
                            preserveState
                            aria-label={`Halaman ${nomor}`}
                            aria-current={nomor === meta.current_page ? 'page' : undefined}
                            className={cn(
                                'inline-flex min-h-touch min-w-touch items-center justify-center rounded-lg px-3 text-sm font-medium transition-colors sm:min-h-9 sm:min-w-9',
                                nomor === meta.current_page
                                    ? 'bg-brand-700 text-white'
                                    : 'border border-line bg-white text-ink-muted hover:bg-surface-sunken hover:text-ink',
                            )}
                        >
                            {nomor}
                        </Link>
                    ),
                )}

                <TombolArah url={meta.next_page_url} label="Halaman berikutnya" arah="next" />
            </div>
        </nav>
    );
}

function TombolArah({
    url,
    label,
    arah,
}: {
    url: string | null;
    label: string;
    arah: 'prev' | 'next';
}) {
    const Icon = arah === 'prev' ? ChevronLeft : ChevronRight;
    const kelas =
        'inline-flex min-h-touch min-w-touch items-center justify-center rounded-lg border border-line sm:min-h-9 sm:min-w-9';

    if (url === null) {
        return (
            <span
                aria-hidden
                className={cn(kelas, 'cursor-not-allowed bg-surface-sunken text-ink-subtle')}
            >
                <Icon className="size-4" />
            </span>
        );
    }

    return (
        <Link
            href={url}
            preserveScroll
            preserveState
            aria-label={label}
            className={cn(kelas, 'bg-white text-ink-muted hover:bg-surface-sunken hover:text-ink')}
        >
            <Icon className="size-4" aria-hidden />
        </Link>
    );
}

function urlHalaman(path: string, halaman: number): string {
    const url = new URL(path, window.location.origin);
    // Penyaring yang sedang aktif ikut dibawa, supaya berpindah halaman tidak
    // diam-diam mengosongkan pencarian yang baru saja diketik pengguna.
    new URLSearchParams(window.location.search).forEach((nilai, kunci) => {
        if (kunci !== 'page') url.searchParams.set(kunci, nilai);
    });
    url.searchParams.set('page', String(halaman));

    return url.pathname + url.search;
}

/**
 * Deret nomor halaman dengan elipsis: 1 … 4 5 6 … 208
 */
function susunNomorHalaman(sekarang: number, total: number): (number | null)[] {
    const sekitar = 1;
    const nomor = new Set<number>([1, total]);

    for (let i = sekarang - sekitar; i <= sekarang + sekitar; i++) {
        if (i >= 1 && i <= total) nomor.add(i);
    }

    const terurut = [...nomor].sort((a, b) => a - b);
    const hasil: (number | null)[] = [];

    terurut.forEach((n, i) => {
        if (i > 0 && n - terurut[i - 1] > 1) hasil.push(null);
        hasil.push(n);
    });

    return hasil;
}

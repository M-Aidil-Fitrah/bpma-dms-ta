import { cn } from '@/lib/cn';
import { daftarkanPenerimaToast } from '@/lib/toastEvents';
import {
    CircleAlert,
    CircleCheck,
    CircleX,
    Info,
    X,
    type LucideIcon,
} from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { useTranslation } from 'react-i18next';

export type StatusToast = 'success' | 'error' | 'warning' | 'info';

export interface IsiToast {
    status: StatusToast;
    /** Kalimat utama. Sebut apa yang terjadi, bukan sekadar "Berhasil". */
    judul: string;
    /** Keterangan tambahan bila judulnya saja belum cukup menjelaskan. */
    keterangan?: string;
}

interface Toast extends IsiToast {
    id: number;
    menutup: boolean;
}

interface KontrakToast {
    tampilkan: (isi: IsiToast) => number;
    tutup: (id: number) => void;
    /** Pintasan untuk pemakaian tersering. */
    sukses: (judul: string, keterangan?: string) => number;
    galat: (judul: string, keterangan?: string) => number;
}

const KonteksToast = createContext<KontrakToast | null>(null);

/**
 * Umpan balik singkat setelah sebuah aksi (`Arsitektur-Frontend.md` §komponen).
 *
 * Aturan yang dipegang di sini:
 *
 * 1. **Statusnya nyata, bukan hiasan.** Mockup di `scripts/BPMA DMS UI/`
 *    memakai ikon centang hijau yang sama untuk "disetujui" maupun "ditolak".
 *    Itu sengaja tidak diikuti: warna dan ikon adalah cara tercepat orang
 *    menangkap hasil sebuah aksi, dan menyamakan keduanya justru membuat
 *    penolakan terbaca sebagai keberhasilan.
 *
 * 2. **Waktunya konsisten.** Semua status tampil lima detik agar ritmenya
 *    mudah dipahami; pengguna tetap dapat menahan atau menutupnya sendiri.
 *
 * 3. **Hitungan mundur berhenti saat disentuh.** Menggerakkan tetikus ke arah
 *    tombol tutup, atau membaca pelan-pelan, tidak boleh membuat pesannya
 *    kabur tepat sebelum sempat dibaca.
 */
export function ToastProvider({ children, posisi = 'auth' }: { children: ReactNode; posisi?: 'auth' | 'portal' }) {
    const [daftar, setDaftar] = useState<Toast[]>([]);
    const nomor = useRef(0);

    const tutup = useCallback((id: number) => {
        setDaftar((s) => s.map((t) => t.id === id ? { ...t, menutup: true } : t));
    }, []);

    const hapus = useCallback((id: number) => {
        setDaftar((s) => s.filter((t) => t.id !== id));
    }, []);

    const tampilkan = useCallback((isi: IsiToast) => {
        const id = ++nomor.current;

        setDaftar((s) => {
            // Tumpukan dibatasi supaya rentetan aksi cepat tidak menutupi
            // seluruh layar. Yang terlama disingkirkan lebih dulu.
            const berikutnya = [...s, { ...isi, id, menutup: false }];

            return berikutnya.slice(-4);
        });

        return id;
    }, []);

    useEffect(() => daftarkanPenerimaToast(tampilkan), [tampilkan]);

    const nilai = useMemo<KontrakToast>(
        () => ({
            tampilkan,
            tutup,
            sukses: (judul, keterangan) =>
                tampilkan({ status: 'success', judul, keterangan }),
            galat: (judul, keterangan) =>
                tampilkan({ status: 'error', judul, keterangan }),
        }),
        [tampilkan, tutup],
    );

    return (
        <KonteksToast.Provider value={nilai}>
            {children}

            {/*
              * Satu wadah berposisi, dua wilayah live di dalamnya.
              *
              * Wilayahnya dipisah karena pembaca layar memperlakukan keduanya
              * berbeda: `assertive` menyela pengguna, `polite` menunggu jeda —
              * kegagalan layak menyela, keberhasilan tidak.
              *
              * Tapi keduanya TIDAK boleh sama-sama berposisi `fixed` di titik
              * yang sama, karena saat galat dan sukses muncul bersamaan yang
              * satu akan menimpa yang lain. Karena itu hanya wadah luarnya yang
              * berposisi, dan kedua wilayah di dalamnya menumpuk wajar.
              */}
            <div
                className={cn(
                    'pointer-events-none fixed inset-x-0 z-[60] flex flex-col gap-2 px-3',
                    posisi === 'portal'
                        ? 'top-14 sm:inset-x-auto sm:right-0 sm:top-16 sm:w-full sm:max-w-sm sm:px-4'
                        : 'top-3 sm:inset-x-auto sm:right-0 sm:top-4 sm:w-full sm:max-w-sm sm:px-4',
                )}
            >
                <Wilayah
                    daftar={daftar.filter((t) => t.status === 'error')}
                    tutup={tutup}
                    hapus={hapus}
                    tegas
                />
                <Wilayah daftar={daftar.filter((t) => t.status !== 'error')} tutup={tutup} hapus={hapus} />
            </div>
        </KonteksToast.Provider>
    );
}

export function useToast(): KontrakToast {
    const konteks = useContext(KonteksToast);

    if (konteks === null) {
        throw new Error('useToast harus dipakai di dalam <ToastProvider>.');
    }

    return konteks;
}

function Wilayah({
    daftar,
    tutup,
    hapus,
    tegas = false,
}: {
    daftar: Toast[];
    tutup: (id: number) => void;
    hapus: (id: number) => void;
    tegas?: boolean;
}) {
    return (
        <div
            role={tegas ? 'alert' : 'status'}
            aria-live={tegas ? 'assertive' : 'polite'}
            aria-atomic="false"
            /* `pointer-events-none` pada wadah supaya area kosong di sekitar
               toast tidak menghalangi klik ke halaman di baliknya; tiap kartu
               mengaktifkannya kembali untuk dirinya sendiri. */
            className="flex flex-col items-stretch gap-2 empty:hidden sm:items-end"
        >
            {/* Dibalik supaya yang TERBARU berada paling atas. Toast lama
                terdorong ke bawah, bukan menutupi yang baru muncul — pesan
                terakhirlah yang sedang ditunggu pengguna. */}
            {[...daftar].reverse().map((toast) => (
                <KartuToast key={toast.id} toast={toast} onTutup={() => tutup(toast.id)} onHapus={() => hapus(toast.id)} />
            ))}
        </div>
    );
}

/*
 * Rupa dan sebutan tiap status, dikumpulkan di satu tempat.
 *
 * `label` adalah kategori yang tampil di atas pesan. Gunanya bukan hiasan:
 * warna saja tidak terbaca oleh sekitar 8% laki-laki yang buta warna merah-hijau,
 * dan bagi mereka toast berhasil dan gagal tampak serupa. Sebutan tertulis
 * membuat statusnya terbaca tanpa bergantung pada warna sama sekali.
 */
const GAYA: Record<
    StatusToast,
    {
        icon: LucideIcon;
        warnaIkon: string;
        warnaLabel: string;
        latarBilah: string;
        latarIkon: string;
        garis: string;
    }
> = {
    success: {
        icon: CircleCheck,
        warnaIkon: 'text-success',
        warnaLabel: 'text-success-strong',
        latarBilah: 'bg-success',
        latarIkon: 'bg-success-soft',
        garis: 'border-l-success',
    },
    error: {
        icon: CircleX,
        warnaIkon: 'text-danger',
        warnaLabel: 'text-danger-strong',
        latarBilah: 'bg-danger',
        latarIkon: 'bg-danger-soft',
        garis: 'border-l-danger',
    },
    warning: {
        icon: CircleAlert,
        warnaIkon: 'text-warning',
        warnaLabel: 'text-warning-strong',
        latarBilah: 'bg-warning',
        latarIkon: 'bg-warning-soft',
        garis: 'border-l-warning',
    },
    info: {
        icon: Info,
        warnaIkon: 'text-brand-700',
        warnaLabel: 'text-brand-700',
        latarBilah: 'bg-brand-700',
        latarIkon: 'bg-brand-50',
        garis: 'border-l-brand-700',
    },
};

/**
 * Lama tampil bawaan: lima detik untuk semua status.
 *
 * Semua status memakai durasi yang sama supaya pola umpan baliknya konsisten.
 */
const DURASI_TOAST = 5000;

function KartuToast({ toast, onTutup, onHapus }: { toast: Toast; onTutup: () => void; onHapus: () => void }) {
    const { t } = useTranslation('common');
    const { icon: Icon, warnaIkon, warnaLabel, latarBilah, latarIkon, garis } =
        GAYA[toast.status];
    const label = t(`toastStatus.${toast.status}`);
    const durasi = DURASI_TOAST;

    const [tertahan, setTertahan] = useState(false);
    const adaKeterangan = toast.keterangan !== undefined && toast.keterangan !== '';

    /*
     * Perataan mengikuti JUMLAH BARIS yang benar-benar terender, bukan sekadar
     * ada tidaknya keterangan.
     *
     * Judul panjang dapat membungkus menjadi dua baris di layar sempit padahal
     * keterangannya kosong — dan sebaliknya, toast berketerangan pendek bisa
     * muat sebaris di layar lebar. Menebak dari isi datanya saja membuat
     * perataannya salah tiap kali lebar layar berubah, jadi tingginya diukur
     * langsung dan diamati lewat `ResizeObserver`.
     */
    const teksRef = useRef<HTMLDivElement>(null);
    const [banyakBaris, setBanyakBaris] = useState(adaKeterangan);

    useEffect(() => {
        const elemen = teksRef.current;

        if (elemen === null) return;

        function ukur(): void {
            const judul = elemen?.firstElementChild;

            if (!(elemen instanceof HTMLElement) || !(judul instanceof HTMLElement)) return;

            const tinggiBaris = Number.parseFloat(
                window.getComputedStyle(judul).lineHeight,
            );

            if (!Number.isFinite(tinggiBaris) || tinggiBaris <= 0) return;

            // Ambang 1,5 baris: cukup longgar untuk pembulatan sub-piksel,
            // cukup ketat untuk membedakan satu baris dari dua.
            setBanyakBaris(elemen.offsetHeight > tinggiBaris * 1.5);
        }

        ukur();

        const pengamat = new ResizeObserver(ukur);
        pengamat.observe(elemen);

        return () => pengamat.disconnect();
    }, [toast.judul, toast.keterangan]);

    /*
     * `onTutup` disimpan di ref, dan sengaja TIDAK menjadi dependensi efek di
     * bawah.
     *
     * Fungsi itu dibuat ulang pada setiap render induknya. Bila ia ikut jadi
     * dependensi, hitungan mundur ter-reset setiap kali daftar toast berubah —
     * sehingga toast yang muncul bersamaan justru menutup berurutan (yang
     * pertama di detik 5, berikutnya detik 10, lalu 15) alih-alih bersama-sama
     * di detik 5.
     */
    const tutupRef = useRef(onTutup);

    useEffect(() => {
        tutupRef.current = onTutup;
    });

    useEffect(() => {
        if (toast.menutup || tertahan) return;

        const pewaktu = window.setTimeout(() => tutupRef.current(), durasi);

        return () => window.clearTimeout(pewaktu);
    }, [durasi, tertahan, toast.menutup]);

    useEffect(() => {
        if (! toast.menutup) return;

        const pewaktu = window.setTimeout(onHapus, 180);

        return () => window.clearTimeout(pewaktu);
    }, [onHapus, toast.menutup]);

    return (
        <div
            onMouseEnter={() => setTertahan(true)}
            onMouseLeave={() => setTertahan(false)}
            onFocusCapture={() => setTertahan(true)}
            onBlurCapture={() => setTertahan(false)}
            className={cn(
                'pointer-events-auto relative flex w-full gap-3 overflow-hidden rounded-card border border-l-4 border-line bg-surface p-3 shadow-pop sm:max-w-sm',
                // Sebaris: semuanya rata tengah. Dua baris atau lebih: rata
                // atas, supaya ikon sejajar dengan baris judul dan tidak
                // melayang di tengah paragraf.
                banyakBaris ? 'items-start' : 'items-center',
                // `motion-safe` membuat animasinya dilewati sepenuhnya bila
                // sistem pengguna meminta gerak dikurangi.
                toast.menutup ? 'pointer-events-none motion-safe:animate-toast-keluar' : 'motion-safe:animate-toast-masuk',
                garis,
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-full',
                    latarIkon,
                )}
            >
                <Icon className={cn('size-5', warnaIkon)} />
            </span>

            <div ref={teksRef} className="min-w-0 flex-1">
                <p
                    className={cn(
                        'text-[0.6875rem] font-semibold uppercase leading-4 tracking-wider',
                        warnaLabel,
                    )}
                >
                    {label}
                </p>

                <p className="mt-0.5 text-sm text-ink">{toast.judul}</p>
                {adaKeterangan && (
                    <p className="mt-1 text-sm text-ink-muted">{toast.keterangan}</p>
                )}
            </div>

            <button
                type="button"
                onClick={onTutup}
                aria-label={t('ui.tutupPemberitahuan')}
                className="-mr-1 flex size-8 shrink-0 items-center justify-center rounded text-ink-subtle transition-colors hover:bg-surface-sunken hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-700"
            >
                <X className="size-4" aria-hidden />
            </button>

            {/* Bilah hitung mundur: menunjukkan sisa waktu sebelum toast
                menutup sendiri. Tanpa penanda ini, hilangnya pesan terasa
                mendadak dan pengguna tidak tahu ia sempat terlewat atau tidak.
                Animasinya ikut berhenti saat kartu disentuh, seiring pewaktunya. */}
            <span
                aria-hidden
                className={cn(
                    'absolute inset-x-0 bottom-0 block h-0.5 origin-left',
                    latarBilah,
                )}
                style={{
                    animation: `toast-mundur ${durasi}ms linear forwards`,
                    animationPlayState: tertahan ? 'paused' : 'running',
                }}
            />
        </div>
    );
}

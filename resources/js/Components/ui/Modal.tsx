import { IconButton } from '@/Components/ui/IconButton';
import { cn } from '@/lib/cn';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { X } from 'lucide-react';
import { type ReactNode } from 'react';

export interface ModalProps {
    terbuka: boolean;
    onTutup: (terbuka: boolean) => void;
    judul: string;
    keterangan?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    /** Aksi kontekstual di dekat tombol tutup, mis. salin isi teks. */
    aksiHeader?: ReactNode;
    /** Dialog konfirmasi memakai hierarki terpusat; formulir tetap rata kiri. */
    teksTerpusat?: boolean;
    className?: string;
    contentClassName?: string;
}

/**
 * Dialog standar aplikasi.
 *
 * Tingginya sengaja tetap besar sejak dibuka. Modal kecil yang kemudian
 * membesar saat isi dimuat membuat posisi fokus dan konteks visual pengguna
 * bergeser; satu kanvas baca yang konsisten lebih mudah dipindai.
 */
export function Modal({
    terbuka,
    onTutup,
    judul,
    keterangan,
    children,
    footer,
    aksiHeader,
    teksTerpusat = false,
    className,
    contentClassName,
}: ModalProps) {
    return (
        <Dialog open={terbuka} onClose={onTutup} className="relative z-[70]">
            <div className="fixed inset-0 bg-ink/40" aria-hidden />

            <div className="fixed inset-0 flex items-end justify-center p-4 sm:items-center">
                <DialogPanel
                    className={cn(
                        'flex max-h-[calc(100dvh-2rem)] w-full max-w-3xl flex-col rounded-card bg-surface shadow-pop',
                        className,
                    )}
                >
                    <div className={cn('flex items-start gap-4 border-b border-line px-5 py-4', teksTerpusat ? 'justify-center text-center' : 'justify-between')}>
                        <div className="min-w-0">
                            <DialogTitle className="text-base font-semibold text-ink">{judul}</DialogTitle>
                            {keterangan && <div className="mt-1 text-sm text-ink-muted">{keterangan}</div>}
                        </div>
                        <div className="flex shrink-0 items-center gap-1">
                            {aksiHeader}
                            <IconButton
                                icon={X}
                                label={`Tutup ${judul}`}
                                variant="ghost"
                                className={teksTerpusat ? 'absolute right-4 top-3' : undefined}
                                onClick={() => onTutup(false)}
                            />
                        </div>
                    </div>

                    <div className={cn('min-h-0 flex-1 overflow-auto p-5', teksTerpusat && 'text-center', contentClassName)}>{children}</div>

                    {footer && (
                        <div className={cn('flex flex-col-reverse gap-2 border-t border-line px-5 py-4 sm:flex-row', teksTerpusat ? 'sm:justify-center' : 'sm:justify-end')}>
                            {footer}
                        </div>
                    )}
                </DialogPanel>
            </div>
        </Dialog>
    );
}

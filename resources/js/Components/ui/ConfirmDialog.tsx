import { Button } from '@/Components/ui/Button';
import { cn } from '@/lib/cn';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { TriangleAlert, type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

export interface ConfirmDialogProps {
    terbuka: boolean;
    onTutup: () => void;
    onSetuju: () => void;
    judul: string;
    /** Penjelasan akibat tindakan ini. Sebut akibatnya, bukan sekadar "Anda yakin?". */
    children: ReactNode;
    labelSetuju: string;
    labelBatal?: string;
    ikon?: LucideIcon;
    /** `danger` untuk tindakan merusak; `brand` untuk tindakan biasa. */
    nada?: 'danger' | 'brand';
    memproses?: boolean;
}

/**
 * Dialog konfirmasi untuk tindakan yang tidak sepele.
 *
 * Isinya sengaja menuntut penjelasan, bukan sekadar "Anda yakin?". Pertanyaan
 * seperti itu tidak menambah informasi apa pun — pengguna sudah tahu ia menekan
 * tombol. Yang belum ia ketahui adalah AKIBATNYA, dan itulah yang harus tertulis
 * di sini: apa yang hilang, apa yang tetap, dan apakah masih dapat dibatalkan.
 *
 * Fokus dijebak di dalam dialog dan `Esc` menutupnya — keduanya bawaan
 * Headless UI, dan keduanya wajib: dialog yang tidak dapat ditutup dengan
 * papan ketik menjebak pengguna yang tidak memakai tetikus.
 */
export function ConfirmDialog({
    terbuka,
    onTutup,
    onSetuju,
    judul,
    children,
    labelSetuju,
    labelBatal = 'Batal',
    ikon: Ikon = TriangleAlert,
    nada = 'danger',
    memproses = false,
}: ConfirmDialogProps) {
    return (
        <Dialog open={terbuka} onClose={onTutup} className="relative z-[70]">
            <div className="fixed inset-0 bg-ink/40" aria-hidden />

            <div className="fixed inset-0 flex items-end justify-center p-4 sm:items-center">
                <DialogPanel className="w-full max-w-md rounded-card bg-white p-5 shadow-pop">
                    <div className="flex gap-3">
                        <span
                            aria-hidden
                            className={cn(
                                'flex size-10 shrink-0 items-center justify-center rounded-full',
                                nada === 'danger' ? 'bg-danger-soft' : 'bg-brand-50',
                            )}
                        >
                            <Ikon
                                className={cn(
                                    'size-5',
                                    nada === 'danger' ? 'text-danger' : 'text-brand-700',
                                )}
                            />
                        </span>

                        <div className="min-w-0 flex-1">
                            <DialogTitle className="text-base font-semibold text-ink">
                                {judul}
                            </DialogTitle>
                            <div className="mt-1.5 space-y-2 text-sm text-ink-muted">
                                {children}
                            </div>
                        </div>
                    </div>

                    {/* Di ponsel tombolnya bertumpuk dan melebar penuh; berjajar
                        di layar sempit membuat keduanya terlalu kecil untuk
                        disentuh dengan tepat. */}
                    <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={onTutup}
                            disabled={memproses}
                        >
                            {labelBatal}
                        </Button>

                        <Button
                            type="button"
                            variant={nada === 'danger' ? 'danger' : 'primary'}
                            onClick={onSetuju}
                            loading={memproses}
                        >
                            {labelSetuju}
                        </Button>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    );
}

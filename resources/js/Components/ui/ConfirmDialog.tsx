import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';
import { cn } from '@/lib/cn';
import { TriangleAlert, type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

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
    labelBatal,
    ikon: Ikon = TriangleAlert,
    nada = 'danger',
    memproses = false,
}: ConfirmDialogProps) {
    const { t } = useTranslation('common');

    return (
        <Modal
            terbuka={terbuka}
            onTutup={onTutup}
            judul={judul}
            teksTerpusat
            className="max-w-xl"
            footer={
                <>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={onTutup}
                        disabled={memproses}
                        className="w-full sm:w-auto"
                    >
                        {labelBatal ?? t('aksi.batal')}
                    </Button>
                    <Button
                        type="button"
                        variant={nada === 'danger' ? 'danger' : 'primary'}
                        onClick={onSetuju}
                        loading={memproses}
                        className="w-full sm:w-auto"
                    >
                        {labelSetuju}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col items-center gap-3">
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
                <div className="w-full space-y-2 text-sm text-ink-muted">{children}</div>
            </div>
        </Modal>
    );
}

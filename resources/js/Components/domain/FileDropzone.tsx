import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { IconButton } from '@/Components/ui/IconButton';
import { cn } from '@/lib/cn';
import { formatUkuranBerkas } from '@/lib/format';
import { Camera, Upload, X } from 'lucide-react';
import { useRef, useState, type DragEvent } from 'react';
import { useTranslation } from 'react-i18next';

export interface FileDropzoneProps {
    berkas: File | null;
    onChange: (berkas: File | null) => void;
    /** Batas ukuran dalam kilobyte; null berarti tanpa batas. */
    batasKb: number | null;
    batasLabel: string;
    error?: string;
}

/**
 * Pemilih berkas dengan seret-lepas dan pemeriksaan ukuran di sisi klien.
 *
 * Pemeriksaan ukuran di sini bukan pengganti validasi server — itu tetap ada
 * dan tetap yang menentukan. Gunanya menghemat waktu pengguna: berkas 900 MB
 * yang melebihi batas akan ditolak seketika, alih-alih setelah menunggu
 * unggahan panjang yang sudah pasti gagal.
 */
export function FileDropzone({
    berkas,
    onChange,
    batasKb,
    batasLabel,
    error,
}: FileDropzoneProps) {
    const { t } = useTranslation('documentForm');
    const inputRef = useRef<HTMLInputElement>(null);
    const [seret, setSeret] = useState(false);
    const [galatLokal, setGalatLokal] = useState<string | null>(null);

    function terima(daftar: FileList | null) {
        const dipilih = daftar?.[0] ?? null;
        setGalatLokal(null);

        if (dipilih === null) {
            onChange(null);

            return;
        }

        if (batasKb !== null && dipilih.size > batasKb * 1024) {
            setGalatLokal(
                t('documentForm:unggahBerkas.galatUkuran', {
                    ukuran: formatUkuranBerkas(dipilih.size),
                    batas: batasLabel,
                }),
            );
            onChange(null);

            return;
        }

        onChange(dipilih);
    }

    function jatuhkan(e: DragEvent<HTMLDivElement>) {
        e.preventDefault();
        setSeret(false);
        terima(e.dataTransfer.files);
    }

    const pesan = galatLokal ?? error;

    if (berkas !== null) {
        return (
            <div className="space-y-2">
                <div className="flex items-center gap-3 rounded-card border border-line bg-surface p-3">
                    <FileTypeBadge mime={berkas.type || 'application/octet-stream'} namaBerkas={berkas.name} size="md" />

                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-ink">{berkas.name}</p>
                        <p className="font-mono text-xs text-ink-subtle">
                            {formatUkuranBerkas(berkas.size)}
                        </p>
                    </div>

                    <IconButton
                        icon={X}
                        label={t('documentForm:unggahBerkas.hapusBerkas')}
                        variant="danger"
                        size="sm"
                        onClick={() => {
                            onChange(null);
                            // Nilai input dikosongkan supaya memilih berkas yang
                            // sama persis setelah dihapus tetap memicu onChange.
                            if (inputRef.current) inputRef.current.value = '';
                        }}
                    />
                </div>

                {pesan && <p className="text-sm text-danger">{pesan}</p>}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div
                onDragOver={(e) => {
                    e.preventDefault();
                    setSeret(true);
                }}
                onDragLeave={() => setSeret(false)}
                onDrop={jatuhkan}
                className={cn(
                    'rounded-card border-2 border-dashed p-6 text-center transition-colors',
                    seret ? 'border-brand-700 bg-brand-50' : 'border-line bg-surface-sunken',
                    pesan && 'border-danger',
                )}
            >
                <Upload className="mx-auto size-8 text-ink-subtle" aria-hidden />

                <p className="mt-3 text-sm text-ink">
                    {t('documentForm:unggahBerkas.seretKeSini')}
                </p>

                <div className="mt-3 flex flex-wrap items-center justify-center gap-2">
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        className="inline-flex min-h-touch items-center gap-2 rounded-lg bg-brand-700 px-4 text-sm font-medium text-white transition-colors hover:bg-brand-800 sm:min-h-10"
                    >
                        <Upload className="size-4" aria-hidden />
                        {t('documentForm:unggahBerkas.pilihBerkas')}
                    </button>

                    {/* Ambil foto langsung dari kamera (FR-06b). Tombolnya hanya
                        muncul di layar kecil karena `capture` tidak berarti apa
                        pun di desktop. */}
                    <label className="inline-flex min-h-touch cursor-pointer items-center gap-2 rounded-lg border border-line bg-surface px-4 text-sm font-medium text-ink-muted transition-colors hover:bg-surface-sunken sm:hidden sm:min-h-10">
                        <Camera className="size-4" aria-hidden />
                        {t('documentForm:unggahBerkas.ambilFoto')}
                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            className="sr-only"
                            onChange={(e) => terima(e.target.files)}
                        />
                    </label>
                </div>

                <p className="mt-3 text-xs text-ink-subtle">
                    {t('documentForm:unggahBerkas.keterangan', { batas: batasLabel })}
                </p>
            </div>

            <input
                ref={inputRef}
                type="file"
                className="sr-only"
                aria-label={t('documentForm:unggahBerkas.ariaPilihBerkas')}
                onChange={(e) => terima(e.target.files)}
            />

            {pesan && <p className="text-sm text-danger">{pesan}</p>}
        </div>
    );
}

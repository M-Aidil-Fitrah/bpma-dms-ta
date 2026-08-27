import { Button } from '@/Components/ui/Button';
import { Download, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface CsvPreviewProps {
    dokumen: App.Data.DocumentDetailData;
}

interface CsvData {
    headers: string[];
    rows: string[][];
    truncated: boolean;
}

/** Pratinjau tabel CSV privat; semua sel dirender React sebagai teks biasa. */
export function CsvPreview({ dokumen }: CsvPreviewProps) {
    const { t } = useTranslation('documentBrowse');
    const [data, setData] = useState<CsvData | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        async function load() {
            try {
                const response = await fetch(`/documents/${dokumen.id}/csv-preview`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                const body = await response.json() as CsvData | { message?: string };

                if (!response.ok || !('headers' in body)) {
                    throw new Error('message' in body ? body.message : t('documentBrowse:preview.csvGagalDimuat'));
                }

                setData(body);
            } catch (caught) {
                if ((caught as DOMException).name !== 'AbortError') {
                    setError(caught instanceof Error ? caught.message : t('documentBrowse:preview.csvGagalDimuat'));
                }
            }
        }

        void load();

        return () => controller.abort();
    }, [dokumen.id, t]);

    if (error !== null) {
        return <CsvFallback dokumen={dokumen} pesan={error} />;
    }

    if (data === null) {
        return (
            <div className="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
                <Loader2 className="size-6 animate-spin text-ink-subtle" aria-hidden />
                <p className="text-sm text-ink-muted">{t('documentBrowse:preview.memuatCsv')}</p>
            </div>
        );
    }

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="border-b border-line bg-surface px-4 py-2 text-xs text-ink-muted">
                {data.truncated ? t('documentBrowse:preview.csvTerpotong') : t('documentBrowse:preview.csvTabel')}
            </div>
            <div className="min-h-0 flex-1 overflow-auto">
                <table className="min-w-full border-collapse text-left text-sm">
                    <thead className="sticky top-0 z-10 bg-surface-sunken text-xs font-semibold text-ink">
                        <tr>
                            {data.headers.map((header, index) => <th key={`${header}-${index}`} className="whitespace-nowrap border-b border-line px-3 py-2">{header}</th>)}
                        </tr>
                    </thead>
                    <tbody className="text-ink-muted">
                        {data.rows.map((row, rowIndex) => (
                            <tr key={rowIndex} className="border-b border-line last:border-0">
                                {data.headers.map((_, columnIndex) => (
                                    <td key={columnIndex} className="max-w-[24rem] whitespace-pre-wrap break-words px-3 py-2 align-top">
                                        {row[columnIndex] ?? ''}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function CsvFallback({ dokumen, pesan }: { dokumen: App.Data.DocumentDetailData; pesan: string }) {
    const { t } = useTranslation('documentBrowse');

    return (
        <div className="flex h-full flex-col items-center justify-center gap-4 p-8 text-center">
            <p className="max-w-md text-sm text-ink-muted">{pesan}</p>
            <a href={`/documents/${dokumen.id}/file`} download>
                <Button icon={Download}>{t('documentBrowse:preview.unduhBerkas')}</Button>
            </a>
        </div>
    );
}

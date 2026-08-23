import { ReferenceResourceActions, type ReferenceResourceKind } from '@/Components/domain/ReferenceResourceActions';
import { Badge } from '@/Components/ui/Badge';
import { useTranslation } from 'react-i18next';

interface ReferenceResourceCardsProps {
    jenis: ReferenceResourceKind;
    referensi: readonly App.Data.ReferensiListData[];
}

/** Versi ponsel daftar referensi — tanpa tabel yang harus digeser mendatar. */
export function ReferenceResourceCards({ jenis, referensi }: ReferenceResourceCardsProps) {
    const { t } = useTranslation('common');

    return (
        <ul className="divide-y divide-line lg:hidden">
            {referensi.map((item) => (
                <li key={item.id} className="px-4 py-3.5">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p
                                className="text-sm font-medium text-ink"
                                style={jenis === 'unit' ? { paddingLeft: `${item.kedalaman * 14}px` } : undefined}
                            >
                                {jenis === 'unit' && item.kedalaman > 0 && <span className="mr-1 text-ink-subtle">└</span>}
                                {item.nama}
                            </p>
                            {item.keterangan && <p className="mt-1 text-xs text-ink-muted">{item.keterangan}</p>}
                        </div>
                        <Badge variant={item.is_active ? 'success' : 'neutral'} size="sm">
                            {item.is_active ? t('status.aktif') : t('status.nonaktif')}
                        </Badge>
                    </div>
                    <div className="mt-2">
                        <ReferenceResourceActions
                            jenis={jenis}
                            id={item.id}
                            nama={item.nama}
                            aktif={item.is_active}
                            dampak={item.dampak_nonaktif}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

import { IconButton } from '@/Components/ui/IconButton';
import { Input } from '@/Components/ui/Input';
import { useDebounce } from '@/hooks/useDebounce';
import { cn } from '@/lib/cn';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

export interface SearchInputProps {
    value: string;
    onChange: (nilai: string) => void;
    placeholder?: string;
    className?: string;
}

/**
 * Kolom pencarian dengan penundaan.
 *
 * Nilai yang diketik disimpan lokal supaya kolomnya tetap responsif, lalu
 * diteruskan ke pemanggil setelah pengguna berhenti mengetik. Meneruskannya
 * setiap ketukan akan memicu satu permintaan per huruf.
 */
export function SearchInput({
    value,
    onChange,
    placeholder,
    className,
}: SearchInputProps) {
    const { t } = useTranslation('common');
    const placeholderAkhir = placeholder ?? t('ui.cariPlaceholder');
    const [lokal, setLokal] = useState(value);
    const ditunda = useDebounce(lokal, 300);
    const nilaiTerakhir = useRef(value);

    useEffect(() => {
        // Hanya kabarkan saat nilainya benar-benar berubah. Tanpa penjaga ini,
        // render pertama ikut mengirim permintaan padahal tidak ada yang
        // diketik.
        if (ditunda !== nilaiTerakhir.current) {
            nilaiTerakhir.current = ditunda;
            onChange(ditunda);
        }
    }, [ditunda, onChange]);

    // Menyelaraskan kembali bila nilainya diubah dari luar, misalnya saat
    // pengguna menekan "Bersihkan semua" pada bilah penyaring.
    useEffect(() => {
        if (value !== nilaiTerakhir.current) {
            nilaiTerakhir.current = value;
            setLokal(value);
        }
    }, [value]);

    return (
        <div className={className}>
            <div className="relative">
                <Input
                    type="search"
                    icon={Search}
                    value={lokal}
                    placeholder={placeholderAkhir}
                    aria-label={placeholderAkhir}
                    onChange={(e) => setLokal(e.target.value)}
                    className={cn(
                        // Tombol hapus bawaan peramban untuk `type="search"`
                        // disembunyikan: tanpa ini ada dua tanda silang
                        // berdampingan, dan yang bawaan tidak mengikuti gaya
                        // maupun ukuran target sentuh aplikasi.
                        '[&::-webkit-search-cancel-button]:appearance-none',
                        lokal && 'pr-10',
                    )}
                />

                {lokal && (
                    <IconButton
                        icon={X}
                        label={t('ui.bersihkanPencarian')}
                        variant="ghost"
                        size="sm"
                        onClick={() => setLokal('')}
                        className="absolute right-1 top-1/2 -translate-y-1/2"
                    />
                )}
            </div>
        </div>
    );
}

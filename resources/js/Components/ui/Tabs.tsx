import { cn } from '@/lib/cn';
import { type LucideIcon } from 'lucide-react';

export interface TabItem<T extends string> {
    value: T;
    label: string;
    icon: LucideIcon;
}

export interface TabsProps<T extends string> {
    items: readonly TabItem<T>[];
    value: T;
    onChange: (value: T) => void;
    label: string;
}

/** Tab navigasi ringkas untuk panel dengan isi yang saling eksklusif. */
export function Tabs<T extends string>({ items, value, onChange, label }: TabsProps<T>) {
    return (
        <div className="flex border-b border-line" role="tablist" aria-label={label}>
            {items.map(({ value: itemValue, label: itemLabel, icon: Icon }) => {
                const aktif = itemValue === value;

                return (
                    <button
                        key={itemValue}
                        type="button"
                        role="tab"
                        aria-selected={aktif}
                        onClick={() => onChange(itemValue)}
                        className={cn(
                            'flex min-h-touch flex-1 items-center justify-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors sm:min-h-0',
                            aktif
                                ? 'border-brand-700 text-brand-700'
                                : 'border-transparent text-ink-muted hover:text-ink',
                        )}
                    >
                        <Icon className="size-4" aria-hidden />
                        {itemLabel}
                    </button>
                );
            })}
        </div>
    );
}

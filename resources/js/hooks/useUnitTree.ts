import type { UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { useMemo, useState } from 'react';

/**
 * Susunan pohon dua tingkat (induk → anak) dari daftar unit datar, plus
 * status buka/tutup tiap cabang — dipakai bersama `UnitTreePicker` (cascade
 * multi-pilih) dan `UnitTreeSelect` (satu pilihan, untuk penyaring). Kedua
 * komponen sebelumnya menghitung ulang struktur pohon yang identik dengan
 * cara yang identik pula.
 */
export function useUnitTree(units: readonly UnitPilihan[]) {
    const [terbuka, setTerbuka] = useState<Set<number>>(new Set());

    const { induk, anakDari } = useMemo(() => {
        const induk = units.filter((u) => u.parent_id === null);
        const anakDari = new Map<number, UnitPilihan[]>();

        for (const unit of units) {
            if (unit.parent_id === null) continue;
            const daftar = anakDari.get(unit.parent_id) ?? [];
            daftar.push(unit);
            anakDari.set(unit.parent_id, daftar);
        }

        return { induk, anakDari };
    }, [units]);

    function toggleTerbuka(id: number) {
        setTerbuka((s) => {
            const n = new Set(s);
            if (n.has(id)) {
                n.delete(id);
            } else {
                n.add(id);
            }

            return n;
        });
    }

    return { induk, anakDari, terbuka, toggleTerbuka };
}

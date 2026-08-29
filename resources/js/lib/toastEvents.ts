import type { IsiToast } from '@/Components/ui/Toast';

type PenerimaToast = (isi: IsiToast) => void;

const penerima = new Set<PenerimaToast>();
const antrean: IsiToast[] = [];
const BATAS_ANTREAN = 10;

/**
 * Menyampaikan toast dari kode di luar pohon React, seperti event global
 * Inertia. Pesan yang muncul sebelum provider siap disimpan sebentar agar
 * kegagalan awal tidak hilang begitu saja.
 */
export function tampilkanToastGlobal(isi: IsiToast): void {
    if (penerima.size === 0) {
        antrean.push(isi);

        if (antrean.length > BATAS_ANTREAN) antrean.shift();

        return;
    }

    penerima.forEach((terima) => terima(isi));
}

/** Mendaftarkan jembatan dari event global ke provider toast React. */
export function daftarkanPenerimaToast(terima: PenerimaToast): () => void {
    penerima.add(terima);

    while (antrean.length > 0) {
        const isi = antrean.shift();

        if (isi !== undefined) terima(isi);
    }

    return () => penerima.delete(terima);
}

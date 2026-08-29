import { readdirSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

type JsonValue = string | number | boolean | null | JsonValue[] | { [key: string]: JsonValue };

function ratakan(nilai: JsonValue, awalan = ''): string[] {
    if (nilai === null || typeof nilai !== 'object' || Array.isArray(nilai)) {
        return [awalan];
    }

    return Object.entries(nilai).flatMap(([kunci, anak]) => ratakan(anak, awalan === '' ? kunci : `${awalan}.${kunci}`));
}

function bacaLocale(bahasa: 'id' | 'en'): Map<string, string[]> {
    const direktori = resolve(dirname(fileURLToPath(import.meta.url)), '..', 'lang', bahasa);

    return new Map(
        readdirSync(direktori)
            .filter((berkas) => berkas.endsWith('.json'))
            .sort()
            .map((berkas) => {
                const isi = JSON.parse(readFileSync(`${direktori}/${berkas}`, 'utf8')) as JsonValue;

                return [berkas, ratakan(isi).sort()];
            }),
    );
}

describe('kontrak locale', () => {
    it('memiliki berkas dan key Indonesia-Inggris yang simetris', () => {
        const indonesia = bacaLocale('id');
        const inggris = bacaLocale('en');

        expect([...indonesia.keys()]).toEqual([...inggris.keys()]);

        for (const [berkas, keysIndonesia] of indonesia) {
            expect(inggris.get(berkas)).toEqual(keysIndonesia);
        }
    });
});

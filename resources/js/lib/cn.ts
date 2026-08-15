import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Menggabungkan kelas Tailwind dengan aman.
 *
 * `clsx` menangani kelas bersyarat, `twMerge` menyelesaikan kelas yang saling
 * bertabrakan — sehingga prop `className` dari pemanggil selalu menang atas
 * kelas bawaan komponen. Tanpa ini, `<Button className="bg-danger">` tidak akan
 * berpengaruh karena kelas bawaannya muncul belakangan di berkas CSS.
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

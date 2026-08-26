export type JenisBerkas = 'pdf' | 'word' | 'excel' | 'ppt' | 'gambar' | 'video' | 'audio' | 'teks' | 'zip' | 'berkas';

const EKSTENSI_WORD = new Set(['doc', 'docx', 'docm', 'dot', 'dotx']);
const EKSTENSI_EXCEL = new Set(['csv', 'xls', 'xlsx', 'xlsm', 'xlsb', 'ods']);
const EKSTENSI_POWERPOINT = new Set(['ppt', 'pptx', 'pptm', 'pps', 'ppsx', 'odp']);

/**
 * Menentukan keluarga berkas untuk seluruh permukaan UI.
 *
 * MIME diprioritaskan karena tersedia pada berkas yang sudah tersimpan.
 * Ekstensi menjadi fallback untuk browser yang memberi
 * `application/octet-stream`, termasuk CSV yang sah disajikan sebagai tabel.
 */
export function jenisBerkas(mime: string, namaBerkas?: string): JenisBerkas {
    const tipe = mime.toLowerCase();
    const ekstensi = namaBerkas?.split('.').pop()?.toLowerCase() ?? '';

    if (tipe === 'application/pdf') return 'pdf';
    if (tipe.includes('wordprocessingml') || tipe.includes('msword')) return 'word';
    if (tipe.includes('spreadsheetml') || tipe.includes('ms-excel') || tipe.includes('csv')) return 'excel';
    if (tipe.includes('presentationml') || tipe.includes('ms-powerpoint')) return 'ppt';
    if (tipe.startsWith('image/')) return 'gambar';
    if (tipe.startsWith('video/')) return 'video';
    if (tipe.startsWith('audio/')) return 'audio';
    if (tipe.includes('zip') || tipe.includes('compressed') || tipe.includes('tar')) return 'zip';

    if (EKSTENSI_EXCEL.has(ekstensi)) return 'excel';
    if (EKSTENSI_WORD.has(ekstensi)) return 'word';
    if (EKSTENSI_POWERPOINT.has(ekstensi)) return 'ppt';
    if (tipe === 'text/plain') return 'teks';

    return 'berkas';
}

import { UnitTreePicker, type UnitPilihan } from '@/Components/domain/UnitTreePicker';
import { UserPicker, type PenggunaTerpilih } from '@/Components/domain/UserPicker';
import { Alert } from '@/Components/ui/Alert';
import { Select } from '@/Components/ui/Select';
import { cn } from '@/lib/cn';
import { Check, Globe, ShieldCheck, TriangleAlert, Users } from 'lucide-react';
import { type ReactNode } from 'react';

export interface NilaiAkses {
    is_shared_to_all: boolean;
    min_tingkat_akses: number | null;
    unit_ids: number[];
    shared_users: PenggunaTerpilih[];
}

/** Satu jenjang jabatan beserta isinya, dikirim server. */
export interface JenjangJabatan {
    tingkat: number;
    jabatan: { nama: string; jumlah: number }[];
    jumlah: number;
}

export interface AccessMechanismPickerProps {
    nilai: NilaiAkses;
    onChange: (nilai: NilaiAkses) => void;
    units: readonly UnitPilihan[];
    jenjang: readonly JenjangJabatan[];
    error?: string;
}

/**
 * Pengatur empat mekanisme akses dokumen.
 *
 * Ini wujud antarmuka dari pembeda utama produk (`PRD.md` §1.3), dan dua hal
 * padanya tidak boleh keliru:
 *
 * 1. **Berbentuk checklist, bukan tombol radio.** Keempat mekanisme dapat aktif
 *    bersamaan; dokumen terlihat bila salah satu saja terpenuhi. Memakai radio
 *    akan memaksa pengunggah mengunggah dokumen yang sama berkali-kali untuk
 *    tiap sasaran — persis masalah yang produk ini hendak selesaikan.
 *
 * 2. **Pratinjau ditampilkan sebelum disimpan.** Tanpa itu, pengunggah harus
 *    menebak akibat dari kombinasi yang ia pilih — dan menebak adalah cara
 *    dokumen berakhir salah bagi.
 */
export function AccessMechanismPicker({
    nilai,
    onChange,
    units,
    jenjang,
    error,
}: AccessMechanismPickerProps) {
    function ubah(sebagian: Partial<NilaiAkses>) {
        onChange({ ...nilai, ...sebagian });
    }

    const jumlahAktif =
        (nilai.is_shared_to_all ? 1 : 0) +
        (nilai.min_tingkat_akses !== null ? 1 : 0) +
        (nilai.unit_ids.length > 0 ? 1 : 0) +
        (nilai.shared_users.length > 0 ? 1 : 0);

    return (
        <div className="space-y-3">
            {error && (
                <Alert variant="danger" title="Mekanisme akses belum diatur">
                    {error}
                </Alert>
            )}

            <Mekanisme
                aktif={nilai.is_shared_to_all}
                onToggle={() => ubah({ is_shared_to_all: !nilai.is_shared_to_all })}
                icon={Globe}
                judul="Bagikan ke semua"
                keterangan="Seluruh pengguna internal dapat melihat dokumen ini."
            />

            <Mekanisme
                aktif={nilai.min_tingkat_akses !== null}
                onToggle={() =>
                    ubah({
                        min_tingkat_akses:
                            nilai.min_tingkat_akses === null
                                ? (jenjang[1]?.tingkat ?? jenjang[0]?.tingkat ?? null)
                                : null,
                    })
                }
                icon={ShieldCheck}
                judul="Bagikan ke jabatan tertentu ke atas"
                keterangan="Berdasarkan jenjang jabatan, berlaku di semua unit."
            >
                <JenjangPicker
                    jenjang={jenjang}
                    terpilih={nilai.min_tingkat_akses}
                    onChange={(tingkat) => ubah({ min_tingkat_akses: tingkat })}
                />
            </Mekanisme>

            <Mekanisme
                aktif={nilai.unit_ids.length > 0}
                icon={Users}
                judul="Bagikan ke unit"
                keterangan={
                    nilai.unit_ids.length > 0
                        ? `${nilai.unit_ids.length} unit dipilih.`
                        : 'Pilih unit kerja yang berhak melihat.'
                }
                selaluTerbuka
            >
                <UnitTreePicker
                    units={units}
                    terpilih={nilai.unit_ids}
                    onChange={(unit_ids) => ubah({ unit_ids })}
                />
            </Mekanisme>

            <Mekanisme
                aktif={nilai.shared_users.length > 0}
                icon={Users}
                judul="Bagikan ke orang tertentu"
                keterangan={
                    nilai.shared_users.length > 0
                        ? `${nilai.shared_users.length} orang dipilih.`
                        : 'Cari orang berdasarkan nama, lintas unit dan jabatan.'
                }
                selaluTerbuka
            >
                <UserPicker
                    terpilih={nilai.shared_users}
                    onChange={(shared_users) => ubah({ shared_users })}
                />
            </Mekanisme>

            <Pratinjau
                nilai={nilai}
                jumlahAktif={jumlahAktif}
                units={units}
                jenjang={jenjang}
            />
        </div>
    );
}

/**
 * Pemilih jenjang jabatan yang menyebutkan akibatnya secara harfiah.
 *
 * Sebelumnya pilihan ini hanya berbunyi "Tingkat 2 ke atas". Kalimat itu
 * menuntut pengunggah mengingat sendiri jabatan apa saja yang duduk di tingkat
 * 2 — dan menebak salah berarti dokumen terbuka bagi pihak yang tidak
 * semestinya. Di sini tiap pilihan menyebut nama jabatannya, lalu di bawahnya
 * dirinci siapa yang termasuk dan siapa yang tidak, lengkap dengan jumlah orang
 * yang sesungguhnya.
 */
function JenjangPicker({
    jenjang,
    terpilih,
    onChange,
}: {
    jenjang: readonly JenjangJabatan[];
    terpilih: number | null;
    onChange: (tingkat: number) => void;
}) {
    const termasuk = jenjang.filter((j) => terpilih !== null && j.tingkat <= terpilih);
    const diluar = jenjang.filter((j) => terpilih !== null && j.tingkat > terpilih);
    const total = termasuk.reduce((jumlah, j) => jumlah + j.jumlah, 0);

    return (
        <>
            <Select
                aria-label="Jabatan minimum yang dapat melihat"
                value={terpilih ?? ''}
                onChange={(e) => onChange(Number(e.target.value))}
                options={jenjang.map((j) => ({
                    value: j.tingkat,
                    label: `${namaJenjang(j)} ke atas`,
                }))}
            />

            {terpilih !== null && (
                <div className="mt-2.5 space-y-2 rounded-lg bg-surface-sunken p-3">
                    <p className="text-xs font-semibold text-ink">
                        {total} orang akan dapat melihat dokumen ini:
                    </p>

                    <ul className="space-y-1">
                        {termasuk.map((j) => (
                            <li
                                key={j.tingkat}
                                className="flex items-start gap-1.5 text-xs text-ink-muted"
                            >
                                <Check
                                    className="mt-0.5 size-3 shrink-0 text-success"
                                    aria-hidden
                                />
                                <span>
                                    {j.jabatan
                                        .map((p) => `${p.nama} (${p.jumlah})`)
                                        .join(', ')}
                                </span>
                            </li>
                        ))}
                    </ul>

                    {diluar.length > 0 && (
                        <p className="border-t border-line pt-2 text-xs text-ink-subtle">
                            <span className="font-medium">Tidak termasuk:</span>{' '}
                            {diluar
                                .flatMap((j) => j.jabatan.map((p) => p.nama))
                                .join(', ')}
                            .
                        </p>
                    )}
                </div>
            )}
        </>
    );
}

/**
 * Ringkasan satu baris untuk panel pratinjau, mis. "7 orang berjabatan Kepala
 * BPMA, Wakil Kepala BPMA, Deputi, Sekretaris".
 */
function ringkasJenjang(
    jenjang: readonly JenjangJabatan[],
    tingkat: number,
): string {
    const termasuk = jenjang.filter((j) => j.tingkat <= tingkat);
    const jumlah = termasuk.reduce((total, j) => total + j.jumlah, 0);
    const nama = termasuk.flatMap((j) => j.jabatan.map((p) => p.nama));

    return `${jumlah} orang berjabatan ${nama.join(', ')}`;
}

/**
 * Nama jenjang apa adanya, mis. "Deputi & Sekretaris".
 *
 * Angka tingkat sengaja tidak ikut ditampilkan: ia nomor internal basis data,
 * dan menampilkannya hanya menambah satu hal lagi yang harus diterjemahkan
 * pembaca.
 */
function namaJenjang(j: JenjangJabatan): string {
    const nama = j.jabatan.map((p) => p.nama);

    if (nama.length <= 2) return nama.join(' & ');

    return `${nama.slice(0, -1).join(', ')} & ${nama[nama.length - 1]}`;
}

function Mekanisme({
    aktif,
    onToggle,
    icon: Icon,
    judul,
    keterangan,
    children,
    selaluTerbuka = false,
}: {
    aktif: boolean;
    /**
     * Tidak dipakai (dan tidak perlu diisi) untuk mekanisme `selaluTerbuka`
     * seperti "Bagikan ke unit"/"Bagikan ke orang tertentu" — isinya sudah
     * selalu tampil terlepas status aktif, dan status aktifnya sendiri
     * berubah otomatis begitu pemilih di dalamnya memilih/melepas sesuatu,
     * bukan lewat klik header ini.
     */
    onToggle?: () => void;
    icon: typeof Globe;
    judul: string;
    keterangan: string;
    children?: ReactNode;
    /** Isi tetap terlihat walau mekanismenya belum aktif — untuk pemilih. */
    selaluTerbuka?: boolean;
}) {
    return (
        <div
            className={cn(
                'rounded-card border transition-colors',
                aktif ? 'border-brand-300 bg-brand-50/40' : 'border-line bg-surface',
            )}
        >
            <button
                type="button"
                onClick={selaluTerbuka ? undefined : onToggle}
                aria-pressed={aktif}
                className={cn(
                    'flex w-full items-start gap-3 p-3 text-left',
                    selaluTerbuka ? 'cursor-default' : 'cursor-pointer',
                )}
            >
                <span
                    aria-hidden
                    className={cn(
                        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border',
                        aktif ? 'border-brand-700 bg-brand-700 text-white' : 'border-line',
                    )}
                >
                    {aktif && <Check className="size-3.5" />}
                </span>

                <Icon
                    className={cn(
                        'mt-0.5 size-4 shrink-0',
                        aktif ? 'text-brand-700' : 'text-ink-subtle',
                    )}
                    aria-hidden
                />

                <span className="min-w-0 flex-1">
                    <span
                        className={cn(
                            'block text-sm font-medium',
                            aktif ? 'text-brand-700' : 'text-ink',
                        )}
                    >
                        {judul}
                    </span>
                    <span className="mt-0.5 block text-xs text-ink-muted">{keterangan}</span>
                </span>
            </button>

            {children && (aktif || selaluTerbuka) && (
                <div className="border-t border-line/70 p-3">{children}</div>
            )}
        </div>
    );
}

/**
 * Menunjukkan akibat dari kombinasi yang dipilih, sebelum disimpan.
 */
function Pratinjau({
    nilai,
    jumlahAktif,
    units,
    jenjang,
}: {
    nilai: NilaiAkses;
    jumlahAktif: number;
    units: readonly UnitPilihan[];
    jenjang: readonly JenjangJabatan[];
}) {
    if (jumlahAktif === 0) {
        const jabatanTertinggi = jenjang[0]?.jabatan.map(({ nama }) => nama).join(' dan ') ?? 'Pimpinan BPMA';

        return (
            <Alert variant="warning" title="Belum ada mekanisme akses yang aktif">
                Dokumen ini hanya akan terlihat oleh Anda sendiri, Superadmin, dan
                {' '}{jabatanTertinggi}. Aktifkan minimal satu mekanisme di atas.
            </Alert>
        );
    }

    if (nilai.is_shared_to_all) {
        return (
            <Alert variant="info" title="Dokumen ini akan terlihat semua orang">
                Mekanisme lain yang aktif tidak menambah apa pun — semua pengguna
                internal sudah dapat melihatnya lewat mekanisme &ldquo;bagikan ke
                semua&rdquo;.
            </Alert>
        );
    }

    const namaUnit = units
        .filter((u) => nilai.unit_ids.includes(u.id))
        .map((u) => u.nama);

    return (
        <div className="rounded-card border border-brand-200 bg-brand-50 p-4">
            <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-brand-700">
                <TriangleAlert className="size-3.5" aria-hidden />
                Yang akan dapat melihat dokumen ini
            </p>

            <ul className="mt-2 space-y-1.5 text-sm text-ink">
                {nilai.min_tingkat_akses !== null && (
                    <li>
                        {ringkasJenjang(jenjang, nilai.min_tingkat_akses)}, di unit mana
                        pun
                    </li>
                )}
                {namaUnit.length > 0 && (
                    <li>Anggota {namaUnit.length} unit: {namaUnit.join(', ')}</li>
                )}
                {nilai.shared_users.length > 0 && (
                    <li>
                        {nilai.shared_users.length} orang tertentu:{' '}
                        {nilai.shared_users.map((p) => p.nama).join(', ')}
                    </li>
                )}
                <li className="text-ink-muted">
                    Anda sendiri sebagai pengunggah, Superadmin, dan jabatan tingkat
                    tertinggi
                </li>
            </ul>
        </div>
    );
}

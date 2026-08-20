import { Button } from '@/Components/ui/Button';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Link, router } from '@inertiajs/react';
import { ArchiveRestore, Download, EyeOff, Pencil } from 'lucide-react';
import { useState } from 'react';

export interface DocumentHeaderActionsProps {
    dokumenId: number;
    judul: string;
    aktif: boolean;
    bolehUbah: boolean;
    bolehNonaktifkan: boolean;
    /** Hanya Superadmin yang dapat mengaktifkan kembali (FR-10). */
    bolehAktifkan: boolean;
}

/**
 * Aksi pada bilah atas halaman detail dokumen (FR-08, FR-09, FR-10).
 *
 * Tombol yang tidak boleh dipakai tidak dirender sama sekali — tapi itu semata
 * demi kerapian, bukan pengamanan. Setiap aksi di baliknya tetap diperiksa
 * Policy di server, karena alamatnya dapat dipanggil langsung tanpa melewati
 * antarmuka ini sama sekali (FR-43).
 */
export function DocumentHeaderActions({
    dokumenId,
    judul,
    aktif,
    bolehUbah,
    bolehNonaktifkan,
    bolehAktifkan,
}: DocumentHeaderActionsProps) {
    const konfirmasikan = usePasswordConfirmation();
    const [tanyaNonaktif, setTanyaNonaktif] = useState(false);
    const [memproses, setMemproses] = useState(false);

    function nonaktifkan() {
        konfirmasikan(jalankanNonaktifkan);
    }

    function jalankanNonaktifkan() {
        setMemproses(true);
        router.delete(`/documents/${dokumenId}`, {
            onFinish: () => {
                setMemproses(false);
                setTanyaNonaktif(false);
            },
        });
    }

    function aktifkan() {
        konfirmasikan(jalankanAktifkan);
    }

    function jalankanAktifkan() {
        setMemproses(true);
        router.patch(
            `/documents/${dokumenId}/restore`,
            {},
            { onFinish: () => setMemproses(false) },
        );
    }

    return (
        <>
            <a href={`/documents/${dokumenId}/file`} download>
                <Button icon={Download} size="sm" variant="secondary">
                    <span className="hidden md:inline">Unduh</span>
                    <span className="sr-only md:hidden">Unduh</span>
                </Button>
            </a>

            {bolehUbah && aktif && (
                <Link href={`/documents/${dokumenId}/edit`}>
                    <Button icon={Pencil} size="sm">
                    <span className="hidden md:inline">Ubah</span>
                    <span className="sr-only md:hidden">Ubah</span>
                    </Button>
                </Link>
            )}

            {bolehNonaktifkan && aktif && (
                <Button
                    icon={EyeOff}
                    size="sm"
                    variant="secondary"
                    onClick={() => setTanyaNonaktif(true)}
                >
                    <span className="hidden md:inline">Nonaktifkan</span>
                    <span className="sr-only md:hidden">Nonaktifkan</span>
                </Button>
            )}

            {bolehAktifkan && ! aktif && (
                <Button
                    icon={ArchiveRestore}
                    size="sm"
                    loading={memproses}
                    onClick={aktifkan}
                >
                    <span className="hidden md:inline">Aktifkan Kembali</span>
                    <span className="sr-only md:hidden">Aktifkan Kembali</span>
                </Button>
            )}

            <ConfirmDialog
                terbuka={tanyaNonaktif}
                onTutup={() => setTanyaNonaktif(false)}
                onSetuju={nonaktifkan}
                judul="Nonaktifkan dokumen ini?"
                labelSetuju="Ya, nonaktifkan"
                ikon={EyeOff}
                memproses={memproses}
            >
                <p>
                    <span className="font-medium text-ink">{judul}</span> akan hilang dari
                    daftar dokumen dan hasil pencarian untuk semua orang.
                </p>
                <p>
                    Dokumennya <span className="font-medium text-ink">tidak dihapus</span>.
                    Berkas, riwayat, dan catatan aktivitasnya tetap tersimpan, dan
                    Superadmin dapat mengaktifkannya kembali kapan saja.
                </p>
            </ConfirmDialog>
        </>
    );
}

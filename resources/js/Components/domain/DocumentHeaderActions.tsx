import { Button } from '@/Components/ui/Button';
import { usePasswordConfirmation } from '@/Components/auth/PasswordConfirmationProvider';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Link, router } from '@inertiajs/react';
import { ArchiveRestore, Download, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

export interface DocumentHeaderActionsProps {
    dokumenId: number;
    judul: string;
    aktif: boolean;
    bolehUbah: boolean;
    bolehPindahKeSampah: boolean;
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
    bolehPindahKeSampah,
    bolehAktifkan,
}: DocumentHeaderActionsProps) {
    const konfirmasikan = usePasswordConfirmation();
    const [tanyaSampah, setTanyaSampah] = useState(false);
    const [memproses, setMemproses] = useState(false);

    function pindahKeSampah() {
        konfirmasikan(jalankanPindahKeSampah);
    }

    function jalankanPindahKeSampah() {
        setMemproses(true);
        router.delete(`/documents/${dokumenId}`, {
            onFinish: () => {
                setMemproses(false);
                setTanyaSampah(false);
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

            {bolehPindahKeSampah && aktif && (
                <Button
                    icon={Trash2}
                    size="sm"
                    variant="secondary"
                    onClick={() => setTanyaSampah(true)}
                >
                    <span className="hidden md:inline">Pindahkan ke Sampah</span>
                    <span className="sr-only md:hidden">Pindahkan ke Sampah</span>
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
                terbuka={tanyaSampah}
                onTutup={() => setTanyaSampah(false)}
                onSetuju={pindahKeSampah}
                judul="Pindahkan dokumen ke Sampah?"
                labelSetuju="Ya, pindahkan"
                ikon={Trash2}
                memproses={memproses}
            >
                <p>
                    <span className="font-medium text-ink">{judul}</span> akan hilang dari
                    daftar dan hasil pencarian selama berada di Sampah.
                </p>
                <p>
                    Anda dapat memulihkannya selama 30 hari. Setelah itu, berkas dan
                    versinya dihapus permanen; ringkasan aktivitas audit tetap tersimpan.
                </p>
            </ConfirmDialog>
        </>
    );
}

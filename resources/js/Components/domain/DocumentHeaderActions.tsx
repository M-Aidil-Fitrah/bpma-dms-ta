import { Button } from '@/Components/ui/Button';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Link, router } from '@inertiajs/react';
import axios from 'axios';
import { ArchiveRestore, Download, LockKeyhole, Pencil, Trash2 } from 'lucide-react';
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
    const [tanyaSampah, setTanyaSampah] = useState(false);
    const [konfirmasiSandi, setKonfirmasiSandi] = useState(false);
    const [password, setPassword] = useState('');
    const [galatPassword, setGalatPassword] = useState<string>();
    const [memproses, setMemproses] = useState(false);

    function lanjutkanKeKonfirmasiSandi() {
        setKonfirmasiSandi(true);
        setGalatPassword(undefined);
    }

    async function pindahKeSampah() {
        if (!password) {
            setGalatPassword('Masukkan kata sandi Anda untuk melanjutkan.');

            return;
        }

        setMemproses(true);
        setGalatPassword(undefined);

        try {
            await axios.post('/confirm-password', { password }, { headers: { Accept: 'application/json' } });
            router.delete(`/documents/${dokumenId}`, {
                onFinish: () => {
                    setMemproses(false);
                    tutupKonfirmasiSampah();
                },
            });
        } catch (error) {
            if (axios.isAxiosError<{ errors?: { password?: string[] } }>(error)) {
                setGalatPassword(error.response?.data.errors?.password?.[0] ?? 'Kata sandi tidak dapat dikonfirmasi.');
            } else {
                setGalatPassword('Konfirmasi kata sandi tidak dapat diproses. Coba lagi.');
            }
            setMemproses(false);
        }
    }

    function aktifkan() {
        setMemproses(true);
        router.patch(
            `/documents/${dokumenId}/restore`,
            {},
            { onFinish: () => setMemproses(false) },
        );
    }

    function tutupKonfirmasiSampah() {
        if (memproses) return;

        setTanyaSampah(false);
        setKonfirmasiSandi(false);
        setPassword('');
        setGalatPassword(undefined);
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
                    onClick={() => {
                        setTanyaSampah(true);
                        setKonfirmasiSandi(false);
                        setPassword('');
                        setGalatPassword(undefined);
                    }}
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
                onTutup={tutupKonfirmasiSampah}
                onSetuju={konfirmasiSandi ? pindahKeSampah : lanjutkanKeKonfirmasiSandi}
                judul={konfirmasiSandi ? 'Konfirmasi kata sandi' : 'Pindahkan dokumen ke Sampah?'}
                labelSetuju={konfirmasiSandi ? 'Konfirmasi dan pindahkan' : 'Lanjutkan'}
                ikon={konfirmasiSandi ? LockKeyhole : Trash2}
                memproses={memproses}
            >
                {konfirmasiSandi ? (
                    <Field label="Kata Sandi" error={galatPassword} required>
                        {(input) => (
                            <Input
                                {...input}
                                type="password"
                                autoComplete="current-password"
                                icon={LockKeyhole}
                                value={password}
                                autoFocus
                                invalid={Boolean(galatPassword)}
                                onChange={(event) => setPassword(event.target.value)}
                            />
                        )}
                    </Field>
                ) : (
                    <>
                        <p>
                            <span className="font-medium text-ink">{judul}</span> akan hilang dari
                            daftar dan hasil pencarian selama berada di Sampah.
                        </p>
                        <p>
                            Anda dapat memulihkannya selama 30 hari. Setelah itu, berkas dan
                            versinya dihapus permanen; ringkasan aktivitas audit tetap tersimpan.
                        </p>
                    </>
                )}
            </ConfirmDialog>
        </>
    );
}

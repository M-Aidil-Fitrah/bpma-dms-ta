import { Button } from '@/Components/ui/Button';
import { Field } from '@/Components/ui/Field';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import axios from 'axios';
import { LockKeyhole, ShieldCheck } from 'lucide-react';
import { createContext, useCallback, useContext, useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { usePage } from '@inertiajs/react';

type KonfirmasiAksi = (aksi: () => void) => void;

const KonteksKonfirmasiPassword = createContext<KonfirmasiAksi | null>(null);

/**
 * Konfirmasi ulang password sebelum aksi sensitif dikirim.
 *
 * Aksi baru dijalankan sesudah server menyimpan waktu konfirmasi. Middleware
 * pada rute tetap menjadi penjaga akhir bila request dikirim tanpa antarmuka.
 */
export function PasswordConfirmationProvider({ children }: { children: ReactNode }) {
    const { props } = usePage();
    const batasDariServer = props.auth.password_confirmed_until;
    const [berlakuSampai, setBerlakuSampai] = useState(waktu(batasDariServer));
    const [terbuka, setTerbuka] = useState(false);
    const [password, setPassword] = useState('');
    const [galat, setGalat] = useState<string | undefined>();
    const [memproses, setMemproses] = useState(false);
    const [aksiTertunda, setAksiTertunda] = useState<(() => void) | null>(null);

    useEffect(() => {
        setBerlakuSampai(waktu(batasDariServer));
    }, [batasDariServer]);

    const konfirmasikan = useCallback((aksi: () => void) => {
        if (berlakuSampai !== null && berlakuSampai > Date.now()) {
            aksi();

            return;
        }

        setAksiTertunda(() => aksi);
        setPassword('');
        setGalat(undefined);
        setTerbuka(true);
    }, [berlakuSampai]);

    function tutup() {
        if (memproses) return;

        setTerbuka(false);
        setAksiTertunda(null);
        setPassword('');
        setGalat(undefined);
    }

    async function kirim(event: FormEvent) {
        event.preventDefault();
        setMemproses(true);
        setGalat(undefined);

        try {
            const respons = await axios.post<{ password_confirmed_until: string }>('/confirm-password', { password }, {
                headers: { Accept: 'application/json' },
            });
            const aksi = aksiTertunda;

            setBerlakuSampai(waktu(respons.data.password_confirmed_until));
            setTerbuka(false);
            setAksiTertunda(null);
            setPassword('');
            aksi?.();
        } catch (error) {
            if (axios.isAxiosError<{ errors?: { password?: string[] } }>(error)) {
                setGalat(error.response?.data.errors?.password?.[0] ?? 'Kata sandi tidak dapat dikonfirmasi.');
            } else {
                setGalat('Konfirmasi kata sandi tidak dapat diproses. Coba lagi.');
            }
        } finally {
            setMemproses(false);
        }
    }

    return (
        <KonteksKonfirmasiPassword.Provider value={konfirmasikan}>
            {children}
            <Modal
                terbuka={terbuka}
                onTutup={tutup}
                judul="Konfirmasi kata sandi"
                keterangan="Masukkan kembali kata sandi Anda untuk melanjutkan aksi sensitif ini."
                footer={
                    <>
                        <Button type="button" variant="secondary" onClick={tutup} disabled={memproses} className="w-full sm:w-auto">Batal</Button>
                        <Button type="submit" form="konfirmasi-password-aksi" icon={ShieldCheck} loading={memproses} className="w-full sm:w-auto">Konfirmasi</Button>
                    </>
                }
            >
                <form id="konfirmasi-password-aksi" onSubmit={kirim}>
                    <Field label="Kata Sandi" error={galat} required>
                        {(input) => <Input {...input} type="password" autoComplete="current-password" icon={LockKeyhole} value={password} autoFocus invalid={Boolean(galat)} onChange={(event) => setPassword(event.target.value)} />}
                    </Field>
                </form>
            </Modal>
        </KonteksKonfirmasiPassword.Provider>
    );
}

export function usePasswordConfirmation(): KonfirmasiAksi {
    const konteks = useContext(KonteksKonfirmasiPassword);

    if (konteks === null) throw new Error('usePasswordConfirmation harus dipakai di dalam PasswordConfirmationProvider.');

    return konteks;
}

function waktu(nilai: string | null | undefined): number | null {
    if (!nilai) return null;

    const hasil = Date.parse(nilai);

    return Number.isNaN(hasil) ? null : hasil;
}

import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';

const PANJANG = 6;

export default function VerifyResetCode({ email, expireMinutes, maxAttempts }) {
    const { data, setData, post, processing, errors } = useForm({ code: '' });
    const kotakRef = useRef([]);

    const digits = data.code.padEnd(PANJANG, ' ').slice(0, PANJANG).split('');

    const fokus = (i) => kotakRef.current[i]?.focus();

    const setDigit = (i, nilai) => {
        // Hanya angka yang diterima, dan tempelan seperti "482917" langsung
        // terisi ke seluruh kotak, bukan cuma yang sedang aktif.
        const bersih = nilai.replace(/\D/g, '');

        if (!bersih) {
            return;
        }

        const berikut = (
            data.code.slice(0, i) +
            bersih +
            data.code.slice(i + bersih.length)
        ).slice(0, PANJANG);

        setData('code', berikut);
        fokus(Math.min(i + bersih.length, PANJANG - 1));
    };

    const onKeyDown = (i, e) => {
        if (e.key === 'Backspace') {
            e.preventDefault();

            // Kotak terisi dikosongkan di tempat; kotak kosong memundurkan
            // kursor — perilaku yang diharapkan orang dari input berkotak.
            const posisi = data.code[i] ? i : Math.max(i - 1, 0);

            setData(
                'code',
                data.code.slice(0, posisi) + data.code.slice(posisi + 1),
            );
            fokus(posisi);
        }

        if (e.key === 'ArrowLeft') {
            fokus(Math.max(i - 1, 0));
        }

        if (e.key === 'ArrowRight') {
            fokus(Math.min(i + 1, PANJANG - 1));
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('password.code.store'));
    };

    return (
        <GuestLayout>
            <Head title="Masukkan Kode" />

            <h1 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                Masukkan kode
            </h1>

            <p className="mb-4 mt-1 text-sm text-gray-600 dark:text-gray-400">
                Kami kirimkan {PANJANG} digit angka ke{' '}
                <span className="font-medium text-gray-800 dark:text-gray-200">
                    {email}
                </span>
                . Kode berlaku {expireMinutes} menit dan hangus setelah{' '}
                {maxAttempts} kali salah.
            </p>

            <form onSubmit={submit}>
                <div
                    className="flex justify-between gap-2"
                    onPaste={(e) => {
                        e.preventDefault();
                        setDigit(0, e.clipboardData.getData('text'));
                    }}
                >
                    {digits.map((angka, i) => (
                        <input
                            key={i}
                            ref={(el) => (kotakRef.current[i] = el)}
                            type="text"
                            inputMode="numeric"
                            autoComplete={i === 0 ? 'one-time-code' : 'off'}
                            maxLength={PANJANG}
                            value={angka.trim()}
                            aria-label={`Digit ke-${i + 1}`}
                            autoFocus={i === 0}
                            onChange={(e) => setDigit(i, e.target.value)}
                            onKeyDown={(e) => onKeyDown(i, e)}
                            className="h-14 w-full rounded-md border-gray-300 text-center text-xl font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                        />
                    ))}
                </div>

                <InputError message={errors.code} className="mt-2" />

                <div className="mt-4 flex items-center justify-between">
                    <Link
                        href={route('password.request')}
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-gray-400 dark:hover:text-gray-100"
                    >
                        Kirim ulang kode
                    </Link>

                    <PrimaryButton
                        disabled={processing || data.code.length < PANJANG}
                    >
                        {processing ? 'Memeriksa…' : 'Lanjutkan'}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}

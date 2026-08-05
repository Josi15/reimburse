import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status, expireMinutes }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Lupa Password" />

            <h1 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                Lupa password?
            </h1>

            <p className="mb-4 mt-1 text-sm text-gray-600 dark:text-gray-400">
                Masukkan email akun Anda. Kami kirimkan tautan untuk membuat
                password baru — tautannya berlaku {expireMinutes} menit dan
                hanya bisa dipakai sekali.
            </p>

            {status && (
                <div className="mb-4 rounded-md bg-green-50 p-3 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <InputLabel htmlFor="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="mt-1 block w-full"
                    autoComplete="username"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <InputError message={errors.email} className="mt-2" />

                <div className="mt-4 flex items-center justify-between">
                    <Link
                        href={route('login')}
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-gray-400 dark:hover:text-gray-100"
                    >
                        Kembali ke halaman masuk
                    </Link>

                    <PrimaryButton disabled={processing}>
                        {processing ? 'Mengirim…' : 'Kirim Tautan Reset'}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}

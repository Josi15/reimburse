import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ResetPassword({ email }) {
    // Email tidak ikut dikirim: akun yang diubah ditentukan server dari sesi
    // yang sudah lolos verifikasi kode. Kalau alamatnya bisa diketik ulang di
    // sini, kode yang dikirim ke alamat sendiri bisa dipakai mengganti password
    // akun orang lain.
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Atur Password Baru" />

            <h1 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                Atur password baru
            </h1>

            <p className="mb-4 mt-1 text-sm text-gray-600 dark:text-gray-400">
                Untuk akun{' '}
                <span className="font-medium text-gray-800 dark:text-gray-200">
                    {email}
                </span>
                . Gunakan minimal 8 karakter dengan kombinasi huruf besar, huruf
                kecil, angka, dan simbol.
            </p>

            <form onSubmit={submit}>
                <InputError message={errors.email} className="mb-4" />

                <div>
                    <InputLabel htmlFor="password" value="Password Baru" />

                    <PasswordInput
                        id="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        isFocused={true}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Ulangi Password Baru"
                    />

                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Menyimpan…' : 'Simpan Password Baru'}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}

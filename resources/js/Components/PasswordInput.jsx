import TextInput from '@/Components/TextInput';
import { forwardRef, useState } from 'react';

/** Ikon mata (terlihat) / mata tercoret (tersembunyi). */
function EyeIcon({ open }) {
    return (
        <svg
            className="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {open ? (
                <>
                    <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
                    <circle cx="12" cy="12" r="3" />
                </>
            ) : (
                <>
                    <path d="M10.6 6.1A8.9 8.9 0 0 1 12 6c6 0 9.5 6 9.5 6a15.5 15.5 0 0 1-3 3.6M6.6 8.3A15.4 15.4 0 0 0 2.5 12S6 18 12 18c1.5 0 2.8-.3 4-.9" />
                    <path d="M10 10.2a2.6 2.6 0 0 0 3.7 3.6M3 3l18 18" />
                </>
            )}
        </svg>
    );
}

/**
 * Input password dengan tombol ikon mata untuk memperlihatkan/menyembunyikan
 * isinya — supaya salah ketik bisa langsung dicek sendiri, terutama di ponsel.
 *
 * Menerima seluruh props TextInput; `type` sengaja dikendalikan komponen ini.
 */
export default forwardRef(function PasswordInput(
    { className = '', ...props },
    ref,
) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <TextInput
                {...props}
                ref={ref}
                type={visible ? 'text' : 'password'}
                className={`pr-11 ${className}`}
            />
            <button
                type="button"
                onClick={() => setVisible((v) => !v)}
                // Tombol bantu, bukan bagian alur isian: dilewati saat Tab
                // supaya urutan fokus form tetap email → password → submit.
                tabIndex={-1}
                aria-label={
                    visible ? 'Sembunyikan password' : 'Tampilkan password'
                }
                aria-pressed={visible}
                title={visible ? 'Sembunyikan password' : 'Tampilkan password'}
                className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 transition hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:hover:text-gray-200"
            >
                <EyeIcon open={visible} />
            </button>
        </div>
    );
});

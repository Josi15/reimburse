import { resolveIsDark, setTheme } from '@/lib/theme';
import { useEffect, useState } from 'react';

/** Tombol ikon untuk beralih antara mode terang & gelap. */
export default function ThemeToggle({ className = '' }) {
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        setIsDark(resolveIsDark());
        const onChange = () => setIsDark(resolveIsDark());
        window.addEventListener('theme-change', onChange);
        return () => window.removeEventListener('theme-change', onChange);
    }, []);

    const toggle = () => {
        const next = resolveIsDark() ? 'light' : 'dark';
        setTheme(next);
        setIsDark(next === 'dark');
    };

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'}
            title={isDark ? 'Mode terang' : 'Mode gelap'}
            className={`inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:hover:text-gray-200 dark:focus-visible:ring-offset-gray-800 ${className}`}
        >
            {isDark ? (
                // Matahari: klik untuk ke mode terang.
                <svg
                    className="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="4" />
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32l1.41-1.41"
                    />
                </svg>
            ) : (
                // Bulan: klik untuk ke mode gelap.
                <svg
                    className="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    aria-hidden="true"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"
                    />
                </svg>
            )}
        </button>
    );
}

import TextInput from '@/Components/TextInput';
import { useMemo, useState } from 'react';

/**
 * Daftar pilihan ganda berbasis checkbox + kotak cari. Dipakai untuk daftar
 * yang bisa panjang (mis. menugaskan karyawan/magang ke sebuah proyek), di mana
 * <select multiple> bawaan browser sulit dipakai.
 *
 * `options`: [{ value, label, hint? }]. `value`: array of value.
 */
export default function MultiSelectList({
    id,
    options = [],
    value = [],
    onChange,
    emptyText = 'Tidak ada pilihan.',
    searchPlaceholder = 'Cari…',
}) {
    const [q, setQ] = useState('');

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return options;
        return options.filter(
            (o) =>
                o.label.toLowerCase().includes(needle) ||
                (o.hint ?? '').toLowerCase().includes(needle),
        );
    }, [options, q]);

    const toggle = (optionValue) => {
        const exists = value.some((v) => String(v) === String(optionValue));
        onChange(
            exists
                ? value.filter((v) => String(v) !== String(optionValue))
                : [...value, optionValue],
        );
    };

    return (
        <div className="mt-1 rounded-md border border-gray-200 dark:border-gray-700">
            <div className="border-b border-gray-100 p-2 dark:border-gray-700">
                <TextInput
                    id={id}
                    placeholder={searchPlaceholder}
                    className="block w-full text-sm"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                />
            </div>

            <div className="max-h-52 overflow-y-auto p-2">
                {filtered.length === 0 ? (
                    <p className="px-1 py-2 text-sm text-gray-500 dark:text-gray-400">
                        {options.length === 0
                            ? emptyText
                            : 'Tidak ada yang cocok.'}
                    </p>
                ) : (
                    filtered.map((o) => (
                        <label
                            key={o.value}
                            className="flex cursor-pointer items-center gap-2 rounded px-1 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/50"
                        >
                            <input
                                type="checkbox"
                                className="rounded border-gray-300"
                                checked={value.some(
                                    (v) => String(v) === String(o.value),
                                )}
                                onChange={() => toggle(o.value)}
                            />
                            <span className="min-w-0 flex-1 truncate">
                                {o.label}
                                {o.hint && (
                                    <span className="ml-1 text-xs text-gray-400">
                                        · {o.hint}
                                    </span>
                                )}
                            </span>
                        </label>
                    ))
                )}
            </div>

            <div className="border-t border-gray-100 px-3 py-1.5 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                {value.length} dipilih
            </div>
        </div>
    );
}

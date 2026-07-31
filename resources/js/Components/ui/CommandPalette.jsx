import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Palet perintah global (⌘K / Ctrl+K).
 * - Dibuka via pintasan keyboard atau event 'open-command-palette'.
 * - Daftar opsi dibangun dari page.props.navigation (label + href).
 */
export default function CommandPalette() {
    const page = usePage();
    const navigation = page.props.navigation ?? [];

    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    // Buka lewat pintasan ⌘K / Ctrl+K atau event kustom dari tombol "Cari…".
    useEffect(() => {
        const onKeyDown = (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen(true);
            }
        };
        const onOpenEvent = () => setOpen(true);

        window.addEventListener('keydown', onKeyDown);
        window.addEventListener('open-command-palette', onOpenEvent);
        return () => {
            window.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('open-command-palette', onOpenEvent);
        };
    }, []);

    const close = () => {
        setOpen(false);
        // Reset query setelah animasi tutup agar tidak berkedip.
        setTimeout(() => setQuery(''), 150);
    };

    const filtered =
        query.trim() === ''
            ? navigation
            : navigation.filter((item) =>
                  item.label.toLowerCase().includes(query.trim().toLowerCase()),
              );

    const onSelect = (item) => {
        if (!item) return;
        close();
        router.visit(item.href);
    };

    return (
        <Transition show={open} afterLeave={() => setQuery('')} appear>
            <Dialog as="div" className="relative z-50" onClose={close}>
                <TransitionChild
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" />
                </TransitionChild>

                <div className="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20">
                    <TransitionChild
                        enter="ease-out duration-200"
                        enterFrom="opacity-0 scale-95"
                        enterTo="opacity-100 scale-100"
                        leave="ease-in duration-150"
                        leaveFrom="opacity-100 scale-100"
                        leaveTo="opacity-0 scale-95"
                    >
                        <DialogPanel className="mx-auto max-w-lg transform overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-black/5 transition-all dark:bg-gray-800 dark:ring-white/10">
                            <Combobox onChange={onSelect}>
                                <div className="relative">
                                    <svg
                                        className="pointer-events-none absolute left-4 top-3.5 h-5 w-5 text-gray-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        strokeWidth="1.8"
                                        aria-hidden="true"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="m21 21-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z"
                                        />
                                    </svg>
                                    <ComboboxInput
                                        autoFocus
                                        className="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-gray-100"
                                        placeholder="Cari halaman atau aksi…"
                                        onChange={(e) =>
                                            setQuery(e.target.value)
                                        }
                                        onBlur={() => setQuery(query)}
                                        displayValue={() => ''}
                                    />
                                </div>

                                {filtered.length > 0 && (
                                    <ComboboxOptions
                                        static
                                        className="max-h-72 scroll-py-2 overflow-y-auto border-t border-gray-100 py-2 text-sm dark:border-gray-700"
                                    >
                                        {filtered.map((item) => (
                                            <ComboboxOption
                                                key={item.href}
                                                value={item}
                                                className="mx-2 flex cursor-pointer select-none items-center justify-between rounded-md px-3 py-2 text-gray-700 data-[focus]:bg-brand-50 data-[focus]:text-brand-700 dark:text-gray-200 dark:data-[focus]:bg-brand-900/30 dark:data-[focus]:text-brand-300"
                                            >
                                                <span className="truncate">
                                                    {item.label}
                                                </span>
                                                <span className="ml-3 shrink-0 text-xs text-gray-400">
                                                    {item.href}
                                                </span>
                                            </ComboboxOption>
                                        ))}
                                    </ComboboxOptions>
                                )}

                                {query.trim() !== '' &&
                                    filtered.length === 0 && (
                                        <div className="border-t border-gray-100 px-6 py-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                            Tidak ada hasil
                                        </div>
                                    )}
                            </Combobox>

                            <div className="flex items-center justify-end gap-1 border-t border-gray-100 px-4 py-2 text-[11px] text-gray-400 dark:border-gray-700">
                                ↑↓ pilih · ↵ buka · esc tutup
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}

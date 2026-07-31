import CommandPalette from '@/Components/ui/CommandPalette';
import NotificationBell from '@/Components/ui/NotificationBell';
import RoleBadge from '@/Components/ui/RoleBadge';
import Sidebar from '@/Components/ui/Sidebar';
import ThemeToggle from '@/Components/ui/ThemeToggle';
import Toaster from '@/Components/ui/Toaster';
import Logo from '@/Components/ui/Logo';
import {
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const page = usePage();
    const user = page.props.auth.user;
    const navigation = page.props.navigation ?? [];
    const currentUrl = page.url;

    const isActive = (href) =>
        href === '/dashboard'
            ? currentUrl.startsWith('/dashboard')
            : currentUrl.startsWith(href);

    const [drawerOpen, setDrawerOpen] = useState(false);

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            <Toaster />
            <CommandPalette />

            {/* Sidebar tetap (desktop) */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 lg:block">
                <Sidebar
                    navigation={navigation}
                    user={user}
                    isActive={isActive}
                />
            </aside>

            {/* Drawer sidebar (mobile) */}
            <Transition show={drawerOpen}>
                <Dialog
                    as="div"
                    className="relative z-40 lg:hidden"
                    onClose={setDrawerOpen}
                >
                    <TransitionChild
                        enter="ease-out duration-200"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="ease-in duration-150"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <div className="fixed inset-0 bg-gray-900/50" />
                    </TransitionChild>

                    <div className="fixed inset-0 flex">
                        <TransitionChild
                            enter="transition ease-out duration-200"
                            enterFrom="-translate-x-full"
                            enterTo="translate-x-0"
                            leave="transition ease-in duration-150"
                            leaveFrom="translate-x-0"
                            leaveTo="-translate-x-full"
                        >
                            <DialogPanel className="w-64 max-w-[80%] bg-white shadow-xl dark:bg-gray-800">
                                <Sidebar
                                    navigation={navigation}
                                    user={user}
                                    isActive={isActive}
                                    onNavigate={() => setDrawerOpen(false)}
                                />
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </Dialog>
            </Transition>

            {/* Area konten */}
            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur dark:border-gray-700 dark:bg-gray-800/90">
                    <div className="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                        {/* Kiri: hamburger (mobile) + logo (mobile) + header prop */}
                        <button
                            type="button"
                            onClick={() => setDrawerOpen(true)}
                            aria-label="Buka menu navigasi"
                            className="inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:text-gray-400 dark:hover:bg-gray-700 lg:hidden"
                        >
                            <svg
                                className="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                strokeWidth="2"
                                aria-hidden="true"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>

                        <Link
                            href="/"
                            className="lg:hidden"
                            aria-label="Beranda"
                        >
                            <Logo showText={false} />
                        </Link>

                        <div className="min-w-0 flex-1">{header}</div>

                        <div className="flex items-center gap-2">
                            {/* Label role: di mobile sidebar tersembunyi, jadi
                                identitas role tetap terlihat dari header. */}
                            <RoleBadge
                                role={user.role}
                                label={user.role_label}
                                className="hidden sm:inline-flex lg:hidden"
                            />
                            <ThemeToggle className="lg:hidden" />
                            <NotificationBell />
                        </div>
                    </div>
                </header>

                <main>{children}</main>
            </div>
        </div>
    );
}

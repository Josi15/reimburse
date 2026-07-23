import { api } from '@/lib/api';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Lonceng notifikasi di navbar: badge jumlah belum dibaca, refresh berkala. */
export default function NotificationBell() {
    const [count, setCount] = useState(0);

    useEffect(() => {
        let mounted = true;
        const load = () =>
            api
                .get('/api/notifications/unread-count')
                .then((d) => mounted && setCount(d.count))
                .catch(() => {});

        // Hanya polling saat tab terlihat (hemat request di background).
        const tick = () => {
            if (!document.hidden) load();
        };

        load();
        const timer = setInterval(tick, 60_000);

        const onVisibility = () => {
            if (!document.hidden) load();
        };
        const onNotificationsRead = () => load();

        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('focus', onVisibility);
        window.addEventListener('notifications-read', onNotificationsRead);

        return () => {
            mounted = false;
            clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('focus', onVisibility);
            window.removeEventListener(
                'notifications-read',
                onNotificationsRead,
            );
        };
    }, []);

    return (
        <Link
            href="/notifications"
            className="relative rounded-full p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
            aria-label="Notifikasi"
        >
            <svg
                className="h-6 w-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                strokeWidth="1.8"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
                />
            </svg>
            {count > 0 && (
                <span className="absolute -right-0.5 -top-0.5 inline-flex min-w-[18px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </Link>
    );
}

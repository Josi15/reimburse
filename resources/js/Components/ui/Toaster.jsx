import { cn } from '@/lib/format';
import { useEffect, useState } from 'react';

let seq = 0;

/** Menampilkan toast dari event 'app-toast'. Pasang sekali di layout. */
export default function Toaster() {
    const [items, setItems] = useState([]);

    useEffect(() => {
        function onToast(e) {
            const id = ++seq;
            setItems((prev) => [...prev, { id, ...e.detail }]);
            setTimeout(() => {
                setItems((prev) => prev.filter((t) => t.id !== id));
            }, 4000);
        }
        window.addEventListener('app-toast', onToast);
        return () => window.removeEventListener('app-toast', onToast);
    }, []);

    return (
        <div className="pointer-events-none fixed right-4 top-4 z-50 flex w-80 flex-col gap-2">
            {items.map((t) => (
                <div
                    key={t.id}
                    className={cn(
                        'pointer-events-auto rounded-lg px-4 py-3 text-sm text-white shadow-lg',
                        t.type === 'error' ? 'bg-red-600' : t.type === 'info' ? 'bg-blue-600' : 'bg-green-600',
                    )}
                >
                    {t.message}
                </div>
            ))}
        </div>
    );
}

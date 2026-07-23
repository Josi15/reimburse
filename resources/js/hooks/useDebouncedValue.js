import { useEffect, useState } from 'react';

/** Kembalikan nilai yang tertunda (debounce) setelah `delay` ms tanpa perubahan. */
export default function useDebouncedValue(value, delay = 300) {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(value), delay);
        return () => clearTimeout(timer);
    }, [value, delay]);

    return debounced;
}

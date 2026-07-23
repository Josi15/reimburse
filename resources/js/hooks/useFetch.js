import { api, handleApiError } from '@/lib/api';
import { useCallback, useEffect, useRef, useState } from 'react';

/** GET data dari API dengan status loading, error, + reload manual. */
export default function useFetch(url) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Penanda respons stale; diperbarui tiap efek/reload berjalan.
    const activeRef = useRef(0);

    const reload = useCallback(async () => {
        const token = ++activeRef.current;
        setLoading(true);
        setError(null);
        try {
            const result = await api.get(url);
            if (token === activeRef.current) setData(result);
        } catch (e) {
            if (token === activeRef.current) setError(true);
            handleApiError(e, 'Gagal memuat data.');
        } finally {
            if (token === activeRef.current) setLoading(false);
        }
    }, [url]);

    useEffect(() => {
        reload();
        return () => {
            // Batalkan respons yang sedang berjalan agar tidak ter-set.
            // eslint-disable-next-line react-hooks/exhaustive-deps
            activeRef.current++;
        };
    }, [reload]);

    return { data, loading, error, reload };
}

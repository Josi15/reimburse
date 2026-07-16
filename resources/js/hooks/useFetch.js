import { api, handleApiError } from '@/lib/api';
import { useCallback, useEffect, useState } from 'react';

/** GET data dari API dengan status loading + reload manual. */
export default function useFetch(url) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    const reload = useCallback(async () => {
        setLoading(true);
        try {
            setData(await api.get(url));
        } catch (e) {
            handleApiError(e, 'Gagal memuat data.');
        } finally {
            setLoading(false);
        }
    }, [url]);

    useEffect(() => {
        reload();
    }, [reload]);

    return { data, loading, reload };
}

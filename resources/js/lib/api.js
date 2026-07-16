// Klien API tipis di atas axios (Sanctum SPA, cookie sesi + XSRF).
import { toast } from './toast';

const client = window.axios;

/** Ambil XSRF cookie sekali sebelum request mutasi (Sanctum). */
let csrfReady = false;
async function ensureCsrf() {
    if (!csrfReady) {
        await client.get('/sanctum/csrf-cookie');
        csrfReady = true;
    }
}

export const api = {
    async get(url, config = {}) {
        const res = await client.get(url, config);
        return res.data;
    },
    async post(url, data = {}, config = {}) {
        await ensureCsrf();
        const res = await client.post(url, data, config);
        return res.data;
    },
    async put(url, data = {}, config = {}) {
        await ensureCsrf();
        const res = await client.put(url, data, config);
        return res.data;
    },
    async patch(url, data = {}, config = {}) {
        await ensureCsrf();
        const res = await client.patch(url, data, config);
        return res.data;
    },
    async delete(url, config = {}) {
        await ensureCsrf();
        const res = await client.delete(url, config);
        return res.data;
    },
};

/** Ambil pesan error yang ramah dari response axios. */
export function apiError(error, fallback = 'Terjadi kesalahan.') {
    const res = error?.response;
    if (res?.status === 422 && res.data?.errors) {
        return Object.values(res.data.errors)[0]?.[0] ?? fallback;
    }
    return res?.data?.message || fallback;
}

/** Tampilkan error sebagai toast dan kembalikan error validasi (jika ada). */
export function handleApiError(error, fallback) {
    toast(apiError(error, fallback), 'error');
    return error?.response?.data?.errors ?? {};
}

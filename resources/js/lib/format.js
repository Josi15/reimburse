// Utilitas format & helper UI.

/** Format angka rupiah: 1500000 -> "Rp 1.500.000". */
export function rupiah(value) {
    const n = Number(value ?? 0);
    return 'Rp ' + n.toLocaleString('id-ID');
}

/** Format tanggal ke locale Indonesia. */
export function formatDate(value, withTime = false) {
    if (!value) return '-';
    const d = new Date(value);
    const opts = { day: '2-digit', month: 'short', year: 'numeric' };
    if (withTime) {
        opts.hour = '2-digit';
        opts.minute = '2-digit';
    }
    return d.toLocaleDateString('id-ID', opts);
}

/** Kelas warna badge Tailwind berdasarkan warna status dari backend. */
export function badgeClass(color) {
    const map = {
        gray: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    };
    return map[color] ?? map.gray;
}

/** Gabungkan className secara kondisional. */
export function cn(...classes) {
    return classes.filter(Boolean).join(' ');
}

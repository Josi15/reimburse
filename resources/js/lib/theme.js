// Utilitas tema (dark mode) dengan strategi class Tailwind.
// Nilai tema yang valid: 'dark' | 'light' | 'system'.

const STORAGE_KEY = 'theme';
const mql = () => window.matchMedia('(prefers-color-scheme: dark)');

/** Ambil preferensi tema tersimpan (default 'system'). */
export function getStoredTheme() {
    const t = localStorage.getItem(STORAGE_KEY);
    return t === 'dark' || t === 'light' ? t : 'system';
}

/** Apakah tema aktif saat ini gelap? */
export function resolveIsDark(theme = getStoredTheme()) {
    if (theme === 'dark') return true;
    if (theme === 'light') return false;
    return mql().matches; // 'system'
}

/** Terapkan tema ke <html> (menamb/menghapus class 'dark'). */
export function applyTheme(theme = getStoredTheme()) {
    document.documentElement.classList.toggle('dark', resolveIsDark(theme));
}

/** Simpan tema, terapkan, dan beri tahu komponen lain lewat event. */
export function setTheme(theme) {
    localStorage.setItem(STORAGE_KEY, theme);
    applyTheme(theme);
    window.dispatchEvent(
        new CustomEvent('theme-change', { detail: { theme } }),
    );
}

/**
 * Dengarkan perubahan tema OS saat preferensi = 'system'.
 * Mengembalikan fungsi cleanup untuk melepas listener.
 */
export function initSystemListener() {
    const m = mql();
    const onChange = () => {
        if (getStoredTheme() === 'system') applyTheme('system');
    };
    m.addEventListener('change', onChange);
    return () => m.removeEventListener('change', onChange);
}

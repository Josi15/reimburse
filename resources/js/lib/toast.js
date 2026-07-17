// Toast sederhana berbasis event (tanpa dependency eksternal).

export function toast(message, type = 'success') {
    window.dispatchEvent(
        new CustomEvent('app-toast', { detail: { message, type } }),
    );
}

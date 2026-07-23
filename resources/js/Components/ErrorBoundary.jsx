import { Component } from 'react';

/** Menangkap error render tak terduga & menampilkan fallback ramah. */
export default class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError() {
        return { hasError: true };
    }

    componentDidCatch(error, info) {
        // eslint-disable-next-line no-console
        console.error('ErrorBoundary menangkap error:', error, info);
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="flex min-h-screen flex-col items-center justify-center bg-gray-100 px-4 text-center dark:bg-gray-900">
                    <div className="text-5xl">⚠️</div>
                    <h1 className="mt-4 text-xl font-semibold text-gray-800 dark:text-gray-100">
                        Terjadi kesalahan tak terduga
                    </h1>
                    <p className="mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                        Maaf, terjadi kesalahan saat menampilkan halaman ini.
                        Silakan muat ulang halaman.
                    </p>
                    <button
                        onClick={() => window.location.reload()}
                        className="mt-6 inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                    >
                        Muat Ulang
                    </button>
                </div>
            );
        }

        return this.props.children;
    }
}

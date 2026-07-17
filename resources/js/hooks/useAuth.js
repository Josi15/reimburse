import { usePage } from '@inertiajs/react';

/** Akses user + helper cek role/permission dari props Inertia. */
export default function useAuth() {
    const { auth } = usePage().props;
    const user = auth?.user;

    const hasRole = (...roles) => roles.some((r) => user?.roles?.includes(r));
    const can = (permission) =>
        hasRole('super_admin') || user?.permissions?.includes(permission);

    return { user, can, hasRole };
}

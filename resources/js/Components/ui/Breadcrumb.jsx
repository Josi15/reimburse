import { Link } from '@inertiajs/react';

/**
 * Breadcrumb navigasi.
 * @param {{ items: {label: React.ReactNode, href?: string}[] }} props
 */
export default function Breadcrumb({ items = [] }) {
    return (
        <nav aria-label="Breadcrumb" className="text-sm">
            <ol className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                {items.map((item, i) => {
                    const isLast = i === items.length - 1;
                    return (
                        <li key={i} className="flex items-center gap-x-1.5">
                            {item.href && !isLast ? (
                                <Link
                                    href={item.href}
                                    className="text-gray-500 transition hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                                >
                                    {item.label}
                                </Link>
                            ) : (
                                <span
                                    className="font-medium text-gray-700 dark:text-gray-200"
                                    aria-current={isLast ? 'page' : undefined}
                                >
                                    {item.label}
                                </span>
                            )}
                            {!isLast && (
                                <span
                                    aria-hidden="true"
                                    className="text-gray-300 dark:text-gray-600"
                                >
                                    ›
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

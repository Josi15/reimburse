import { forwardRef } from 'react';

export default forwardRef(function TextareaInput(
    { className = '', ...props },
    ref,
) {
    return (
        <textarea
            ref={ref}
            className={
                'rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 ' +
                className
            }
            {...props}
        />
    );
});

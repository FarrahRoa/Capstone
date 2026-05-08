import React from 'react';

const STYLES = {
    available: {
        pill: 'border-green-200 bg-green-50/60 text-green-800 dark:border-green-900/60 dark:bg-green-950/25 dark:text-green-200',
        dot: 'bg-green-500 ring-green-500/30',
    },
    unavailable: {
        pill: 'border-red-200 bg-red-50/60 text-red-800 dark:border-red-900/60 dark:bg-red-950/25 dark:text-red-200',
        dot: 'bg-red-500 ring-red-500/30',
    },
};

export default function StatusIndicator({ status, label }) {
    const s = STYLES[status] || STYLES.unavailable;
    return (
        <span
            className={[
                'inline-flex items-center gap-1.5 rounded-md border px-2 py-1 shadow-sm',
                s.pill,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <span className={['h-2 w-2 rounded-full', s.dot, 'ring-2'].join(' ')} />
            {label}
        </span>
    );
}


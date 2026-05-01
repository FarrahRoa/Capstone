import { useEffect, useRef, useState } from 'react';

/**
 * DeferredMount
 * - Keeps layout stable by always reserving container height via `minHeightClassName`.
 * - Mounts heavy children after first paint / idle time, without changing business logic.
 *
 * This is intentionally small and dependency-free.
 */
export default function DeferredMount({
    when = true,
    children,
    placeholder = null,
    minHeightClassName = '',
    className = '',
}) {
    const [mounted, setMounted] = useState(false);
    const didScheduleRef = useRef(false);

    useEffect(() => {
        if (mounted || didScheduleRef.current) return;
        didScheduleRef.current = true;

        // Prefer requestIdleCallback when available; fallback to a short timeout.
        const ric = window.requestIdleCallback;
        if (typeof ric === 'function') {
            const id = ric(() => setMounted(true), { timeout: 1200 });
            return () => {
                try { window.cancelIdleCallback?.(id); } catch { /* noop */ }
            };
        }

        const t = window.setTimeout(() => setMounted(true), 250);
        return () => window.clearTimeout(t);
    }, [mounted]);

    const active = mounted && Boolean(when);
    return (
        <div className={[minHeightClassName, className].filter(Boolean).join(' ')}>
            {active ? children : placeholder}
        </div>
    );
}


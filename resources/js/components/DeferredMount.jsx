import { useEffect, useRef, useState } from 'react';

/**
 * Defers mounting heavy children until the container is near the viewport (IntersectionObserver),
 * or until the browser is idle (requestIdleCallback fallback).
 *
 * This improves first paint/LCP by avoiding initial render/evaluation for below-the-fold panels.
 */
export default function DeferredMount({
    children,
    placeholder = null,
    rootMargin = '200px',
    idleTimeoutMs = 1200,
    onMount,
}) {
    const ref = useRef(null);
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        if (mounted) return;

        let done = false;
        /** @type {null | number} */
        let idleId = null;
        /** @type {null | IntersectionObserver} */
        let io = null;

        const mount = () => {
            if (done) return;
            done = true;
            setMounted(true);
            if (typeof onMount === 'function') onMount();
            if (io) io.disconnect();
            io = null;
            if (idleId != null && 'cancelIdleCallback' in window) {
                // eslint-disable-next-line no-undef
                window.cancelIdleCallback(idleId);
            }
            idleId = null;
        };

        if ('IntersectionObserver' in window) {
            io = new IntersectionObserver(
                (entries) => {
                    if (entries.some((e) => e.isIntersecting)) mount();
                },
                { root: null, rootMargin }
            );
            if (ref.current) io.observe(ref.current);
        }

        if ('requestIdleCallback' in window) {
            // eslint-disable-next-line no-undef
            idleId = window.requestIdleCallback(mount, { timeout: idleTimeoutMs });
        } else {
            const t = window.setTimeout(mount, idleTimeoutMs);
            return () => window.clearTimeout(t);
        }

        return () => {
            done = true;
            if (io) io.disconnect();
            if (idleId != null && 'cancelIdleCallback' in window) {
                // eslint-disable-next-line no-undef
                window.cancelIdleCallback(idleId);
            }
        };
    }, [mounted, rootMargin, idleTimeoutMs, onMount]);

    return <div ref={ref}>{mounted ? children : placeholder}</div>;
}


import { useCallback, useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';

/** Admin: persistent sidebar from this breakpoint up (matches Tailwind `lg`). */
export const ADMIN_DESKTOP_MIN_WIDTH = 1024;

export const ADMIN_SIDEBAR_OPEN_STORAGE_KEY = 'adminSidebarOpen';

/** Read saved sidebar preference (desktop default when unset). */
export function readInitialAdminSidebarOpen() {
    if (typeof window === 'undefined') {
        return false;
    }
    const isDesktop = window.innerWidth >= ADMIN_DESKTOP_MIN_WIDTH;
    const storedState = localStorage.getItem(ADMIN_SIDEBAR_OPEN_STORAGE_KEY);
    if (storedState !== null) {
        try {
            return JSON.parse(storedState);
        } catch {
            return isDesktop;
        }
    }
    return isDesktop;
}

/** Sidebar open state synced to localStorage across navigations and reloads. */
export function usePersistedAdminSidebarOpen() {
    const [isOpen, setIsOpen] = useState(readInitialAdminSidebarOpen);

    useEffect(() => {
        localStorage.setItem(ADMIN_SIDEBAR_OPEN_STORAGE_KEY, JSON.stringify(isOpen));
    }, [isOpen]);

    return [isOpen, setIsOpen];
}

/** Admin overlay drawer: close after navigation below desktop width. */
export const NAV_DRAWER_MOBILE_MEDIA = `(max-width: ${ADMIN_DESKTOP_MIN_WIDTH - 1}px)`;

/** Student Layout + Admin top bar: inline nav from 2xl (1536px) up; drawer below. */
export const NAV_INLINE_MIN_WIDTH = 1536;

/** Student Layout hamburger (inline nav from 2xl up). */
export const LAYOUT_NAV_DRAWER_MOBILE_MEDIA = `(max-width: ${NAV_INLINE_MIN_WIDTH - 1}px)`;

/** Admin top bar: same breakpoint as student Layout for consistency. */
export const ADMIN_NAV_DRAWER_MEDIA = LAYOUT_NAV_DRAWER_MOBILE_MEDIA;

export function isAdminDesktopNav() {
    if (typeof window === 'undefined') {
        return false;
    }
    return window.innerWidth >= ADMIN_DESKTOP_MIN_WIDTH;
}

function useMatchMedia(query) {
    const [matches, setMatches] = useState(() => {
        if (typeof window === 'undefined' || !window.matchMedia) {
            return false;
        }
        return window.matchMedia(query).matches;
    });

    useEffect(() => {
        const mq = window.matchMedia(query);
        const sync = () => setMatches(mq.matches);
        sync();
        mq.addEventListener('change', sync);
        return () => mq.removeEventListener('change', sync);
    }, [query]);

    return matches;
}

export function useIsNavDrawerMobile() {
    return useMatchMedia(NAV_DRAWER_MOBILE_MEDIA);
}

export function isAdminNavInlineViewport() {
    if (typeof window === 'undefined') {
        return false;
    }
    return window.innerWidth >= NAV_INLINE_MIN_WIDTH;
}

export function useIsAdminNavDrawerMode() {
    return useMatchMedia(ADMIN_NAV_DRAWER_MEDIA);
}

/** Close admin drawer after route change when top bar uses hamburger mode (< 2xl). */
export function useCloseAdminNavDrawerOnRouteChange(setNavOpen) {
    const { pathname } = useLocation();

    useEffect(() => {
        if (isAdminNavInlineViewport()) {
            return;
        }
        setNavOpen(false);
    }, [pathname, setNavOpen]);
}

/** Close admin drawer on nav link click when in hamburger mode only. */
export function useAdminNavLinkCloseHandler(onClose) {
    return useCallback(() => {
        if (isAdminNavInlineViewport()) {
            return;
        }
        onClose();
    }, [onClose]);
}

/**
 * Close overlay navigation after in-app route changes on mobile/tablet only.
 * Desktop (≥1024px): never closes — sidebar is a persistent pane.
 */
export function useCloseNavDrawerOnRouteChangeMobileOnly(setNavOpen) {
    const { pathname } = useLocation();

    useEffect(() => {
        if (isAdminDesktopNav()) {
            return;
        }
        setNavOpen(false);
    }, [pathname, setNavOpen]);
}

/** Closes mobile overlay drawer only; no-op on desktop. */
export function useNavLinkCloseHandler(onClose) {
    return useCallback(() => {
        if (isAdminDesktopNav()) {
            return;
        }
        onClose();
    }, [onClose]);
}

/** Scroll a container element to top on route change (not the window). */
export function useScrollContainerToTopOnRouteChange(scrollRef) {
    const { pathname } = useLocation();

    useEffect(() => {
        const el = scrollRef?.current;
        if (el) {
            el.scrollTop = 0;
        }
    }, [pathname, scrollRef]);
}

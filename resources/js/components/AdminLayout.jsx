import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useScrollContainerToTopOnRouteChange } from '../hooks/useNavDrawerBehavior';
import {
    getVisibleAdminNavItems,
    isAdminNavItemActive,
} from './admin/adminNavItems';

const sidebarLinkClass = ({ isActive }) =>
    [
        'block rounded-lg px-3 py-2.5 text-sm font-medium leading-snug transition-colors duration-200',
        isActive
            ? 'bg-white/20 text-white shadow-sm ring-1 ring-xu-gold/60'
            : 'text-white/90 hover:bg-white/12 active:bg-white/18',
    ].join(' ');

function AdminNavList({ items, pathname, onNavLinkClick }) {
    return (
        <nav className="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-y-contain px-2 py-3 [scrollbar-width:thin]">
            <ul className="flex flex-col gap-1">
                {items.map((item) => {
                    const active = isAdminNavItemActive(pathname, item);
                    return (
                        <li key={item.to}>
                            <NavLink
                                to={item.to}
                                className={sidebarLinkClass}
                                aria-current={active ? 'page' : undefined}
                                onClick={onNavLinkClick}
                            >
                                {item.label}
                            </NavLink>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

function AdminTopBar({
    navOpen,
    onToggleNav,
    hamburgerRef,
    accountOpen,
    setAccountOpen,
    accountRef,
    accountLabel,
    user,
    isQueueViewOnly,
    handleLogout,
}) {
    return (
        <header className="z-30 shrink-0 border-b border-black/10 bg-xu-primary text-white shadow-md">
            <div className="mx-auto flex min-h-[3.5rem] w-full min-w-0 max-w-[100vw] items-center gap-2 px-3 py-2 sm:gap-3 sm:px-5 lg:px-8">
                <button
                    ref={hamburgerRef}
                    type="button"
                    className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-white/25 bg-white/10 text-white transition hover:bg-white/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-xu-gold/70"
                    aria-expanded={navOpen ? 'true' : 'false'}
                    aria-controls="admin-nav-drawer"
                    aria-label={navOpen ? 'Close navigation menu' : 'Open navigation menu'}
                    onClick={onToggleNav}
                >
                    {navOpen ? (
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    ) : (
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    )}
                </button>

                <Link
                    to="/admin/dashboard"
                    className="shrink-0 border-l border-white/25 pl-2.5 font-serif text-sm font-semibold tracking-tight text-white sm:text-base"
                >
                    XU Library
                </Link>

                <div className="min-w-0 flex-1" aria-hidden="true" />

                <div className="flex shrink-0 items-center gap-2 border-l border-white/20 pl-2 sm:gap-2.5 sm:pl-3">
                    <div className="relative" ref={accountRef}>
                        <button
                            type="button"
                            onClick={() => setAccountOpen((v) => !v)}
                            className="flex max-w-[min(100vw-8rem,14rem)] items-center gap-2 rounded-lg border border-white/20 bg-white/5 px-2 py-1.5 transition hover:bg-white/10 hover:border-white/35 sm:max-w-none sm:px-3 sm:py-2"
                            aria-haspopup="menu"
                            aria-expanded={accountOpen ? 'true' : 'false'}
                            title={accountLabel}
                        >
                            <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/20">
                                <svg viewBox="0 0 24 24" className="h-4 w-4 text-white" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                    <path d="M20 21a8 8 0 1 0-16 0" />
                                    <circle cx="12" cy="8" r="4" />
                                </svg>
                            </span>
                            <span
                                className="hidden min-w-0 text-left text-sm leading-tight text-white/90 sm:block sm:max-w-[10rem] md:max-w-[14rem]"
                                title={accountLabel}
                            >
                                <span className="block truncate font-medium text-white">{user?.name}</span>
                                <span className="block truncate text-xs text-white/80">{user?.role?.name}</span>
                                {isQueueViewOnly && (
                                    <span className="mt-0.5 block truncate text-[10px] font-semibold uppercase tracking-wide text-white/90">
                                        Queue access – Approve/reject disabled
                                    </span>
                                )}
                            </span>
                            <svg viewBox="0 0 20 20" className="hidden h-4 w-4 shrink-0 text-white/80 sm:block" fill="currentColor" aria-hidden="true">
                                <path d="M5.25 7.5 10 12.25 14.75 7.5l1.5 1.5-6.25 6.25L3.75 9l1.5-1.5Z" />
                            </svg>
                        </button>

                        {accountOpen && (
                            <div
                                role="menu"
                                className="absolute right-0 z-50 mt-2 w-[min(100vw-1.5rem,16rem)] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                            >
                                <Link
                                    to="/account"
                                    onClick={() => setAccountOpen(false)}
                                    className="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50"
                                    role="menuitem"
                                >
                                    Account Settings
                                </Link>
                            </div>
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={handleLogout}
                        className="shrink-0 rounded-lg border border-white/35 bg-white/10 px-2.5 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-white/18 hover:border-white/50 sm:px-3"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </header>
    );
}

/** Nav sections only — fixed overlay drawer; no horizontal top nav. */
function AdminNavDrawer({
    isOpen,
    onClose,
    onNavLinkClick,
    drawerRef,
    drawerCloseRef,
    visibleNavItems,
    pathname,
}) {
    if (!isOpen) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50" role="presentation">
            <button
                type="button"
                className="absolute inset-0 bg-black/45"
                aria-label="Close navigation"
                onClick={onClose}
            />
            <aside
                id="admin-nav-drawer"
                ref={drawerRef}
                role="dialog"
                aria-modal="true"
                aria-label="Admin navigation"
                className="absolute left-0 top-0 flex h-full min-h-0 w-[min(100vw-16rem,20rem)] max-w-sm flex-col bg-xu-primary text-white shadow-2xl ring-1 ring-black/15 sm:w-72"
            >
                <div className="flex shrink-0 items-center justify-between gap-3 border-b border-white/15 px-4 py-4">
                    <div className="min-w-0">
                        <p className="truncate font-serif text-base font-semibold tracking-tight text-white">
                            Navigation
                        </p>
                        <p className="truncate text-xs font-medium text-white/75">Admin sections</p>
                    </div>
                    <button
                        ref={drawerCloseRef}
                        type="button"
                        className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-white/25 bg-white/10 text-white transition hover:bg-white/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-xu-gold/70"
                        onClick={onClose}
                        aria-label="Close navigation"
                    >
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>

                <AdminNavList items={visibleNavItems} pathname={pathname} onNavLinkClick={onNavLinkClick} />
            </aside>
        </div>
    );
}

/**
 * Single admin shell for all /admin/* routes. Mounted once; child pages render in <Outlet /> only.
 */
export default function AdminLayout() {
    const { user, logout, hasPermission } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();

    const [isNavOpen, setIsNavOpen] = useState(false);
    const [accountOpen, setAccountOpen] = useState(false);

    const hamburgerRef = useRef(null);
    const drawerRef = useRef(null);
    const drawerCloseRef = useRef(null);
    const accountRef = useRef(null);
    const mainScrollRef = useRef(null);

    const visibleNavItems = useMemo(() => {
        const perms = user?.permissions ?? [];
        const check = (permission) => perms.includes(permission);
        return getVisibleAdminNavItems(check);
    }, [user?.permissions]);

    const isQueueViewOnly =
        hasPermission('reservation.view_all') && !hasPermission('reservation.approve');

    const accountLabel = useMemo(() => {
        const name = user?.name || 'User';
        const role = user?.role?.name || '';
        return role ? `${name} · ${role}` : name;
    }, [user?.name, user?.role?.name]);

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    const closeNav = useCallback(() => {
        setIsNavOpen(false);
    }, []);

    const toggleNav = useCallback(() => {
        setAccountOpen(false);
        setIsNavOpen((open) => !open);
    }, []);

    const handleNavLinkClick = useCallback(() => {
        setIsNavOpen(false);
    }, []);

    useScrollContainerToTopOnRouteChange(mainScrollRef);

    useEffect(() => {
        setIsNavOpen(false);
    }, [location.pathname]);

    useEffect(() => {
        if (!accountOpen) return;
        const onDoc = (e) => {
            if (!accountRef.current?.contains(e.target)) {
                setAccountOpen(false);
            }
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, [accountOpen]);

    useEffect(() => {
        if (!isNavOpen) return;

        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const onKeyDown = (e) => {
            if (e.key === 'Escape') closeNav();
        };
        document.addEventListener('keydown', onKeyDown);
        drawerCloseRef.current?.focus?.();

        return () => {
            document.body.style.overflow = prevOverflow;
            document.removeEventListener('keydown', onKeyDown);
            hamburgerRef.current?.focus?.();
        };
    }, [isNavOpen, closeNav]);

    useEffect(() => {
        if (!isNavOpen) return;
        const onDoc = (e) => {
            if (drawerRef.current?.contains(e.target)) return;
            if (hamburgerRef.current?.contains(e.target)) return;
            closeNav();
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, [isNavOpen, closeNav]);

    return (
        <div className="flex h-screen w-full flex-col overflow-hidden bg-xu-page">
            <AdminTopBar
                navOpen={isNavOpen}
                onToggleNav={toggleNav}
                hamburgerRef={hamburgerRef}
                accountOpen={accountOpen}
                setAccountOpen={setAccountOpen}
                accountRef={accountRef}
                accountLabel={accountLabel}
                user={user}
                isQueueViewOnly={isQueueViewOnly}
                handleLogout={handleLogout}
            />

            <AdminNavDrawer
                isOpen={isNavOpen}
                onClose={closeNav}
                onNavLinkClick={handleNavLinkClick}
                drawerRef={drawerRef}
                drawerCloseRef={drawerCloseRef}
                visibleNavItems={visibleNavItems}
                pathname={location.pathname}
            />

            <main
                ref={mainScrollRef}
                className="min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-y-contain bg-xu-page [scrollbar-width:thin]"
            >
                <div className="mx-auto min-w-0 w-full max-w-7xl px-3 py-5 sm:px-4 sm:py-6 md:px-5 lg:px-6">
                    <Outlet />
                </div>
            </main>
        </div>
    );
}

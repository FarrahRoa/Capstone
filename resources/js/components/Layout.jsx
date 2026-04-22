import { Link, NavLink, useNavigate, useLocation } from 'react-router-dom';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useAuth } from '../contexts/AuthContext';

const navItemClass = ({ isActive }) =>
    [
        'inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-md px-2 py-1.5 text-sm font-medium leading-snug tracking-normal transition-all duration-150 2xl:px-2.5 2xl:py-2',
        isActive
            ? 'bg-white/20 text-white shadow-sm ring-1 ring-xu-gold/70'
            : 'text-white/85 hover:bg-white/12 hover:text-white active:bg-white/18',
    ].join(' ');

const navItemClassMobile = ({ isActive }) =>
    [
        'block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium leading-snug transition-colors duration-150',
        isActive
            ? 'bg-white/20 text-white shadow-sm ring-1 ring-xu-gold/60'
            : 'text-white/90 hover:bg-white/12 active:bg-white/18',
    ].join(' ');

export default function Layout({ children }) {
    const { user, logout, hasPermission } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [accountOpen, setAccountOpen] = useState(false);
    const [navOpen, setNavOpen] = useState(false);
    const accountRef = useRef(null);
    const canViewCalendar = hasPermission('calendar.view');
    const canCreateReservation = hasPermission('reservation.create');
    const canViewOwnReservations = hasPermission('reservation.view_own');
    const canViewReservationQueue = hasPermission('reservation.view_all');
    const canViewReports = hasPermission('reports.view');
    const canManageUsers = hasPermission('users.manage');
    const canManageSpaces = hasPermission('spaces.manage');
    const canManagePolicies = hasPermission('policies.manage');
    const canManageDeanEmails = canManagePolicies;
    const canManageOperatingHours = canManagePolicies;
    const canCloudSync = hasPermission('system.cloud_sync');

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    const accountLabel = useMemo(() => {
        const name = user?.name || 'User';
        const role = user?.role?.name || '';
        return role ? `${name} · ${role}` : name;
    }, [user?.name, user?.role?.name]);

    useEffect(() => {
        const onDoc = (e) => {
            if (!accountRef.current) return;
            if (accountRef.current.contains(e.target)) return;
            setAccountOpen(false);
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    useEffect(() => {
        setNavOpen(false);
    }, [location.pathname]);

    const closeMobileNav = () => setNavOpen(false);

    const isAdminRoute = location.pathname.startsWith('/admin');
    const isAdminPortalAccount = ['admin', 'librarian', 'student_assistant'].includes((user?.role?.slug || '').toLowerCase());
    const forceHamburgerNav = isAdminRoute || isAdminPortalAccount;

    const hasPrimaryNav =
        canViewCalendar ||
        canCreateReservation ||
        canViewOwnReservations ||
        canViewReservationQueue ||
        canViewReports ||
        canManageSpaces ||
        canManageUsers ||
        canManagePolicies ||
        canManageOperatingHours ||
        canManageDeanEmails ||
        canCloudSync;

    const navLinkItems = (itemClass, onNavigate) => (
        <>
            {canViewCalendar && (
                <NavLink to="/calendar" className={itemClass} onClick={onNavigate}>
                    Calendar
                </NavLink>
            )}
            {canCreateReservation && (
                <NavLink to="/reserve" className={itemClass} onClick={onNavigate}>
                    New Reservation
                </NavLink>
            )}
            {canViewOwnReservations && (
                <NavLink to="/my-reservations" className={itemClass} onClick={onNavigate}>
                    My Reservations
                </NavLink>
            )}
            {canViewReservationQueue && (
                <NavLink to="/admin/reservations" className={itemClass} onClick={onNavigate}>
                    Reservation Queue
                </NavLink>
            )}
            {canViewReports && (
                <NavLink to="/admin/reports" className={itemClass} onClick={onNavigate}>
                    Reports
                </NavLink>
            )}
            {canManageSpaces && (
                <NavLink to="/admin/spaces" className={itemClass} onClick={onNavigate}>
                    Spaces
                </NavLink>
            )}
            {canManageUsers && (
                <NavLink to="/admin/users" className={itemClass} onClick={onNavigate}>
                    User Management
                </NavLink>
            )}
            {canManagePolicies && (
                <NavLink to="/admin/policies" className={itemClass} onClick={onNavigate}>
                    Guidelines
                </NavLink>
            )}
            {canManageOperatingHours && (
                <NavLink to="/admin/operating-hours" className={itemClass} onClick={onNavigate}>
                    Operating Hours
                </NavLink>
            )}
            {canManageDeanEmails && (
                <NavLink to="/admin/dean-emails" className={itemClass} onClick={onNavigate}>
                    Dean Emails
                </NavLink>
            )}
            {canCloudSync && (
                <NavLink to="/admin/cloud-sync" className={itemClass} onClick={onNavigate}>
                    Cloud sync
                </NavLink>
            )}
        </>
    );

    const toggleNav = () => {
        setAccountOpen(false);
        setNavOpen((v) => !v);
    };

    const NavMenuButton = ({ className = '' }) => (
        <button
            type="button"
            className={[
                'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-white/25 bg-white/10 text-white transition hover:bg-white/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-xu-gold/70',
                className,
            ].join(' ')}
            aria-expanded={navOpen ? 'true' : 'false'}
            aria-controls="layout-primary-nav-mobile"
            aria-label={navOpen ? 'Close menu' : 'Open menu'}
            onClick={toggleNav}
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
    );

    return (
        <div className="min-h-screen min-w-0 overflow-x-hidden bg-xu-page">
            <nav className="bg-xu-primary text-white shadow-md border-b border-black/10">
                <div className="mx-auto w-full min-w-0 max-w-[100vw] px-3 sm:px-5 lg:px-8 xl:px-10">
                    <div className="flex min-h-[3.5rem] items-center gap-2 py-2 sm:gap-4">
                        <div className="flex min-h-0 min-w-0 flex-1 items-center gap-2 sm:gap-3 xl:gap-4">
                            {hasPrimaryNav && forceHamburgerNav && (
                                <div className="shrink-0">
                                    <NavMenuButton />
                                </div>
                            )}
                            <Link
                                to="/"
                                className="shrink-0 font-serif font-semibold text-sm text-white tracking-tight border-r border-white/25 pr-2 sm:pr-3 sm:text-base"
                            >
                                XU Library
                            </Link>
                            <div
                                className={[
                                    'hidden min-h-0 min-w-0 flex-1 items-center gap-x-1 py-0.5 sm:gap-x-1.5',
                                    forceHamburgerNav ? '' : '2xl:flex 2xl:flex-wrap 2xl:content-center 2xl:gap-x-2 2xl:gap-y-1',
                                ].join(' ')}
                            >
                                {!forceHamburgerNav && navLinkItems(navItemClass, undefined)}
                            </div>
                        </div>
                        <div className="relative z-10 flex shrink-0 items-center gap-2 border-l border-white/20 bg-xu-primary pl-2 sm:gap-2.5 sm:pl-4">
                            {hasPrimaryNav && !forceHamburgerNav && <NavMenuButton className="2xl:hidden" />}
                            <div className="relative" ref={accountRef}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setNavOpen(false);
                                        setAccountOpen((v) => !v);
                                    }}
                                    className="flex max-w-full items-center gap-2 rounded-lg border border-white/20 bg-white/5 px-2.5 py-1.5 transition hover:bg-white/10 hover:border-white/35 active:bg-white/15 sm:px-3 sm:py-2"
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
                                        className="hidden min-w-0 text-left text-sm leading-tight text-white/90 sm:block sm:max-w-[11rem] md:max-w-[16rem] lg:max-w-[20rem] xl:max-w-[24rem]"
                                        title={accountLabel}
                                    >
                                        <span className="block truncate font-medium text-white">{user?.name}</span>
                                        <span className="block truncate text-white/80 text-xs">{user?.role?.name}</span>
                                    </span>
                                    <svg viewBox="0 0 20 20" className="hidden h-4 w-4 shrink-0 text-white/80 sm:block" fill="currentColor" aria-hidden="true">
                                        <path d="M5.25 7.5 10 12.25 14.75 7.5l1.5 1.5-6.25 6.25L3.75 9l1.5-1.5Z" />
                                    </svg>
                                </button>

                                {accountOpen && (
                                    <div
                                        role="menu"
                                        className="absolute right-0 z-20 mt-2 max-h-[min(70vh,22rem)] w-[min(100vw-1.5rem,16rem)] overflow-y-auto overflow-x-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                                    >
                                        <Link
                                            to="/account"
                                            onClick={() => setAccountOpen(false)}
                                            className="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50"
                                            role="menuitem"
                                        >
                                            Account Settings
                                        </Link>
                                        <button
                                            type="button"
                                            className="flex w-full touch-manipulation items-center border-t border-slate-100 px-4 py-3 text-left text-sm font-medium text-slate-800 hover:bg-slate-50 sm:hidden"
                                            role="menuitem"
                                            onClick={() => {
                                                setAccountOpen(false);
                                                handleLogout();
                                            }}
                                        >
                                            Log out
                                        </button>
                                    </div>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="hidden shrink-0 rounded-lg border border-white/35 bg-white/10 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition-all hover:bg-white/18 hover:border-white/50 active:bg-white/22 sm:inline-flex"
                            >
                                Logout
                            </button>
                        </div>
                    </div>
                    {navOpen && hasPrimaryNav && (
                        <div
                            id="layout-primary-nav-mobile"
                            className={[
                                'border-t border-white/15 bg-xu-primary/95 py-3 shadow-inner backdrop-blur-sm',
                                forceHamburgerNav ? '' : '2xl:hidden',
                            ].join(' ')}
                        >
                            <div className="flex max-h-[min(70vh,28rem)] flex-col gap-0.5 overflow-y-auto overscroll-y-contain px-1 [scrollbar-width:thin]">
                                {navLinkItems(navItemClassMobile, closeMobileNav)}
                            </div>
                        </div>
                    )}
                </div>
            </nav>
            <main className="mx-auto min-w-0 w-full max-w-7xl px-3 py-5 sm:px-4 sm:py-6 md:px-5 lg:px-6">{children}</main>
        </div>
    );
}

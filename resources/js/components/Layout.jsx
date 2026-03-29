import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import AutomaticGoogleProfileEnrichment from './AutomaticGoogleProfileEnrichment';

const navItemClass = ({ isActive }) =>
    [
        'text-sm font-medium transition-colors border-b-2 -mb-px pb-0.5',
        isActive
            ? 'text-white border-xu-gold'
            : 'text-white/80 border-transparent hover:text-white hover:border-white/30',
    ].join(' ');

export default function Layout({ children }) {
    const { user, logout, hasPermission } = useAuth();
    const navigate = useNavigate();
    const canViewCalendar = hasPermission('calendar.view');
    const canCreateReservation = hasPermission('reservation.create');
    const canViewOwnReservations = hasPermission('reservation.view_own');
    const canViewReservationQueue = hasPermission('reservation.view_all');
    const canViewReports = hasPermission('reports.view');
    const canManageUsers = hasPermission('users.manage');
    const canManageSpaces = hasPermission('spaces.manage');
    const canManagePolicies = hasPermission('policies.manage');

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    return (
        <div className="min-h-screen bg-xu-page">
            <nav className="bg-xu-primary text-white shadow-md border-b border-black/10">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between min-h-14 items-center gap-4 py-2 flex-wrap">
                        <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                            <Link
                                to="/"
                                className="font-serif font-semibold text-lg text-white tracking-tight border-r border-white/25 pr-6 shrink-0"
                            >
                                XU Library
                            </Link>
                            <div className="flex flex-wrap items-center gap-x-5 gap-y-1">
                                {canViewCalendar && (
                                    <NavLink to="/calendar" className={navItemClass}>
                                        Calendar
                                    </NavLink>
                                )}
                                {canCreateReservation && (
                                    <NavLink to="/reserve" className={navItemClass}>
                                        New Reservation
                                    </NavLink>
                                )}
                                {canViewOwnReservations && (
                                    <NavLink to="/my-reservations" className={navItemClass}>
                                        My Reservations
                                    </NavLink>
                                )}
                                {canViewReservationQueue && (
                                    <NavLink to="/admin/reservations" className={navItemClass}>
                                        Reservation Queue
                                    </NavLink>
                                )}
                                {canViewReports && (
                                    <NavLink to="/admin/reports" className={navItemClass}>
                                        Reports
                                    </NavLink>
                                )}
                                {canManageSpaces && (
                                    <NavLink to="/admin/spaces" className={navItemClass}>
                                        Spaces
                                    </NavLink>
                                )}
                                {canManageUsers && (
                                    <NavLink to="/admin/users" className={navItemClass}>
                                        User Management
                                    </NavLink>
                                )}
                                {canManagePolicies && (
                                    <NavLink to="/admin/policies" className={navItemClass}>
                                        Guidelines
                                    </NavLink>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-4 shrink-0">
                            <span className="text-white/85 text-sm hidden sm:inline">
                                {user?.name} ({user?.role?.name})
                            </span>
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="text-sm text-white/90 hover:text-white border border-white/30 rounded-md px-3 py-1 hover:bg-white/10 transition-colors"
                            >
                                Logout
                            </button>
                        </div>
                    </div>
                </div>
            </nav>
            <main className="max-w-7xl mx-auto px-4 py-6">{children}</main>
            <AutomaticGoogleProfileEnrichment />
        </div>
    );
}

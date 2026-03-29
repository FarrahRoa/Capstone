import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { ui } from '../theme';

function StatCard({ label, value, loading }) {
    return (
        <div className="bg-white rounded-lg border border-slate-200/90 border-t-[3px] border-t-xu-gold/80 p-4 shadow-sm min-w-[8rem]">
            <p className={ui.sectionLabel}>{label}</p>
            <p className="text-2xl font-semibold text-xu-primary mt-1 tabular-nums">
                {loading ? '…' : value === null ? '—' : value}
            </p>
        </div>
    );
}

function ActionCard({ to, title, description }) {
    return (
        <Link
            to={to}
            className="block bg-white rounded-lg border border-slate-200/90 p-4 shadow-sm hover:border-xu-secondary/45 hover:shadow-md transition-colors"
        >
            <h3 className="font-semibold text-xu-primary">{title}</h3>
            {description && <p className="text-sm text-slate-600 mt-1">{description}</p>}
        </Link>
    );
}

export default function HomeDashboard() {
    const { user, hasPermission } = useAuth();
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState({
        myReservations: null,
        pendingApproval: null,
        pendingEmail: null,
        spacesCount: null,
        usersTotal: null,
    });
    const [guidelinesPreview, setGuidelinesPreview] = useState('');
    const [statsError, setStatsError] = useState(false);

    const canCalendar = hasPermission('calendar.view');
    const canReserve = hasPermission('reservation.create');
    const canMyRes = hasPermission('reservation.view_own');
    const canQueue = hasPermission('reservation.view_all');
    const canReports = hasPermission('reports.view');
    const canSpaces = hasPermission('spaces.manage');
    const canUsers = hasPermission('users.manage');
    const canPolicies = hasPermission('policies.manage');

    useEffect(() => {
        let cancelled = false;
        (async () => {
            setLoading(true);
            setStatsError(false);
            const next = {
                myReservations: null,
                pendingApproval: null,
                pendingEmail: null,
                spacesCount: null,
                usersTotal: null,
            };
            try {
                if (canMyRes) {
                    const { data } = await api.get('/reservations', { params: { per_page: 1 } });
                    if (!cancelled) next.myReservations = data.total ?? 0;
                }
                if (canQueue) {
                    const [appr, email] = await Promise.all([
                        api.get('/admin/reservations', { params: { status: 'pending_approval', per_page: 1 } }),
                        api.get('/admin/reservations', { params: { status: 'email_verification_pending', per_page: 1 } }),
                    ]);
                    if (!cancelled) {
                        next.pendingApproval = appr.data.total ?? 0;
                        next.pendingEmail = email.data.total ?? 0;
                    }
                }
                if (canSpaces) {
                    const { data } = await api.get('/admin/spaces');
                    if (!cancelled) next.spacesCount = Array.isArray(data) ? data.length : 0;
                }
                if (canUsers) {
                    const { data } = await api.get('/admin/users', { params: { per_page: 1 } });
                    if (!cancelled) next.usersTotal = data.total ?? 0;
                }
                if (canReserve) {
                    try {
                        const { data } = await api.get('/reservation-guidelines');
                        const c = data.content?.trim() || '';
                        if (!cancelled && c) {
                            setGuidelinesPreview(c.length > 240 ? `${c.slice(0, 240)}…` : c);
                        }
                    } catch {
                        if (!cancelled) setGuidelinesPreview('');
                    }
                }
            } catch {
                if (!cancelled) setStatsError(true);
            } finally {
                if (!cancelled) {
                    setStats(next);
                    setLoading(false);
                }
            }
        })();
        return () => {
            cancelled = true;
        };
    }, [user, canMyRes, canQueue, canSpaces, canUsers, canReserve]);

    const actionItems = useMemo(() => {
        const items = [];
        if (canCalendar) {
            items.push({
                to: '/calendar',
                title: 'Calendar',
                description: 'See room availability by date.',
            });
        }
        if (canReserve) {
            items.push({
                to: '/reserve',
                title: 'New reservation',
                description: 'Book a library space.',
            });
        }
        if (canMyRes) {
            items.push({
                to: '/my-reservations',
                title: 'My reservations',
                description: 'View and track your bookings.',
            });
        }
        if (canQueue) {
            items.push({
                to: '/admin/reservations',
                title: 'Reservation queue',
                description: 'Review and approve requests.',
            });
        }
        if (canReports) {
            items.push({
                to: '/admin/reports',
                title: 'Reports',
                description: 'Usage summaries and export.',
            });
        }
        if (canSpaces) {
            items.push({
                to: '/admin/spaces',
                title: 'Spaces',
                description: 'Manage rooms and availability.',
            });
        }
        if (canUsers) {
            items.push({
                to: '/admin/users',
                title: 'User management',
                description: 'Roles and room eligibility.',
            });
        }
        if (canPolicies) {
            items.push({
                to: '/admin/policies',
                title: 'Guidelines',
                description: 'Edit reservation policy text.',
            });
        }
        return items;
    }, [canCalendar, canReserve, canMyRes, canQueue, canReports, canSpaces, canUsers, canPolicies]);

    return (
        <div>
            <h1 className={`${ui.pageTitle} mb-1`}>Dashboard</h1>
            <p className="text-lg text-slate-800 mb-1">Welcome, {user?.name || 'user'}</p>
            <p className="text-slate-600 mb-6">
                {user?.role?.name ? `${user.role.name} · ` : ''}
                Quick access to what you can do in the library reservation system.
            </p>

            {(canMyRes || canQueue || canSpaces || canUsers) && (
                <section className="mb-8">
                    <h2 className={`${ui.sectionLabel} mb-3`}>At a glance</h2>
                    {statsError && (
                        <p className="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-3">
                            Some summary counts could not be loaded. Shortcuts below still work.
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        {canMyRes && (
                            <StatCard label="My reservations" value={stats.myReservations} loading={loading} />
                        )}
                        {canQueue && (
                            <>
                                <StatCard label="Pending approval" value={stats.pendingApproval} loading={loading} />
                                <StatCard label="Awaiting email confirm" value={stats.pendingEmail} loading={loading} />
                            </>
                        )}
                        {canSpaces && (
                            <StatCard label="Spaces" value={stats.spacesCount} loading={loading} />
                        )}
                        {canUsers && (
                            <StatCard label="Users" value={stats.usersTotal} loading={loading} />
                        )}
                    </div>
                </section>
            )}

            <section className="mb-8">
                <h2 className={`${ui.sectionLabel} mb-3`}>Shortcuts</h2>
                <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    {actionItems.map((item) => (
                        <ActionCard key={item.to} {...item} />
                    ))}
                </div>
                {actionItems.length === 0 && (
                    <p className="text-slate-600 text-sm">No actions available for your account.</p>
                )}
            </section>

            {canReserve && guidelinesPreview && (
                <section className="bg-white border border-slate-200/90 rounded-lg p-4 shadow-sm border-l-4 border-l-xu-primary/70">
                    <h2 className="text-sm font-semibold text-xu-primary mb-2">Guidelines preview</h2>
                    <p className="text-sm text-slate-700 whitespace-pre-wrap">{guidelinesPreview}</p>
                    <Link to="/reserve" className={`inline-block mt-3 text-sm ${ui.linkAccent}`}>
                        Go to New reservation →
                    </Link>
                </section>
            )}
        </div>
    );
}

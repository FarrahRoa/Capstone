import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { unwrapData } from '../utils/apiEnvelope';
import { useAuth } from '../contexts/AuthContext';
import DeferredMount from '../components/DeferredMount';
import { ui } from '../theme';

const HomeDashboardDeferredSections = lazy(() => import('../components/dashboard/HomeDashboardDeferredSections'));

let apiClientPromise = null;
async function getApiClient() {
    if (!apiClientPromise) {
        apiClientPromise = import('../api').then((m) => m.default || m);
    }
    return apiClientPromise;
}

const EMPTY_STATS = Object.freeze({
    myReservations: null,
    pendingApproval: null,
    pendingEmail: null,
    reservationsTotal: null,
    spacesCount: null,
    usersTotal: null,
});

const ACCENT = {
    pending: 'bg-xu-gold/25 text-xu-primary',
    email: 'bg-amber-100/90 text-amber-950',
    total: 'bg-xu-primary/12 text-xu-primary',
    spaces: 'bg-xu-secondary/12 text-xu-secondary',
    users: 'bg-xu-gold/20 text-xu-secondary',
    mine: 'bg-slate-200/70 text-xu-primary',
};

function SummaryMetricCard({ label, value, loading, iconLetter, accentClass }) {
    return (
        <div className="flex gap-4 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm min-h-[5.25rem] items-center">
            <div
                className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-xs font-bold tracking-tight ${accentClass}`}
                aria-hidden
            >
                {iconLetter}
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide">{label}</p>
                <p className="text-2xl font-semibold text-xu-primary mt-0.5 tabular-nums leading-tight">
                    {loading ? '…' : value === null ? '—' : value}
                </p>
            </div>
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
        reservationsTotal: null,
        spacesCount: null,
        usersTotal: null,
    });
    const [statsError, setStatsError] = useState(false);

    const canCalendar = hasPermission('calendar.view');
    const canReserve = hasPermission('reservation.create');
    const canMyRes = hasPermission('reservation.view_own');
    const canQueue = hasPermission('reservation.view_all');
    const canReports = hasPermission('reports.view');
    const canSpaces = hasPermission('spaces.manage');
    const canUsers = hasPermission('users.manage');
    const canPolicies = hasPermission('policies.manage');

    const isAdminContext = canQueue || canReports || canSpaces || canUsers || canPolicies;

    const needsSummaryStats = canMyRes || canQueue || canSpaces || canUsers;

    useEffect(() => {
        if (!needsSummaryStats) {
            setLoading(false);
            setStatsError(false);
            setStats(EMPTY_STATS);
            return undefined;
        }

        let cancelled = false;
        /** @type {number | null} */
        let timeoutId = null;
        /** @type {number | null} */
        let idleId = null;
        /** @type {any} */
        const w = window;

        setLoading((v) => (v ? v : true));
        setStatsError(false);

        const load = async () => {
            try {
                const api = await getApiClient();
                const { data } = await api.get('/dashboard/summary');
                const d = unwrapData(data) ?? {};
                if (cancelled) return;
                setStats({
                    myReservations: canMyRes ? d.my_active_reservations ?? 0 : null,
                    pendingApproval: canQueue ? d.pending_approval ?? 0 : null,
                    pendingEmail: canQueue ? d.email_verification_pending ?? 0 : null,
                    reservationsTotal: canQueue ? d.reservations_total ?? 0 : null,
                    spacesCount: canSpaces ? d.spaces_count ?? 0 : null,
                    usersTotal: canUsers ? d.users_total ?? 0 : null,
                });
            } catch {
                if (!cancelled) {
                    setStatsError(true);
                    setStats(EMPTY_STATS);
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        };

        // Keep above-the-fold render cheap: schedule the stats fetch for idle/after-paint.
        if (typeof w.requestIdleCallback === 'function') {
            idleId = w.requestIdleCallback(() => load(), { timeout: 1200 });
        } else {
            timeoutId = w.setTimeout(() => load(), 0);
        }

        return () => {
            cancelled = true;
            if (timeoutId != null) window.clearTimeout(timeoutId);
            if (idleId != null) w.cancelIdleCallback?.(idleId);
        };
    }, [user, needsSummaryStats, canMyRes, canQueue, canSpaces, canUsers]);

    /** Non-admin and hybrid shortcuts (includes My reservations). */
    const actionItems = useMemo(() => {
        const items = [];
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
    }, [canMyRes, canQueue, canReports, canSpaces, canUsers, canPolicies]);

    /**
     * Admin dashboard shortcuts: fixed priority order, no My reservations (nav still has it).
     */
    const adminShortcutItems = useMemo(() => {
        const items = [];
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
    }, [canQueue, canReports, canSpaces, canUsers, canPolicies]);

    const shortcutItems = isAdminContext ? adminShortcutItems : actionItems;

    const hasAdminSummaryStats = isAdminContext && (canQueue || canSpaces || canUsers);
    const hasUserSummaryStats = !isAdminContext && (canMyRes || canQueue || canSpaces || canUsers);
    const hasSummaryStats = hasAdminSummaryStats || hasUserSummaryStats;

    const welcomeLeadNonAdmin = canCalendar
        ? 'Pick a date and room below to see availability and start a reservation.'
        : 'Quick access to what you can do in the library reservation system.';

    const statsPanel =
        hasSummaryStats && (
            <section className="rounded-xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm">
                <div className="mb-5">
                    <h2 className={`${ui.sectionLabel} mb-1`}>At a glance</h2>
                    <p className="text-sm text-slate-600">
                        {isAdminContext
                            ? 'Key volumes for monitoring reservations, spaces, and accounts.'
                            : 'A quick snapshot of your library activity.'}
                    </p>
                </div>
                {statsError && (
                    <p className="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-4">
                        Some summary counts could not be loaded. Shortcuts below still work.
                    </p>
                )}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-5">
                    {isAdminContext && canQueue && (
                        <>
                            <SummaryMetricCard
                                label="All reservations"
                                value={stats.reservationsTotal}
                                loading={loading}
                                iconLetter="AR"
                                accentClass={ACCENT.total}
                            />
                            <SummaryMetricCard
                                label="Pending approval"
                                value={stats.pendingApproval}
                                loading={loading}
                                iconLetter="PA"
                                accentClass={ACCENT.pending}
                            />
                            <SummaryMetricCard
                                label="Awaiting email confirm"
                                value={stats.pendingEmail}
                                loading={loading}
                                iconLetter="EV"
                                accentClass={ACCENT.email}
                            />
                        </>
                    )}
                    {!isAdminContext && canQueue && (
                        <>
                            <SummaryMetricCard
                                label="Pending approval"
                                value={stats.pendingApproval}
                                loading={loading}
                                iconLetter="PA"
                                accentClass={ACCENT.pending}
                            />
                            <SummaryMetricCard
                                label="Awaiting email confirm"
                                value={stats.pendingEmail}
                                loading={loading}
                                iconLetter="EV"
                                accentClass={ACCENT.email}
                            />
                            <SummaryMetricCard
                                label="All reservations"
                                value={stats.reservationsTotal}
                                loading={loading}
                                iconLetter="AR"
                                accentClass={ACCENT.total}
                            />
                        </>
                    )}
                    {canSpaces && (
                        <SummaryMetricCard
                            label="Spaces"
                            value={stats.spacesCount}
                            loading={loading}
                            iconLetter="SP"
                            accentClass={ACCENT.spaces}
                        />
                    )}
                    {canUsers && (
                        <SummaryMetricCard
                            label="Users"
                            value={stats.usersTotal}
                            loading={loading}
                            iconLetter="UR"
                            accentClass={ACCENT.users}
                        />
                    )}
                    {!isAdminContext && canMyRes && (
                        <SummaryMetricCard
                            label="My reservations"
                            value={stats.myReservations}
                            loading={loading}
                            iconLetter="MR"
                            accentClass={ACCENT.mine}
                        />
                    )}
                </div>
            </section>
        );

    const shortcutsSection = (
        <section className="rounded-xl border border-dashed border-slate-200/90 bg-white/80 p-5 sm:p-6 shadow-sm">
            <h2 className={`${ui.sectionLabel} mb-1`}>{isAdminContext ? 'Admin shortcuts' : 'Shortcuts'}</h2>
            <p className="text-sm text-slate-600 mb-4">
                {isAdminContext ? 'Jump to primary admin tools.' : 'Quick links for your account.'}
            </p>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3">
                {shortcutItems.map((item) => (
                    <ActionCard key={item.to} {...item} />
                ))}
            </div>
            {shortcutItems.length === 0 && (
                <p className="text-slate-600 text-sm">No actions available for your account.</p>
            )}
        </section>
    );

    return (
        <div className="min-w-0 space-y-8">
            {/* 1. Page header / welcome */}
            <header className="space-y-2">
                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    {isAdminContext ? 'Admin / Dashboard' : 'Home / Dashboard'}
                </p>
                <h1 className={ui.pageTitle}>Dashboard</h1>
                <p className="text-lg text-slate-800 font-medium">Hi {user?.name || 'user'}!</p>
                {isAdminContext ? (
                    <p className="text-slate-600 text-sm sm:text-base max-w-3xl leading-relaxed">
                        This is your <span className="font-medium text-slate-800">admin control center</span> for the XU
                        Library reservation system—monitor reservations, spaces, people, and activity, then open tools
                        below as needed.
                        {user?.role?.name ? (
                            <>
                                {' '}
                                <span className="text-slate-500">({user.role.name})</span>
                            </>
                        ) : null}
                    </p>
                ) : (
                    <p className="text-slate-600 text-sm sm:text-base max-w-3xl leading-relaxed">
                        {user?.role?.name ? <span className="font-medium text-slate-700">{user.role.name}</span> : null}
                        {user?.role?.name ? ' · ' : ''}
                        {welcomeLeadNonAdmin}
                    </p>
                )}
            </header>

            {/* 2. Primary summary metric cards */}
            {statsPanel}

            {/* 3. Primary admin actions / shortcuts (secondary emphasis vs metrics) */}
            {shortcutsSection}

            {/* 4. Schedule overview / calendar */}
            <DeferredMount rootMargin="240px" idleTimeoutMs={1800} placeholder={null}>
                <Suspense fallback={null}>
                    <HomeDashboardDeferredSections
                        user={user}
                        hasPermission={hasPermission}
                        isAdminContext={isAdminContext}
                        canCalendar={canCalendar}
                        canReserve={canReserve}
                        canQueue={canQueue}
                        canUsers={canUsers}
                        loading={loading}
                        statsError={statsError}
                    />
                </Suspense>
            </DeferredMount>

            {/* 7. Optional footer — omitted (no additional real data source). */}
        </div>
    );
}

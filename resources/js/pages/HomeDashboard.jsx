import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import { paginatorRows, unwrapData } from '../utils/apiEnvelope';
import { useAuth } from '../contexts/AuthContext';
import DeferredMount from '../components/DeferredMount';
import { isAdminScheduleViewer } from '../utils/isAdminScheduleViewer';
import { ui } from '../theme';

const BookingCalendar = lazy(() => import('../components/booking/BookingCalendar'));
const AdminScheduleOverview = lazy(() => import('../components/booking/AdminScheduleOverview'));
const AdminDashboardPanels = lazy(() => import('../components/dashboard/AdminDashboardPanels'));

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
    const [spaces, setSpaces] = useState([]);
    const [spacesLoadError, setSpacesLoadError] = useState(false);
    const [scheduleMounted, setScheduleMounted] = useState(false);
    const [adminPanelsMounted, setAdminPanelsMounted] = useState(false);
    const [adminScheduleExpanded, setAdminScheduleExpanded] = useState(false);
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState({
        myReservations: null,
        pendingApproval: null,
        pendingEmail: null,
        reservationsTotal: null,
        spacesCount: null,
        usersTotal: null,
    });
    const [guidelinesPreview, setGuidelinesPreview] = useState('');
    const [statsError, setStatsError] = useState(false);
    const [recentUsers, setRecentUsers] = useState([]);
    const [recentLogs, setRecentLogs] = useState([]);
    const [feedLoading, setFeedLoading] = useState(false);
    const [feedError, setFeedError] = useState(false);

    const canCalendar = hasPermission('calendar.view');
    const canReserve = hasPermission('reservation.create');
    const canMyRes = hasPermission('reservation.view_own');
    const canQueue = hasPermission('reservation.view_all');
    const canReports = hasPermission('reports.view');
    const canSpaces = hasPermission('spaces.manage');
    const canUsers = hasPermission('users.manage');
    const canPolicies = hasPermission('policies.manage');
    const adminSchedule = isAdminScheduleViewer(user, hasPermission);

    const isAdminContext = canQueue || canReports || canSpaces || canUsers || canPolicies;

    useEffect(() => {
        // The schedule UI is the heavy part of the dashboard; don't fetch its inputs
        // until the schedule section actually mounts.
        if (!canCalendar || !scheduleMounted) {
            setSpaces([]);
            return;
        }
        api.get('/spaces', { params: adminSchedule ? { operational: 1 } : {} })
            .then(({ data }) => {
                const list = unwrapData(data);
                setSpaces(Array.isArray(list) ? list : []);
                setSpacesLoadError(false);
            })
            .catch(() => {
                setSpaces([]);
                setSpacesLoadError(true);
            });
    }, [canCalendar, adminSchedule, scheduleMounted]);

    useEffect(() => {
        let cancelled = false;
        (async () => {
            setLoading(true);
            setStatsError(false);
            if (!canUsers) {
                setRecentUsers([]);
            }
            const next = {
                myReservations: null,
                pendingApproval: null,
                pendingEmail: null,
                reservationsTotal: null,
                spacesCount: null,
                usersTotal: null,
            };
            try {
                if (canMyRes) {
                    const { data } = await api.get('/reservations/active-count');
                    if (!cancelled) next.myReservations = data?.data?.count ?? 0;
                }
                if (canQueue) {
                    const [appr, email, allRecent] = await Promise.all([
                        api.get('/admin/reservations', { params: { status: 'pending_approval', per_page: 1 } }),
                        api.get('/admin/reservations', { params: { status: 'email_verification_pending', per_page: 1 } }),
                        api.get('/admin/reservations', { params: { per_page: 5 } }),
                    ]);
                    if (!cancelled) {
                        next.pendingApproval = appr.data.total ?? 0;
                        next.pendingEmail = email.data.total ?? 0;
                        next.reservationsTotal = allRecent.data.total ?? 0;
                    }
                }
                if (canSpaces) {
                    const { data } = await api.get('/admin/spaces');
                    const list = unwrapData(data);
                    if (!cancelled) next.spacesCount = Array.isArray(list) ? list.length : 0;
                }
                if (canUsers) {
                    // The summary card needs a count, but the full "recent users" table is below-the-fold.
                    // Fetch the smallest payload until the admin panels mount.
                    const perPage = adminPanelsMounted ? 8 : 1;
                    const { data } = await api.get('/admin/users', { params: { sort: 'recent', per_page: perPage } });
                    if (!cancelled) {
                        next.usersTotal = data.total ?? 0;
                        if (adminPanelsMounted) {
                            setRecentUsers(paginatorRows(data));
                        } else {
                            setRecentUsers([]);
                        }
                    }
                }
                if (canReserve) {
                    try {
                        const { data } = await api.get('/reservation-guidelines');
                        const doc = unwrapData(data);
                        const c = doc && doc.content ? String(doc.content).trim() : '';
                        if (!cancelled && c) {
                            setGuidelinesPreview(c.length > 240 ? `${c.slice(0, 240)}…` : c);
                        }
                    } catch {
                        if (!cancelled) setGuidelinesPreview('');
                    }
                }
            } catch {
                if (!cancelled) {
                    setStatsError(true);
                    setRecentUsers([]);
                }
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
    }, [user, canMyRes, canQueue, canSpaces, canUsers, canReserve, adminPanelsMounted]);

    /** Reservation audit feed only; recent users rows come from the stats effect (same /admin/users response). */
    useEffect(() => {
        if (!isAdminContext || !canQueue || !adminPanelsMounted) {
            setRecentLogs([]);
            setFeedLoading(false);
            return;
        }
        let cancelled = false;
        (async () => {
            setFeedLoading(true);
            setFeedError(false);
            try {
                const { data } = await api.get('/admin/activity/reservation-logs');
                if (cancelled) return;
                const logsPayload = unwrapData(data) ?? [];
                setRecentLogs(Array.isArray(logsPayload) ? logsPayload : []);
            } catch {
                if (!cancelled) {
                    setFeedError(true);
                    setRecentLogs([]);
                }
            } finally {
                if (!cancelled) setFeedLoading(false);
            }
        })();
        return () => {
            cancelled = true;
        };
    }, [isAdminContext, canQueue, adminPanelsMounted]);

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

    const scheduleIntro = adminSchedule
        ? {
              title: 'Schedule overview',
              body: 'Select a date to see every active library space in one list. Each space shows reserved vs available slots using the same colors as the overview legend. This dashboard calendar is read-only.',
          }
        : {
              title: 'Book a space',
              body: 'Choose a library space, select a date on the calendar, then pick an available time. Reserved times appear dimmed and are not selectable; free slots open the reservation form with your choices filled in.',
          };

    const welcomeLeadNonAdmin = canCalendar
        ? adminSchedule
            ? 'Use the overview below to monitor reservations across all library spaces for any day.'
            : 'Pick a date and room below to see availability and start a reservation.'
        : 'Quick access to what you can do in the library reservation system.';

    const scheduleCard = canCalendar && (
        <section
            id="dashboard-schedule"
            className="min-w-0 rounded-xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm"
            aria-labelledby="home-booking-heading"
        >
            <h2 id="home-booking-heading" className={`${ui.sectionLabel} mb-1`}>
                {scheduleIntro.title}
            </h2>
            {adminSchedule ? (
                <div className="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 text-sm text-slate-600">
                    <p className="max-w-3xl leading-relaxed">{scheduleIntro.body}</p>
                    {!adminScheduleExpanded ? (
                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                onClick={() => {
                                    setAdminScheduleExpanded(true);
                                    setScheduleMounted(true);
                                }}
                                className="inline-flex items-center justify-center rounded-lg bg-xu-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-xu-secondary transition-colors"
                            >
                                Load schedule overview
                            </button>
                            <span className="text-xs text-slate-500">
                                Loads the full all-spaces schedule (heavier).
                            </span>
                        </div>
                    ) : (
                        <Suspense
                            fallback={
                                <div className="mt-4 h-10 w-full rounded-lg bg-white/70 border border-slate-200/70" aria-hidden />
                            }
                        >
                            <div className="mt-5">
                                <AdminScheduleOverview spaces={spaces} spacesLoadError={spacesLoadError} embedded />
                            </div>
                        </Suspense>
                    )}
                </div>
            ) : (
                <DeferredMount
                    // For Lighthouse (and real users), this section should not mount during initial paint.
                    // The booking schedule UI is heavy; mount it only when it's actually near the viewport.
                    rootMargin="0px"
                    idleTimeoutMs={6000}
                    onMount={() => setScheduleMounted(true)}
                    placeholder={
                        <div className="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 text-sm text-slate-600">
                            <p className="max-w-3xl leading-relaxed">{scheduleIntro.body}</p>
                            <div className="mt-3 h-10 w-full rounded-lg bg-white/70 border border-slate-200/70" aria-hidden />
                        </div>
                    }
                >
                    <Suspense
                        fallback={
                            <div className="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 text-sm text-slate-600">
                                <p className="max-w-3xl leading-relaxed">{scheduleIntro.body}</p>
                                <div className="mt-3 h-10 w-full rounded-lg bg-white/70 border border-slate-200/70" aria-hidden />
                            </div>
                        }
                    >
                        <p className="text-sm text-slate-600 mb-5 max-w-3xl leading-relaxed">{scheduleIntro.body}</p>
                        <BookingCalendar user={user} spaces={spaces} spacesLoadError={spacesLoadError} embedded />
                    </Suspense>
                </DeferredMount>
            )}
        </section>
    );

    const guidelinesBlock =
        canReserve && guidelinesPreview ? (
            <section className="rounded-xl border border-slate-200/90 bg-slate-50/80 p-5 shadow-sm border-l-[3px] border-l-xu-gold/70">
                <h2 className="text-xs font-semibold text-xu-secondary uppercase tracking-wide mb-2">Guidelines preview</h2>
                <p className="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed">{guidelinesPreview}</p>
                {canCalendar ? (
                    <a href="#dashboard-schedule" className={`inline-block mt-4 text-sm ${ui.linkAccent}`}>
                        {adminSchedule ? 'Jump to schedule overview →' : 'Jump to booking calendar →'}
                    </a>
                ) : (
                    <Link to="/reserve" className={`inline-block mt-4 text-sm ${ui.linkAccent}`}>
                        Go to New reservation →
                    </Link>
                )}
            </section>
        ) : null;

    const adminPanels =
        isAdminContext && (canUsers || canQueue) ? (
            <DeferredMount
                rootMargin="120px"
                onMount={() => setAdminPanelsMounted(true)}
                placeholder={null}
            >
                <Suspense fallback={null}>
                    <AdminDashboardPanels
                        canUsers={canUsers}
                        canQueue={canQueue}
                        isAdminContext={isAdminContext}
                        loading={loading}
                        statsError={statsError}
                        recentUsers={recentUsers}
                        feedLoading={feedLoading}
                        feedError={feedError}
                        recentLogs={recentLogs}
                    />
                </Suspense>
            </DeferredMount>
        ) : null;

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
            {scheduleCard}

            {/* 5-6. Admin-only panels (lazy chunk) */}
            {adminPanels}

            {/* 7. Guidelines preview (supporting) */}
            {guidelinesBlock}

            {/* 7. Optional footer — omitted (no additional real data source). */}
        </div>
    );
}

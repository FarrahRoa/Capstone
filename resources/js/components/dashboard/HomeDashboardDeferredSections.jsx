import { Suspense, lazy, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api';
import { paginatorRows, unwrapData } from '../../utils/apiEnvelope';
import DeferredMount from '../DeferredMount';
import { isAdminScheduleViewer } from '../../utils/isAdminScheduleViewer';
import { ui } from '../../theme';

const BookingCalendar = lazy(() => import('../booking/BookingCalendar'));
const AdminScheduleOverview = lazy(() => import('../booking/AdminScheduleOverview'));
const AdminDashboardPanels = lazy(() => import('./AdminDashboardPanels'));

export default function HomeDashboardDeferredSections({
    user,
    hasPermission,
    isAdminContext,
    canCalendar,
    canReserve,
    canQueue,
    canUsers,
    loading,
    statsError,
}) {
    const [spaces, setSpaces] = useState([]);
    const [spacesLoadError, setSpacesLoadError] = useState(false);
    const [scheduleMounted, setScheduleMounted] = useState(false);
    const [adminPanelsMounted, setAdminPanelsMounted] = useState(false);
    const [adminScheduleExpanded, setAdminScheduleExpanded] = useState(false);

    const [guidelinesPreview, setGuidelinesPreview] = useState('');
    const [recentUsers, setRecentUsers] = useState([]);
    const [recentLogs, setRecentLogs] = useState([]);
    const [feedLoading, setFeedLoading] = useState(false);
    const [feedError, setFeedError] = useState(false);

    const adminSchedule = isAdminScheduleViewer(user, hasPermission);

    useEffect(() => {
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
        if (!canReserve) {
            setGuidelinesPreview('');
            return undefined;
        }
        let cancelled = false;
        const load = async () => {
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
        };

        const w = /** @type {any} */ (window);
        if (typeof w.requestIdleCallback === 'function') {
            const id = w.requestIdleCallback(() => load(), { timeout: 3000 });
            return () => {
                cancelled = true;
                w.cancelIdleCallback?.(id);
            };
        }

        const t = window.setTimeout(() => load(), 900);
        return () => {
            cancelled = true;
            window.clearTimeout(t);
        };
    }, [canReserve]);

    useEffect(() => {
        if (!isAdminContext || !canUsers || !adminPanelsMounted) {
            setRecentUsers([]);
            return;
        }
        let cancelled = false;
        api.get('/admin/users', { params: { sort: 'recent', per_page: 8 } })
            .then(({ data }) => {
                if (cancelled) return;
                setRecentUsers(paginatorRows(data));
            })
            .catch(() => {
                if (!cancelled) setRecentUsers([]);
            });
        return () => {
            cancelled = true;
        };
    }, [isAdminContext, canUsers, adminPanelsMounted]);

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

    const scheduleIntro = adminSchedule
        ? {
              title: 'Schedule overview',
              body: 'Select a date to see every active library space in one list. Each space shows reserved vs available slots using the same colors as the overview legend. This dashboard calendar is read-only.',
          }
        : {
              title: 'Book a space',
              body: 'Choose a library space, select a date on the calendar, then pick an available time. Reserved times appear dimmed and are not selectable; free slots open the reservation form with your choices filled in.',
          };

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
                            <span className="text-xs text-slate-500">Loads the full all-spaces schedule (heavier).</span>
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

    const adminPanels =
        isAdminContext && (canUsers || canQueue) ? (
            <DeferredMount rootMargin="120px" onMount={() => setAdminPanelsMounted(true)} placeholder={null}>
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

    return (
        <>
            {scheduleCard}
            {adminPanels}
            {guidelinesBlock}
            {isAdminContext && canQueue && adminPanelsMounted && feedError && !feedLoading ? (
                <p className="sr-only">Recent activity could not be loaded.</p>
            ) : null}
        </>
    );
}


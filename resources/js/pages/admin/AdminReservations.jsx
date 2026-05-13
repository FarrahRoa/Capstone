import { useState, useEffect } from 'react';
import api from '../../api';
import { paginatorRows, unwrapData } from '../../utils/apiEnvelope';
import { useAuth } from '../../contexts/AuthContext';
import { getReservationActionLabel, getReservationStatusBadgeClass, getReservationStatusLabel } from '../../utils/reservationVocabulary';
import { formatLogTime, formatReservationRange } from '../../utils/timeDisplay';
import { ui } from '../../theme';

const GLOBAL_OVERRIDE_STATUSES = new Set([
    'pending_approval',
    'pending_dean_approval',
    'email_verification_pending',
    'approved',
]);

function isoToDatetimeLocalValue(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function AdminReservations() {
    const { user, hasPermission } = useAuth();
    const [reservations, setReservations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('pending_approval');
    const [rejectReason, setRejectReason] = useState('');
    const [actionId, setActionId] = useState(null);
    const [feedback, setFeedback] = useState(null);
    const [confabPick, setConfabPick] = useState({});
    const [assignOptions, setAssignOptions] = useState({});
    const [overrideModal, setOverrideModal] = useState(null);
    const [overrideForm, setOverrideForm] = useState({
        space_id: '',
        start_at: '',
        end_at: '',
        reason: '',
    });
    const [spacesList, setSpacesList] = useState([]);
    const [spacesError, setSpacesError] = useState(false);

    const isSystemAdmin = String(user?.role?.slug || '').toLowerCase() === 'admin';

    const load = () => {
        setLoading(true);
        const params = statusFilter ? { status: statusFilter } : {};
        api.get('/admin/reservations', { params })
            .then(({ data }) => setReservations(paginatorRows(data)))
            .catch(() => {
                setReservations([]);
                setFeedback({ type: 'error', text: 'Failed to load reservation queue.' });
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => load(), [statusFilter]);

    useEffect(() => {
        if (!isSystemAdmin) {
            setSpacesList([]);
            return;
        }
        api.get('/spaces')
            .then(({ data }) => {
                const list = unwrapData(data);
                setSpacesList(Array.isArray(list) ? list : []);
                setSpacesError(false);
            })
            .catch(() => {
                setSpacesList([]);
                setSpacesError(true);
            });
    }, [isSystemAdmin]);

    const canApprove = hasPermission('reservation.approve');
    const canReject = hasPermission('reservation.reject');
    const canOverride = hasPermission('reservation.override');
    const queueViewOnly =
        hasPermission('reservation.view_all') &&
        !canApprove &&
        !canReject &&
        !canOverride;

    const clearFeedback = () => setFeedback(null);

    const loadAssignOptions = async (id) => {
        if (Object.prototype.hasOwnProperty.call(assignOptions, id)) return;
        setAssignOptions((o) => ({ ...o, [id]: null }));
        try {
            const { data: body } = await api.get(`/admin/reservations/${id}/assignable-confab-spaces`);
            const list = unwrapData(body);
            setAssignOptions((o) => ({ ...o, [id]: Array.isArray(list) ? list : [] }));
        } catch {
            setAssignOptions((o) => ({ ...o, [id]: [] }));
        }
    };

    const needsConfabAssign = (r) => r.status === 'pending_approval' && r.space?.is_confab_pool;

    const canGlobalOverride = (r) => isSystemAdmin && GLOBAL_OVERRIDE_STATUSES.has(r.status);

    const openGlobalOverrideModal = (r) => {
        if (!canGlobalOverride(r)) return;
        clearFeedback();
        setOverrideModal(r);
        setOverrideForm({
            space_id: String(r.space?.id || ''),
            start_at: isoToDatetimeLocalValue(r.start_at),
            end_at: isoToDatetimeLocalValue(r.end_at),
            reason: '',
        });
    };

    const submitGlobalOverride = () => {
        if (!overrideModal) return;
        const reason = overrideForm.reason.trim();
        if (!reason) {
            setFeedback({ type: 'error', text: 'Override reason is required.' });
            return;
        }
        const sid = Number(overrideForm.space_id);
        if (!sid) {
            setFeedback({ type: 'error', text: 'Choose a library space.' });
            return;
        }
        if (!overrideForm.start_at || !overrideForm.end_at) {
            setFeedback({ type: 'error', text: 'Start and end times are required.' });
            return;
        }
        const startIso = new Date(overrideForm.start_at).toISOString();
        const endIso = new Date(overrideForm.end_at).toISOString();
        if (new Date(endIso) <= new Date(startIso)) {
            setFeedback({ type: 'error', text: 'End must be after start.' });
            return;
        }
        clearFeedback();
        setActionId(overrideModal.id);
        api.post(`/admin/reservations/${overrideModal.id}/override`, {
            reason,
            space_id: sid,
            start_at: startIso,
            end_at: endIso,
        })
            .then(({ data }) => {
                setActionId(null);
                setOverrideModal(null);
                setFeedback({ type: 'success', text: data?.message || 'Global override applied.' });
                load();
            })
            .catch((err) => {
                setActionId(null);
                const msg =
                    err.response?.data?.errors?.reason?.[0]
                    || err.response?.data?.errors?.space_id?.[0]
                    || err.response?.data?.errors?.slot?.[0]
                    || err.response?.data?.message
                    || 'Failed to apply global override.';
                setFeedback({ type: 'error', text: msg });
            });
    };

    const approve = (r) => {
        if (!canApprove) return;
        clearFeedback();
        const id = r.id;
        if (needsConfabAssign(r)) {
            const sid = Number(confabPick[id]);
            if (!sid) {
                setFeedback({ type: 'error', text: 'Choose a specific confab room before approving.' });
                return;
            }
        }
        setActionId(id);
        const payload = {};
        if (needsConfabAssign(r)) {
            payload.assigned_space_id = Number(confabPick[id]);
        }
        api.post(`/admin/reservations/${id}/approve`, payload)
            .then(({ data }) => {
                setActionId(null);
                setConfabPick((p) => {
                    const next = { ...p };
                    delete next[id];
                    return next;
                });
                setFeedback({ type: 'success', text: data?.message || 'Reservation approved.' });
                load();
            })
            .catch((err) => {
                setActionId(null);
                const msg = err.response?.data?.errors?.assigned_space_id?.[0]
                    || err.response?.data?.message
                    || 'Failed to approve reservation.';
                setFeedback({ type: 'error', text: msg });
            });
    };

    const reject = (id) => {
        if (!canReject) return;
        if (!rejectReason.trim()) {
            setFeedback({ type: 'error', text: 'Please enter a rejection reason.' });
            return;
        }
        clearFeedback();
        setActionId(id);
        api.post(`/admin/reservations/${id}/reject`, { reason: rejectReason })
            .then(({ data }) => {
                setActionId(null);
                setRejectReason('');
                setFeedback({ type: 'success', text: data?.message || 'Reservation rejected.' });
                load();
            })
            .catch((err) => {
                setActionId(null);
                setFeedback({ type: 'error', text: err.response?.data?.message || 'Failed to reject reservation.' });
            });
    };

    const openReject = (id) => {
        clearFeedback();
        setActionId(id);
    };

    const cancel = (id) => {
        if (!canOverride) return;
        if (!confirm('Cancel this reservation?')) return;
        clearFeedback();
        setActionId(id);
        api.post(`/admin/reservations/${id}/cancel`)
            .then(({ data }) => {
                setActionId(null);
                setFeedback({ type: 'success', text: data?.message || 'Reservation cancelled.' });
                load();
            })
            .catch((err) => {
                setActionId(null);
                setFeedback({ type: 'error', text: err.response?.data?.message || 'Failed to cancel reservation.' });
            });
    };

    return (
        <div className="min-w-0 max-w-full">
            <h1 className={`${ui.pageTitle} mb-4`}>Reservation queue</h1>
            {queueViewOnly && (
                <div
                    className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-950"
                    role="status"
                >
                    <span className="font-semibold">Student Assistant – View only.</span> You can review requests; approving,
                    rejecting, cancelling, and overrides require a librarian or admin.
                </div>
            )}
            <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <label htmlFor="admin-res-queue-filter" className="text-sm font-medium text-slate-700 shrink-0">Filter</label>
                <select
                    id="admin-res-queue-filter"
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className={`${ui.select} w-full min-w-0 sm:w-auto sm:min-w-[14rem]`}
                >
                    <option value="">All</option>
                    <option value="email_verification_pending">Pending verification</option>
                    <option value="pending_dean_approval">Pending dean/office approval</option>
                    <option value="pending_approval">Pending approval</option>
                    <option value="approved">Approved</option>
                    <option value="overridden">Approved (admin override)</option>
                    <option value="reschedule_required">Reschedule required</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            {feedback && (
                <div className={`mb-4 text-sm p-3 rounded border ${feedback.type === 'success' ? 'text-green-700 bg-green-50 border-green-200' : 'text-red-700 bg-red-50 border-red-200'}`}>
                    {feedback.text}
                </div>
            )}
            {loading && <p className="text-slate-600">Loading…</p>}
            <div className="space-y-3">
                {(reservations.length === 0 && !loading) && <p className="text-slate-600">No reservations.</p>}
                {reservations.map((r) => (
                    <div key={r.id} className={`p-3 sm:p-4 ${ui.cardFlat} overflow-hidden`}>
                        <div className="flex flex-col gap-4 md:flex-row md:justify-between md:items-start">
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-xu-primary text-[15px] sm:text-base break-words">
                                    {r.space?.name} – {r.user?.name} ({r.user?.email})
                                </p>
                                {r.user?.mobile_number && (
                                    <p className="text-xs text-slate-600 mt-0.5">
                                        Mobile: <span className="font-medium text-slate-700">{r.user.mobile_number}</span>
                                    </p>
                                )}
                                <p className="text-sm text-slate-600">{formatReservationRange(r.start_at, r.end_at)}</p>
                                <p className="text-sm text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${getReservationStatusBadgeClass(r.status)}`}>
                                        {getReservationStatusLabel(r.status)}
                                    </span>
                                    {r.reservation_number && <span className="text-slate-500">• {r.reservation_number}</span>}
                                </p>
                                {(r.space?.slug === 'avr'
                                    || r.space?.slug === 'lobby'
                                    || r.space?.type === 'confab'
                                    || r.space?.type === 'medical_confab'
                                    || r.space?.type === 'lecture') && (
                                    <div className="mt-2 text-sm text-slate-700 space-y-1 max-w-2xl">
                                        {r.event_title && (
                                            <p>
                                                <span className="font-semibold text-slate-800">Event:</span> {r.event_title}
                                            </p>
                                        )}
                                        {r.participant_count != null && (
                                            <p>
                                                <span className="font-semibold text-slate-800">Participants:</span> {r.participant_count}
                                            </p>
                                        )}
                                        {r.event_description && (
                                            <p className="text-slate-600 whitespace-pre-wrap">
                                                <span className="font-semibold text-slate-800">Notes:</span> {r.event_description}
                                            </p>
                                        )}
                                        {r.event_request_type && (r.space?.type === 'avr' || r.space?.type === 'lobby') && (
                                            <p>
                                                <span className="font-semibold text-slate-800">Event audience:</span>{' '}
                                                {r.event_request_type === 'organization' ? 'Organization event' : r.event_request_type === 'employee' ? 'Employee event' : r.event_request_type}
                                            </p>
                                        )}
                                    </div>
                                )}
                                {needsConfabAssign(r) && (
                                    <p className="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 mt-2 max-w-xl">
                                        General confab request: assign a specific free confab room before approving.
                                    </p>
                                )}
                                {r.status === 'email_verification_pending' && (canApprove || canReject) && (
                                    <p className="text-xs text-slate-500 mt-1.5 max-w-xl">
                                        Awaiting requester email confirmation. <strong>Approve</strong> is available after they verify; <strong>Reject</strong> can decline before then.
                                    </p>
                                )}
                                {r.status === 'pending_dean_approval' && (
                                    <p className="text-xs text-violet-900 bg-violet-50 border border-violet-200 rounded px-2 py-1.5 mt-2 max-w-xl">
                                        Awaiting dean/office decision via email. Library <strong>approve</strong> and <strong>reject</strong> stay disabled until then.
                                        {isSystemAdmin ? (
                                            <> A <strong>system administrator</strong> may still run a global override if needed.</>
                                        ) : null}
                                    </p>
                                )}
                                {r.rejected_reason && (
                                    <p className="text-sm text-red-700 mt-1">Reason: {r.rejected_reason}</p>
                                )}
                                {r.logs?.length > 0 && (
                                    <div className="mt-2 border-t border-slate-100 pt-2">
                                        <p className="text-xs font-semibold text-slate-700 mb-1">History</p>
                                        <ul className="space-y-1">
                                            {r.logs.map((log) => (
                                                <li key={log.id} className="text-xs text-slate-600">
                                                    <span className="font-medium text-slate-700">{getReservationActionLabel(log.action)}</span>
                                                    {' • '}
                                                    {formatLogTime(log.created_at)}
                                                    {' • '}
                                                    {log.actor?.name || log.admin?.name || 'System'}
                                                    {log.notes ? ` • ${log.notes}` : ''}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </div>
                            <div className="flex w-full min-w-0 flex-col gap-3 md:w-auto md:shrink-0 md:items-end">
                                <div className="flex w-full flex-wrap gap-2 md:justify-end">
                                    {r.status === 'pending_approval' && canApprove && (
                                        <button
                                            type="button"
                                            onClick={() => approve(r)}
                                            disabled={actionId === r.id || (needsConfabAssign(r) && !confabPick[r.id])}
                                            className="min-h-[44px] touch-manipulation px-4 py-2 rounded-md bg-xu-primary text-white text-sm font-medium shadow-sm hover:bg-xu-secondary disabled:opacity-50 transition-colors md:min-h-0 md:px-3 md:py-1.5"
                                        >
                                            Approve
                                        </button>
                                    )}
                                    {(r.status === 'pending_approval' || r.status === 'email_verification_pending') && canReject && (
                                        <button
                                            type="button"
                                            onClick={() => openReject(r.id)}
                                            disabled={actionId !== null && actionId !== r.id}
                                            className="min-h-[44px] touch-manipulation px-4 py-2 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700 disabled:opacity-50 md:min-h-0 md:px-3 md:py-1.5"
                                        >
                                            Reject
                                        </button>
                                    )}
                                    {canGlobalOverride(r) && (
                                        <button
                                            type="button"
                                            onClick={() => openGlobalOverrideModal(r)}
                                            disabled={actionId === r.id}
                                            className="min-h-[44px] touch-manipulation px-4 py-2 rounded-md border border-xu-secondary text-xu-secondary bg-white text-sm font-medium hover:bg-xu-page disabled:opacity-50 transition-colors md:min-h-0 md:px-3 md:py-1.5"
                                        >
                                            Override approve
                                        </button>
                                    )}
                                    {canOverride && !['cancelled', 'rejected'].includes(r.status) && (
                                        <button
                                            type="button"
                                            onClick={() => cancel(r.id)}
                                            disabled={actionId === r.id}
                                            className="min-h-[44px] touch-manipulation px-4 py-2 rounded-md border border-slate-300 text-slate-700 bg-white text-sm font-medium hover:bg-slate-50 disabled:opacity-50 transition-colors md:min-h-0 md:px-3 md:py-1.5"
                                        >
                                            Cancel
                                        </button>
                                    )}
                                </div>
                                {needsConfabAssign(r) && canApprove && (
                                    <div className="flex w-full min-w-0 flex-col gap-2 sm:max-w-xs md:items-end">
                                        <label className="text-xs font-medium text-slate-600" htmlFor={`admin-res-confab-${r.id}`}>Confab room</label>
                                        <select
                                            id={`admin-res-confab-${r.id}`}
                                            value={confabPick[r.id] || ''}
                                            onFocus={() => loadAssignOptions(r.id)}
                                            onChange={(e) => setConfabPick((p) => ({ ...p, [r.id]: e.target.value }))}
                                            className={`${ui.select} w-full min-w-0 text-sm md:min-w-[10rem] md:w-auto`}
                                        >
                                            <option value="">Select…</option>
                                            {Array.isArray(assignOptions[r.id]) && assignOptions[r.id].map((s) => (
                                                <option key={s.id} value={s.id}>{s.name}</option>
                                            ))}
                                        </select>
                                        {assignOptions[r.id] === null && (
                                            <span className="text-xs text-slate-500 md:text-right">Loading rooms…</span>
                                        )}
                                        {Array.isArray(assignOptions[r.id]) && assignOptions[r.id].length === 0 && (
                                            <span className="text-xs text-red-600 md:text-right">No free confab rooms for this slot.</span>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                        {actionId === r.id && canReject && (r.status === 'pending_approval' || r.status === 'email_verification_pending') && (
                            <div className="mt-3 flex flex-col gap-2 sm:mt-2 sm:flex-row sm:flex-wrap sm:items-stretch">
                                <input
                                    type="text"
                                    value={rejectReason}
                                    onChange={(e) => setRejectReason(e.target.value)}
                                    className={`${ui.input} text-sm w-full min-w-0 sm:flex-1 sm:min-w-[12rem]`}
                                    placeholder="Rejection reason"
                                />
                                <div className="flex flex-wrap gap-2 sm:shrink-0">
                                    <button
                                        type="button"
                                        onClick={() => reject(r.id)}
                                        className="min-h-[44px] touch-manipulation flex-1 px-4 py-2 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700 md:min-h-0 md:flex-none md:px-3 md:py-1.5"
                                    >
                                        Confirm reject
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setActionId(null);
                                            setRejectReason('');
                                        }}
                                        className="min-h-[44px] touch-manipulation flex-1 px-4 py-2 rounded-md border border-slate-300 text-slate-700 bg-white text-sm hover:bg-slate-50 md:min-h-0 md:flex-none md:px-3 md:py-1.5"
                                    >
                                        Dismiss
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {overrideModal && (
                <div className="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-3 sm:p-6">
                    <button
                        type="button"
                        className="absolute inset-0 bg-black/50"
                        aria-label="Close override dialog"
                        onClick={() => !actionId && setOverrideModal(null)}
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="global-override-title"
                        className="relative z-[1] w-full max-w-lg rounded-xl border border-slate-200 bg-white p-5 shadow-xl max-h-[min(90vh,40rem)] overflow-y-auto"
                    >
                        <h2 id="global-override-title" className="text-lg font-semibold text-xu-primary mb-1">
                            Global override approve
                        </h2>
                        <p className="text-sm text-slate-600 mb-4">
                            Reassign this booking to any active space and time. A reason is required. Conflicting bookings may be
                            marked <em>Reschedule required</em> when allowed by priority rules.
                        </p>
                        {spacesError && (
                            <p className="text-sm text-red-700 mb-3">Could not load spaces. Refresh and try again.</p>
                        )}
                        <div className="space-y-3">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1" htmlFor="global-override-space">
                                    Library space
                                </label>
                                <select
                                    id="global-override-space"
                                    value={overrideForm.space_id}
                                    onChange={(e) => setOverrideForm((f) => ({ ...f, space_id: e.target.value }))}
                                    className={`${ui.select} w-full text-sm`}
                                >
                                    <option value="">Select space…</option>
                                    {spacesList
                                        .filter((s) => !s.is_confab_pool)
                                        .map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name}
                                            </option>
                                        ))}
                                </select>
                            </div>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1" htmlFor="global-override-start">
                                        Start
                                    </label>
                                    <input
                                        id="global-override-start"
                                        type="datetime-local"
                                        value={overrideForm.start_at}
                                        onChange={(e) => setOverrideForm((f) => ({ ...f, start_at: e.target.value }))}
                                        className={`${ui.input} text-sm w-full`}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1" htmlFor="global-override-end">
                                        End
                                    </label>
                                    <input
                                        id="global-override-end"
                                        type="datetime-local"
                                        value={overrideForm.end_at}
                                        onChange={(e) => setOverrideForm((f) => ({ ...f, end_at: e.target.value }))}
                                        className={`${ui.input} text-sm w-full`}
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1" htmlFor="global-override-reason">
                                    Override reason (required)
                                </label>
                                <textarea
                                    id="global-override-reason"
                                    value={overrideForm.reason}
                                    onChange={(e) => setOverrideForm((f) => ({ ...f, reason: e.target.value }))}
                                    rows={4}
                                    className={`${ui.input} text-sm w-full`}
                                    placeholder="Explain why this override is necessary…"
                                />
                            </div>
                        </div>
                        <div className="mt-5 flex flex-wrap gap-2 justify-end">
                            <button
                                type="button"
                                className="px-4 py-2 rounded-md border border-slate-300 text-slate-800 text-sm font-medium hover:bg-slate-50 disabled:opacity-50"
                                disabled={Boolean(actionId)}
                                onClick={() => setOverrideModal(null)}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="px-4 py-2 rounded-md bg-xu-primary text-white text-sm font-medium hover:bg-xu-secondary disabled:opacity-50"
                                disabled={Boolean(actionId)}
                                onClick={submitGlobalOverride}
                            >
                                {actionId ? 'Applying…' : 'Apply override'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

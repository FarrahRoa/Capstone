import { useState, useEffect } from 'react';
import api from '../../api';
import { paginatorRows, unwrapData } from '../../utils/apiEnvelope';
import { useAuth } from '../../contexts/AuthContext';
import { getReservationActionLabel, getReservationStatusBadgeClass, getReservationStatusLabel } from '../../utils/reservationVocabulary';
import { formatLogTime, formatReservationRange } from '../../utils/timeDisplay';
import { ui } from '../../theme';

export default function AdminReservations() {
    const { hasPermission } = useAuth();
    const [reservations, setReservations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('pending_approval');
    const [rejectReason, setRejectReason] = useState('');
    const [actionId, setActionId] = useState(null);
    const [feedback, setFeedback] = useState(null);
    const [confabPick, setConfabPick] = useState({});
    const [assignOptions, setAssignOptions] = useState({});

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

    const canApprove = hasPermission('reservation.approve');
    const canReject = hasPermission('reservation.reject');
    const canOverride = hasPermission('reservation.override');

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

    const overrideApprove = (r) => {
        if (!canOverride) return;
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
        api.post(`/admin/reservations/${id}/override`, payload)
            .then(({ data }) => {
                setActionId(null);
                setConfabPick((p) => {
                    const next = { ...p };
                    delete next[id];
                    return next;
                });
                setFeedback({ type: 'success', text: data?.message || 'Override applied.' });
                load();
            })
            .catch((err) => {
                setActionId(null);
                const msg = err.response?.data?.errors?.assigned_space_id?.[0]
                    || err.response?.data?.message
                    || 'Failed to apply override.';
                setFeedback({ type: 'error', text: msg });
            });
    };

    return (
        <div>
            <h1 className={`${ui.pageTitle} mb-4`}>Reservation queue</h1>
            <div className="mb-4">
                <label className="mr-2 text-sm font-medium text-slate-700">Filter</label>
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className={ui.select}>
                    <option value="">All</option>
                    <option value="email_verification_pending">Pending verification</option>
                    <option value="pending_approval">Pending approval</option>
                    <option value="approved">Approved</option>
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
                    <div key={r.id} className={`p-4 ${ui.cardFlat}`}>
                        <div className="flex justify-between items-start gap-4">
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-xu-primary">
                                    {r.space?.name} – {r.user?.name} ({r.user?.email})
                                </p>
                                {r.user?.mobile_number && (
                                    <p className="text-xs text-slate-600 mt-0.5">
                                        Mobile: <span className="font-medium text-slate-700">{r.user.mobile_number}</span>
                                    </p>
                                )}
                                <p className="text-sm text-slate-600">{formatReservationRange(r.start_at, r.end_at)}</p>
                                <p className="text-sm text-slate-500">
                                    <span className={`inline-flex items-center rounded px-2 py-0.5 mr-2 text-xs font-medium ${getReservationStatusBadgeClass(r.status)}`}>
                                        {getReservationStatusLabel(r.status)}
                                    </span>
                                    {r.reservation_number && `• ${r.reservation_number}`}
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
                                    </div>
                                )}
                                {needsConfabAssign(r) && (
                                    <p className="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 mt-2 max-w-xl">
                                        General confab request: assign a specific free confab room before approving.
                                    </p>
                                )}
                                {r.status === 'email_verification_pending' && (
                                    <p className="text-xs text-slate-500 mt-1.5 max-w-xl">
                                        Awaiting requester email confirmation. <strong>Approve</strong> is available after they verify; <strong>Reject</strong> can decline before then.
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
                            <div className="flex flex-col gap-2 items-end shrink-0">
                                <div className="flex gap-2 flex-wrap justify-end">
                                    {r.status === 'pending_approval' && canApprove && (
                                        <button
                                            type="button"
                                            onClick={() => approve(r)}
                                            disabled={actionId === r.id || (needsConfabAssign(r) && !confabPick[r.id])}
                                            className="px-3 py-1.5 rounded-md bg-xu-primary text-white text-sm font-medium shadow-sm hover:bg-xu-secondary disabled:opacity-50 transition-colors"
                                        >
                                            Approve
                                        </button>
                                    )}
                                    {(r.status === 'pending_approval' || r.status === 'email_verification_pending') && canReject && (
                                        <button
                                            type="button"
                                            onClick={() => openReject(r.id)}
                                            disabled={actionId !== null && actionId !== r.id}
                                            className="px-3 py-1.5 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700 disabled:opacity-50"
                                        >
                                            Reject
                                        </button>
                                    )}
                                    {r.status === 'pending_approval' && canOverride && (
                                        <button
                                            type="button"
                                            onClick={() => overrideApprove(r)}
                                            disabled={actionId === r.id || (needsConfabAssign(r) && !confabPick[r.id])}
                                            className="px-3 py-1.5 rounded-md border border-xu-secondary text-xu-secondary bg-white text-sm font-medium hover:bg-xu-page disabled:opacity-50 transition-colors"
                                        >
                                            Override approve
                                        </button>
                                    )}
                                    {canOverride && !['cancelled', 'rejected'].includes(r.status) && (
                                        <button
                                            type="button"
                                            onClick={() => cancel(r.id)}
                                            disabled={actionId === r.id}
                                            className="px-3 py-1.5 rounded-md border border-slate-300 text-slate-700 bg-white text-sm font-medium hover:bg-slate-50 disabled:opacity-50 transition-colors"
                                        >
                                            Cancel
                                        </button>
                                    )}
                                </div>
                                {needsConfabAssign(r) && (canApprove || canOverride) && (
                                    <div className="flex flex-wrap items-center gap-2 justify-end max-w-xs">
                                        <label className="text-xs font-medium text-slate-600 whitespace-nowrap">Confab room</label>
                                        <select
                                            value={confabPick[r.id] || ''}
                                            onFocus={() => loadAssignOptions(r.id)}
                                            onChange={(e) => setConfabPick((p) => ({ ...p, [r.id]: e.target.value }))}
                                            className={`${ui.select} text-sm min-w-[10rem]`}
                                        >
                                            <option value="">Select…</option>
                                            {Array.isArray(assignOptions[r.id]) && assignOptions[r.id].map((s) => (
                                                <option key={s.id} value={s.id}>{s.name}</option>
                                            ))}
                                        </select>
                                        {assignOptions[r.id] === null && (
                                            <span className="text-xs text-slate-500">Loading rooms…</span>
                                        )}
                                        {Array.isArray(assignOptions[r.id]) && assignOptions[r.id].length === 0 && (
                                            <span className="text-xs text-red-600">No free confab rooms for this slot.</span>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                        {actionId === r.id && canReject && (r.status === 'pending_approval' || r.status === 'email_verification_pending') && (
                            <div className="mt-2 flex gap-2 flex-wrap">
                                <input
                                    type="text"
                                    value={rejectReason}
                                    onChange={(e) => setRejectReason(e.target.value)}
                                    className={`${ui.input} text-sm flex-1 min-w-[12rem]`}
                                    placeholder="Rejection reason"
                                />
                                <button
                                    type="button"
                                    onClick={() => reject(r.id)}
                                    className="px-3 py-1.5 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700"
                                >
                                    Confirm reject
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setActionId(null);
                                        setRejectReason('');
                                    }}
                                    className="px-3 py-1.5 rounded-md border border-slate-300 text-slate-700 bg-white text-sm hover:bg-slate-50"
                                >
                                    Dismiss
                                </button>
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

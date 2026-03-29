import { useState, useEffect } from 'react';
import api from '../../api';
import { useAuth } from '../../contexts/AuthContext';
import { getReservationActionLabel, getReservationStatusBadgeClass, getReservationStatusLabel } from '../../utils/reservationVocabulary';
import { ui } from '../../theme';

export default function AdminReservations() {
    const { hasPermission } = useAuth();
    const [reservations, setReservations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('pending_approval');
    const [rejectReason, setRejectReason] = useState('');
    const [actionId, setActionId] = useState(null);
    const [feedback, setFeedback] = useState(null);

    const load = () => {
        setLoading(true);
        const params = statusFilter ? { status: statusFilter } : {};
        api.get('/admin/reservations', { params })
            .then(({ data }) => setReservations(data.data || data))
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

    const approve = (id) => {
        if (!canApprove) return;
        clearFeedback();
        setActionId(id);
        api.post(`/admin/reservations/${id}/approve`)
            .then(({ data }) => {
                setActionId(null);
                setFeedback({ type: 'success', text: data?.message || 'Reservation approved.' });
                load();
            })
            .catch((err) => {
                setActionId(null);
                setFeedback({ type: 'error', text: err.response?.data?.message || 'Failed to approve reservation.' });
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
                        <div className="flex justify-between items-start">
                            <div>
                                <p className="font-medium text-xu-primary">{r.space?.name} – {r.user?.name} ({r.user?.email})</p>
                                <p className="text-sm text-slate-600">{new Date(r.start_at).toLocaleString()} – {new Date(r.end_at).toLocaleTimeString()}</p>
                                <p className="text-sm text-slate-500">
                                    <span className={`inline-flex items-center rounded px-2 py-0.5 mr-2 text-xs font-medium ${getReservationStatusBadgeClass(r.status)}`}>
                                        {getReservationStatusLabel(r.status)}
                                    </span>
                                    {r.reservation_number && `• ${r.reservation_number}`}
                                </p>
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
                                                    {new Date(log.created_at).toLocaleString()}
                                                    {' • '}
                                                    {log.admin?.name || 'System'}
                                                    {log.notes ? ` • ${log.notes}` : ''}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </div>
                            <div className="flex gap-2 flex-wrap">
                                {r.status === 'pending_approval' && canApprove && (
                                    <button
                                        onClick={() => approve(r.id)}
                                        disabled={actionId === r.id}
                                        className="px-3 py-1.5 rounded-md bg-xu-primary text-white text-sm font-medium shadow-sm hover:bg-xu-secondary disabled:opacity-50 transition-colors"
                                    >
                                        Approve
                                    </button>
                                )}
                                {(r.status === 'pending_approval' || r.status === 'email_verification_pending') && canReject && (
                                    <button
                                        onClick={() => openReject(r.id)}
                                        disabled={actionId !== null && actionId !== r.id}
                                        className="px-3 py-1.5 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700 disabled:opacity-50"
                                    >
                                        Reject
                                    </button>
                                )}
                                {canOverride && !['cancelled', 'rejected'].includes(r.status) && (
                                    <button
                                        onClick={() => cancel(r.id)}
                                        disabled={actionId === r.id}
                                        className="px-3 py-1.5 rounded-md border border-xu-secondary text-xu-secondary bg-white text-sm font-medium hover:bg-xu-page disabled:opacity-50 transition-colors"
                                    >
                                        Cancel
                                    </button>
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
                                    onClick={() => reject(r.id)}
                                    className="px-3 py-1.5 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700"
                                >
                                    Confirm reject
                                </button>
                                <button
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

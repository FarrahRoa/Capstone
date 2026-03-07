import { useState, useEffect } from 'react';
import api from '../../api';

export default function AdminReservations() {
    const [reservations, setReservations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('');
    const [rejectReason, setRejectReason] = useState('');
    const [actionId, setActionId] = useState(null);

    const load = () => {
        setLoading(true);
        const params = statusFilter ? { status: statusFilter } : {};
        api.get('/admin/reservations', { params })
            .then(({ data }) => setReservations(data.data || data))
            .finally(() => setLoading(false));
    };

    useEffect(() => load(), [statusFilter]);

    const statusLabel = (s) => ({ email_verification_pending: 'Pending verification', pending_approval: 'Pending approval', approved: 'Approved', rejected: 'Rejected', cancelled: 'Cancelled' }[s] || s);

    const approve = (id) => {
        setActionId(id);
        api.post(`/admin/reservations/${id}/approve`)
            .then(() => { setActionId(null); load(); })
            .catch(() => setActionId(null));
    };

    const reject = (id) => {
        if (!rejectReason.trim()) { alert('Please enter a reason'); return; }
        setActionId(id);
        api.post(`/admin/reservations/${id}/reject`, { reason: rejectReason })
            .then(() => { setActionId(null); setRejectReason(''); load(); })
            .catch(() => setActionId(null));
    };

    const openReject = (id) => setActionId(id);

    const cancel = (id) => {
        if (!confirm('Cancel this reservation?')) return;
        setActionId(id);
        api.post(`/admin/reservations/${id}/cancel`)
            .then(() => { setActionId(null); load(); })
            .catch(() => setActionId(null));
    };

    return (
        <div>
            <h1 className="text-2xl font-bold text-slate-800 mb-4">Admin – Reservations</h1>
            <div className="mb-4">
                <label className="mr-2 text-sm text-slate-700">Filter:</label>
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded border border-slate-300 px-3 py-1">
                    <option value="">All</option>
                    <option value="email_verification_pending">Pending verification</option>
                    <option value="pending_approval">Pending approval</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            {loading && <p className="text-slate-600">Loading...</p>}
            <div className="space-y-3">
                {(reservations.length === 0 && !loading) && <p className="text-slate-600">No reservations.</p>}
                {reservations.map((r) => (
                    <div key={r.id} className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                        <div className="flex justify-between items-start">
                            <div>
                                <p className="font-medium text-slate-800">{r.space?.name} – {r.user?.name} ({r.user?.email})</p>
                                <p className="text-sm text-slate-600">{new Date(r.start_at).toLocaleString()} – {new Date(r.end_at).toLocaleTimeString()}</p>
                                <p className="text-sm text-slate-500">{statusLabel(r.status)} {r.reservation_number && `• ${r.reservation_number}`}</p>
                            </div>
                            <div className="flex gap-2 flex-wrap">
                                {r.status === 'pending_approval' && (
                                    <>
                                        <button onClick={() => approve(r.id)} disabled={actionId === r.id}
                                            className="px-3 py-1 rounded bg-green-600 text-white text-sm hover:bg-green-700 disabled:opacity-50">Approve</button>
                                        <button onClick={() => openReject(r.id)} disabled={actionId !== null && actionId !== r.id}
                                            className="px-3 py-1 rounded bg-red-600 text-white text-sm hover:bg-red-700 disabled:opacity-50">Reject</button>
                                    </>
                                )}
                                {!['cancelled', 'rejected'].includes(r.status) && (
                                    <button onClick={() => cancel(r.id)} disabled={actionId === r.id}
                                        className="px-3 py-1 rounded bg-slate-600 text-white text-sm hover:bg-slate-700 disabled:opacity-50">Cancel</button>
                                )}
                            </div>
                        </div>
                        {actionId === r.id && (
                            <div className="mt-2 flex gap-2">
                                <input type="text" value={rejectReason} onChange={(e) => setRejectReason(e.target.value)}
                                    className="rounded border border-slate-300 px-2 py-1 text-sm flex-1" placeholder="Rejection reason" />
                                <button onClick={() => reject(r.id)} className="px-2 py-1 rounded bg-red-600 text-white text-sm">Confirm reject</button>
                                <button onClick={() => { setActionId(null); setRejectReason(''); }} className="px-2 py-1 rounded bg-slate-400 text-white text-sm">Cancel</button>
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

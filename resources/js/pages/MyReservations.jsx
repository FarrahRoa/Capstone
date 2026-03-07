import { useState, useEffect } from 'react';
import api from '../api';

export default function MyReservations() {
    const [reservations, setReservations] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/reservations').then(({ data }) => setReservations(data.data || data)).finally(() => setLoading(false));
    }, []);

    const statusLabel = (s) => ({ email_verification_pending: 'Pending email confirmation', pending_approval: 'Pending approval', approved: 'Approved', rejected: 'Rejected', cancelled: 'Cancelled' }[s] || s);

    if (loading) return <p className="text-slate-600">Loading...</p>;

    return (
        <div>
            <h1 className="text-2xl font-bold text-slate-800 mb-4">My reservations</h1>
            <div className="space-y-3">
                {(reservations.length === 0) ? (
                    <p className="text-slate-600">No reservations yet.</p>
                ) : (
                    reservations.map((r) => (
                        <div key={r.id} className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm flex justify-between items-start">
                            <div>
                                <p className="font-medium text-slate-800">{r.space?.name}</p>
                                <p className="text-sm text-slate-600">
                                    {new Date(r.start_at).toLocaleString()} – {new Date(r.end_at).toLocaleTimeString()}
                                </p>
                                <p className="text-sm text-slate-500">{statusLabel(r.status)} {r.reservation_number && `• ${r.reservation_number}`}</p>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

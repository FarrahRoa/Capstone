import { useState, useEffect } from 'react';
import api from '../api';
import { getReservationActionLabel, getReservationStatusLabel } from '../utils/reservationVocabulary';
import { ui } from '../theme';

export default function MyReservations() {
    const [reservations, setReservations] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/reservations').then(({ data }) => setReservations(data.data || data)).finally(() => setLoading(false));
    }, []);

    if (loading) return <p className="text-slate-600">Loading…</p>;

    return (
        <div>
            <h1 className={`${ui.pageTitle} mb-4`}>My reservations</h1>
            <div className="space-y-3">
                {(reservations.length === 0) ? (
                    <p className="text-slate-600">No reservations yet.</p>
                ) : (
                    reservations.map((r) => (
                        <div key={r.id} className={`p-4 flex justify-between items-start ${ui.cardFlat}`}>
                            <div>
                                <p className="font-medium text-xu-primary">{r.space?.name}</p>
                                <p className="text-sm text-slate-600">
                                    {new Date(r.start_at).toLocaleString()} – {new Date(r.end_at).toLocaleTimeString()}
                                </p>
                                <p className="text-sm text-slate-500">{getReservationStatusLabel(r.status)} {r.reservation_number && `• ${r.reservation_number}`}</p>
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
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';

export default function Calendar() {
    const [spaces, setSpaces] = useState([]);
    const [selectedSpace, setSelectedSpace] = useState('');
    const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
    const [availability, setAvailability] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        api.get('/spaces').then(({ data }) => setSpaces(data));
    }, []);

    useEffect(() => {
        if (!date) return;
        setLoading(true);
        const params = { date };
        if (selectedSpace) params.space_id = selectedSpace;
        api.get('/availability', { params })
            .then(({ data }) => setAvailability(data))
            .catch(() => setAvailability([]))
            .finally(() => setLoading(false));
    }, [date, selectedSpace]);

    return (
        <div>
            <h1 className="text-2xl font-bold text-slate-800 mb-4">Calendar – Room availability</h1>
            <div className="flex flex-wrap gap-4 mb-6">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Date</label>
                    <input type="date" value={date} onChange={(e) => setDate(e.target.value)}
                        className="rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-slate-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Room (optional)</label>
                    <select value={selectedSpace} onChange={(e) => setSelectedSpace(e.target.value)}
                        className="rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-slate-500">
                        <option value="">All rooms</option>
                        {spaces.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                </div>
            </div>
            {loading && <p className="text-slate-600">Loading...</p>}
            <div className="space-y-6">
                {!loading && availability.map((item) => (
                    <div key={item.space.id} className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                        <h2 className="font-semibold text-slate-800 mb-2">{item.space.name}</h2>
                        <div className="flex flex-wrap gap-2">
                            {item.reserved_slots.length === 0 ? (
                                <span className="text-green-600 text-sm">All day available</span>
                            ) : (
                                item.reserved_slots.map((slot) => (
                                    <span key={slot.id} className="inline-flex items-center px-2 py-1 rounded bg-amber-100 text-amber-800 text-sm">
                                        {new Date(slot.start_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })} – {new Date(slot.end_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}
                                    </span>
                                ))
                            )}
                        </div>
                        <Link to={`/reserve?space_id=${item.space.id}&date=${date}`}
                            className="inline-block mt-2 text-slate-700 text-sm font-medium hover:underline">Reserve this room →</Link>
                    </div>
                ))}
            </div>
        </div>
    );
}

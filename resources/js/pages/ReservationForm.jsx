import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '../api';

export default function ReservationForm() {
    const [searchParams] = useSearchParams();
    const spaceId = searchParams.get('space_id');
    const dateParam = searchParams.get('date');
    const [spaces, setSpaces] = useState([]);
    const [spaceIdVal, setSpaceIdVal] = useState(spaceId || '');
    const [date, setDate] = useState(dateParam || new Date().toISOString().slice(0, 10));
    const [startTime, setStartTime] = useState('09:00');
    const [endTime, setEndTime] = useState('10:00');
    const [purpose, setPurpose] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const navigate = useNavigate();

    useEffect(() => {
        api.get('/spaces').then(({ data }) => {
            setSpaces(data);
            if (spaceId && !spaceIdVal) setSpaceIdVal(spaceId);
        });
    }, [spaceId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        const start_at = `${date}T${startTime}:00`;
        const end_at = `${date}T${endTime}:00`;
        try {
            await api.post('/reservations', { space_id: Number(spaceIdVal), start_at, end_at, purpose });
            navigate('/my-reservations');
            alert('Reservation created. Please confirm via the link sent to your XU email.');
        } catch (err) {
            setError(err.response?.data?.message || err.response?.data?.errors?.slot?.[0] || 'Failed to create reservation.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-xl">
            <h1 className="text-2xl font-bold text-slate-800 mb-4">New reservation</h1>
            <form onSubmit={handleSubmit} className="space-y-4 bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
                {error && <div className="text-red-600 text-sm bg-red-50 p-3 rounded">{error}</div>}
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Room *</label>
                    <select value={spaceIdVal} onChange={(e) => setSpaceIdVal(e.target.value)} required
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-slate-500">
                        <option value="">Select room</option>
                        {spaces.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Date *</label>
                    <input type="date" value={date} onChange={(e) => setDate(e.target.value)} required
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-slate-500" />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Start time *</label>
                        <input type="time" value={startTime} onChange={(e) => setStartTime(e.target.value)} required
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-slate-500" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">End time *</label>
                        <input type="time" value={endTime} onChange={(e) => setEndTime(e.target.value)} required
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-slate-500" />
                    </div>
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Purpose (optional)</label>
                    <textarea value={purpose} onChange={(e) => setPurpose(e.target.value)} rows={3}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-slate-500" placeholder="Brief purpose of use" />
                </div>
                <button type="submit" disabled={loading}
                    className="w-full bg-slate-800 text-white py-2 rounded-lg font-medium hover:bg-slate-700 disabled:opacity-50">Submit reservation</button>
            </form>
        </div>
    );
}

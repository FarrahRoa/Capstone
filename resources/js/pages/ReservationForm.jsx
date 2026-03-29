import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { getSpaceIneligibilityMessage, getSpaceRestrictionLabel, isUserEligibleForSpace } from '../utils/spaceEligibility';
import { ui } from '../theme';

export default function ReservationForm() {
    const { user } = useAuth();
    const [searchParams] = useSearchParams();
    const spaceId = searchParams.get('space_id');
    const dateParam = searchParams.get('date');
    const startTimeParam = searchParams.get('start_time');
    const endTimeParam = searchParams.get('end_time');
    const [spaces, setSpaces] = useState([]);
    const [spaceIdVal, setSpaceIdVal] = useState(spaceId || '');
    const [date, setDate] = useState(dateParam || new Date().toISOString().slice(0, 10));
    const [startTime, setStartTime] = useState('09:00');
    const [endTime, setEndTime] = useState('10:00');
    const [purpose, setPurpose] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [guidelines, setGuidelines] = useState('');
    const navigate = useNavigate();
    const selectedSpace = spaces.find((s) => String(s.id) === String(spaceIdVal));
    const selectedRestriction = getSpaceRestrictionLabel(selectedSpace);
    const isSelectedSpaceEligible = isUserEligibleForSpace(user, selectedSpace);
    const selectedSpaceBlockMessage = !isSelectedSpaceEligible ? getSpaceIneligibilityMessage(selectedSpace) : '';

    useEffect(() => {
        api.get('/spaces').then(({ data }) => {
            setSpaces(data);
            if (spaceId && !spaceIdVal) setSpaceIdVal(spaceId);
        });
    }, [spaceId]);

    useEffect(() => {
        if (startTimeParam && /^\d{2}:\d{2}$/.test(startTimeParam)) {
            setStartTime(startTimeParam);
        }
        if (endTimeParam && /^\d{2}:\d{2}$/.test(endTimeParam)) {
            setEndTime(endTimeParam);
        }
    }, [startTimeParam, endTimeParam]);

    useEffect(() => {
        api.get('/reservation-guidelines')
            .then(({ data }) => setGuidelines(data.content || ''))
            .catch(() => setGuidelines(''));
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        if (selectedSpace && !isSelectedSpaceEligible) {
            setError(selectedSpaceBlockMessage);
            return;
        }
        setLoading(true);
        const start_at = `${date}T${startTime}:00`;
        const end_at = `${date}T${endTime}:00`;
        try {
            await api.post('/reservations', { space_id: Number(spaceIdVal), start_at, end_at, purpose });
            navigate('/my-reservations');
            alert('Reservation created. Please confirm via the link sent to your XU email.');
        } catch (err) {
            const d = err.response?.data;
            setError(
                d?.message
                    || d?.errors?.space_id?.[0]
                    || d?.errors?.slot?.[0]
                    || 'Failed to create reservation.'
            );
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-xl">
            <h1 className={`${ui.pageTitle} mb-4`}>New reservation</h1>
            {guidelines.trim() !== '' && (
                <details className="mb-4 bg-white border border-slate-200/90 rounded-lg p-4 text-sm text-slate-700 shadow-sm border-l-4 border-l-xu-gold/60">
                    <summary className="cursor-pointer font-medium text-xu-primary">Reservation guidelines</summary>
                    <div className="mt-2 whitespace-pre-wrap">{guidelines}</div>
                </details>
            )}
            <form onSubmit={handleSubmit} className={`space-y-4 p-6 ${ui.cardFlat}`}>
                {error && <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg">{error}</div>}
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Room *</label>
                    <select value={spaceIdVal} onChange={(e) => setSpaceIdVal(e.target.value)} required className={`w-full ${ui.select}`}>
                        <option value="">Select room</option>
                        {spaces.map((s) => {
                            const restriction = getSpaceRestrictionLabel(s);
                            const eligible = isUserEligibleForSpace(user, s);
                            return (
                                <option key={s.id} value={s.id} disabled={!eligible}>
                                    {restriction ? `${s.name} (${restriction})` : s.name}
                                </option>
                            );
                        })}
                    </select>
                    {selectedRestriction && (
                        <p className="mt-2 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 inline-block">
                            {selectedRestriction}
                        </p>
                    )}
                    {selectedSpace && !isSelectedSpaceEligible && (
                        <p className="mt-2 text-sm text-red-700">
                            {selectedSpaceBlockMessage}
                        </p>
                    )}
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Date *</label>
                    <input type="date" value={date} onChange={(e) => setDate(e.target.value)} required className={ui.input} />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Start time *</label>
                        <input type="time" value={startTime} onChange={(e) => setStartTime(e.target.value)} required className={ui.input} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">End time *</label>
                        <input type="time" value={endTime} onChange={(e) => setEndTime(e.target.value)} required className={ui.input} />
                    </div>
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Purpose (optional)</label>
                    <textarea value={purpose} onChange={(e) => setPurpose(e.target.value)} rows={3} className={ui.input} placeholder="Brief purpose of use" />
                </div>
                <button type="submit" disabled={loading || (selectedSpace && !isSelectedSpaceEligible)} className={ui.btnPrimaryFull}>
                    Submit reservation
                </button>
            </form>
        </div>
    );
}

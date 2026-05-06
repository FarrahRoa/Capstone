import { useEffect, useState } from 'react';
import api from '../../api';
import { unwrapData } from '../../utils/apiEnvelope';
import { ui } from '../../theme';

export default function AdminOperatingHours() {
    const [dayStart, setDayStart] = useState('06:00');
    const [dayEnd, setDayEnd] = useState('18:30');
    const [weekendStart, setWeekendStart] = useState('');
    const [weekendEnd, setWeekendEnd] = useState('');
    const [holidays, setHolidays] = useState([]);
    const [holidayName, setHolidayName] = useState('');
    const [holidayDate, setHolidayDate] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [banner, setBanner] = useState(null);

    const load = () => {
        setLoading(true);
        setBanner(null);
        api.get('/admin/policies/operating-hours')
            .then(({ data }) => {
                const doc = unwrapData(data);
                setDayStart(doc?.hours?.day_start || '06:00');
                setDayEnd(doc?.hours?.day_end || '18:30');
                setWeekendStart(doc?.hours?.weekend_day_start || '');
                setWeekendEnd(doc?.hours?.weekend_day_end || '');
                setHolidays(Array.isArray(doc?.holidays) ? doc.holidays : []);
            })
            .catch((err) => {
                setBanner({ type: 'error', text: err.response?.data?.message || 'Failed to load operating hours.' });
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
    }, []);

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        setBanner(null);
        try {
            const { data } = await api.put('/admin/policies/operating-hours', {
                day_start: dayStart,
                day_end: dayEnd,
                weekend_day_start: weekendStart || null,
                weekend_day_end: weekendEnd || null,
            });
            const doc = unwrapData(data);
            setDayStart(doc?.hours?.day_start || dayStart);
            setDayEnd(doc?.hours?.day_end || dayEnd);
            setWeekendStart(doc?.hours?.weekend_day_start || '');
            setWeekendEnd(doc?.hours?.weekend_day_end || '');
            setBanner({ type: 'success', text: data.message || 'Saved.' });
        } catch (err) {
            const msg = err.response?.data?.message;
            const endField = err.response?.data?.errors?.day_end?.[0];
            const startField = err.response?.data?.errors?.day_start?.[0];
            setBanner({ type: 'error', text: endField || startField || msg || 'Could not save operating hours.' });
        } finally {
            setSaving(false);
        }
    };

    const addHoliday = async (e) => {
        e.preventDefault();
        setSaving(true);
        setBanner(null);
        try {
            await api.post('/admin/holidays', {
                name: holidayName.trim(),
                date: holidayDate,
                is_recurring: false,
            });
            setHolidayName('');
            setHolidayDate('');
            load();
            setBanner({ type: 'success', text: 'Holiday added.' });
        } catch (err) {
            const d = err.response?.data;
            setBanner({ type: 'error', text: d?.errors?.name?.[0] || d?.errors?.date?.[0] || d?.message || 'Could not add holiday.' });
        } finally {
            setSaving(false);
        }
    };

    const deleteHoliday = async (id) => {
        if (!confirm('Delete this holiday?')) return;
        setSaving(true);
        setBanner(null);
        try {
            await api.delete(`/admin/holidays/${id}`);
            load();
            setBanner({ type: 'success', text: 'Holiday deleted.' });
        } catch (err) {
            setBanner({ type: 'error', text: err.response?.data?.message || 'Could not delete holiday.' });
        } finally {
            setSaving(false);
        }
    };

    return (
        <div>
            <h1 className={`${ui.pageTitle} mb-2`}>Operating hours</h1>
            <p className="text-sm text-slate-600 mb-4">
                Configure the usable reservation time window (weekdays Mon–Fri by default). Optionally set different hours
                for Saturday–Sunday. These values drive the public reservation form and server validation.
            </p>

            {banner && (
                <div
                    className={`mb-4 text-sm p-3 rounded border ${
                        banner.type === 'success'
                            ? 'text-green-800 bg-green-50 border-green-200'
                            : 'text-red-700 bg-red-50 border-red-200'
                    }`}
                >
                    {banner.text}
                </div>
            )}

            {loading && <p className="text-slate-600">Loading…</p>}

            {!loading && (
                <div className="space-y-4 max-w-2xl">
                    <div className={`p-4 ${ui.card}`}>
                        <form onSubmit={save}>
                        <div className="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Day start</label>
                                <input
                                    type="time"
                                    value={dayStart}
                                    onChange={(e) => setDayStart(e.target.value)}
                                    required
                                    className={ui.input}
                                    disabled={saving}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Day end</label>
                                <input
                                    type="time"
                                    value={dayEnd}
                                    onChange={(e) => setDayEnd(e.target.value)}
                                    required
                                    className={ui.input}
                                    disabled={saving}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Weekend start (Sat–Sun, optional)
                                </label>
                                <input
                                    type="time"
                                    value={weekendStart}
                                    onChange={(e) => setWeekendStart(e.target.value)}
                                    className={ui.input}
                                    disabled={saving}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Weekend end (Sat–Sun, optional)
                                </label>
                                <input
                                    type="time"
                                    value={weekendEnd}
                                    onChange={(e) => setWeekendEnd(e.target.value)}
                                    className={ui.input}
                                    disabled={saving}
                                />
                            </div>
                        </div>
                        <p className="mt-2 text-xs text-slate-600">
                            Leave weekend times empty to use the same window every day. Set both to override hours on
                            Saturday and Sunday only.
                        </p>
                        <button type="submit" disabled={saving} className={`${ui.btnPrimary} mt-4`}>
                            {saving ? 'Saving…' : 'Save operating hours'}
                        </button>
                        </form>
                    </div>

                    <div className={`p-4 ${ui.card}`}>
                        <h2 className="text-sm font-semibold text-slate-900 mb-3">Holidays</h2>
                        <form onSubmit={addHoliday} className="grid gap-3 sm:grid-cols-3">
                            <div className="sm:col-span-2">
                                <label className="block text-sm font-medium text-slate-700 mb-1">Holiday name</label>
                                <input
                                    value={holidayName}
                                    onChange={(e) => setHolidayName(e.target.value)}
                                    className={ui.input}
                                    placeholder="e.g. Foundation Day"
                                    disabled={saving}
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Date</label>
                                <input
                                    type="date"
                                    value={holidayDate}
                                    onChange={(e) => setHolidayDate(e.target.value)}
                                    className={ui.input}
                                    disabled={saving}
                                    required
                                />
                            </div>
                            <div className="sm:col-span-3">
                                <button type="submit" disabled={saving || !holidayName.trim() || !holidayDate} className={ui.btnPrimary}>
                                    {saving ? 'Saving…' : 'Add holiday'}
                                </button>
                            </div>
                        </form>

                        <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                            <table className="min-w-full text-sm">
                                <thead className="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th className="text-left px-4 py-2 font-semibold text-slate-700">Date</th>
                                        <th className="text-left px-4 py-2 font-semibold text-slate-700">Name</th>
                                        <th className="text-left px-4 py-2 font-semibold text-slate-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {holidays.length === 0 ? (
                                        <tr>
                                            <td colSpan={3} className="px-4 py-3 text-slate-500">
                                                No holidays yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        holidays.map((h) => (
                                            <tr key={h.id} className="border-t border-slate-200">
                                                <td className="px-4 py-2 text-slate-700">{h.date}</td>
                                                <td className="px-4 py-2 text-slate-900 font-medium">{h.name}</td>
                                                <td className="px-4 py-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => deleteHoliday(h.id)}
                                                        disabled={saving}
                                                        className="text-xs font-semibold text-red-700 hover:underline disabled:opacity-60"
                                                    >
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}


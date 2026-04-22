import { useEffect, useState } from 'react';
import api from '../../api';
import { unwrapData } from '../../utils/apiEnvelope';
import { ui } from '../../theme';

export default function AdminOperatingHours() {
    const [dayStart, setDayStart] = useState('09:00');
    const [dayEnd, setDayEnd] = useState('18:00');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [banner, setBanner] = useState(null);

    const load = () => {
        setLoading(true);
        setBanner(null);
        api.get('/admin/policies/operating-hours')
            .then(({ data }) => {
                const doc = unwrapData(data);
                setDayStart(doc?.hours?.day_start || '09:00');
                setDayEnd(doc?.hours?.day_end || '18:00');
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
            });
            const doc = unwrapData(data);
            setDayStart(doc?.hours?.day_start || dayStart);
            setDayEnd(doc?.hours?.day_end || dayEnd);
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

    return (
        <div>
            <h1 className={`${ui.pageTitle} mb-2`}>Operating hours</h1>
            <p className="text-sm text-slate-600 mb-4">
                Configure the usable reservation time window. These values will be used by availability and booking rules.
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
                <form onSubmit={save} className="space-y-4 max-w-xl">
                    <div className={`p-4 ${ui.card}`}>
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
                        </div>
                        <button type="submit" disabled={saving} className={`${ui.btnPrimary} mt-4`}>
                            {saving ? 'Saving…' : 'Save operating hours'}
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}


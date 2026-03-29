import { useEffect, useState } from 'react';
import api from '../../api';
import { ui } from '../../theme';

export default function AdminPolicies() {
    const [content, setContent] = useState('');
    const [updatedAt, setUpdatedAt] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [banner, setBanner] = useState(null);

    const load = () => {
        setLoading(true);
        setBanner(null);
        api.get('/admin/policies/reservation-guidelines')
            .then(({ data }) => {
                setContent(data.content ?? '');
                setUpdatedAt(data.updated_at || '');
            })
            .catch((err) => {
                setBanner({
                    type: 'error',
                    text: err.response?.data?.message || 'Failed to load guidelines.',
                });
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
            const { data } = await api.put('/admin/policies/reservation-guidelines', { content });
            setContent(data.content ?? content);
            setUpdatedAt(data.updated_at || '');
            setBanner({ type: 'success', text: data.message || 'Saved.' });
        } catch (err) {
            const msg = err.response?.data?.message;
            const field = err.response?.data?.errors?.content?.[0];
            setBanner({
                type: 'error',
                text: field || msg || 'Could not save guidelines.',
            });
        } finally {
            setSaving(false);
        }
    };

    return (
        <div>
            <h1 className={`${ui.pageTitle} mb-2`}>Reservation guidelines</h1>
            <p className="text-sm text-slate-600 mb-4">
                This text is shown to users on the New reservation page. Plain text only; line breaks are preserved.
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
                <form onSubmit={save} className="space-y-4 max-w-3xl">
                    {updatedAt && (
                        <p className="text-xs text-slate-500">Last updated: {new Date(updatedAt).toLocaleString()}</p>
                    )}
                    <div>
                        <label htmlFor="policy-content" className="block text-sm font-medium text-slate-700 mb-1">
                            Guidelines content
                        </label>
                        <textarea
                            id="policy-content"
                            value={content}
                            onChange={(e) => setContent(e.target.value)}
                            rows={16}
                            disabled={saving}
                            className="w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm focus:ring-2 focus:ring-xu-secondary/35 focus:border-xu-secondary disabled:opacity-60"
                        />
                    </div>
                    <button type="submit" disabled={saving} className={ui.btnPrimary}>
                        {saving ? 'Saving…' : 'Save guidelines'}
                    </button>
                </form>
            )}
        </div>
    );
}

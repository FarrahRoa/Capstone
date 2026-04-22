import { useEffect, useState } from 'react';
import api from '../../api';
import { unwrapData } from '../../utils/apiEnvelope';
import { getSpaceRestrictionLabel } from '../../utils/spaceEligibility';
import { ui } from '../../theme';

const TYPE_OPTIONS = [
    { value: 'avr', label: 'AVR' },
    { value: 'lobby', label: 'Lobby' },
    { value: 'boardroom', label: 'Boardroom' },
    { value: 'medical_confab', label: 'Medical Confab' },
    { value: 'confab', label: 'Confab' },
    { value: 'lecture', label: 'Lecture Space' },
];

function typeLabel(value) {
    return TYPE_OPTIONS.find((o) => o.value === value)?.label || value;
}

export default function AdminSpaces() {
    const [spaces, setSpaces] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [searchInput, setSearchInput] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [banner, setBanner] = useState(null);
    const [formErrors, setFormErrors] = useState({});
    const [createOpen, setCreateOpen] = useState(false);
    const [createForm, setCreateForm] = useState({
        name: '',
        slug: '',
        type: 'avr',
        capacity: '',
        is_active: true,
    });
    const [creating, setCreating] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [editDraft, setEditDraft] = useState(null);
    const [savingId, setSavingId] = useState(null);

    const loadSpaces = () => {
        setLoading(true);
        setBanner(null);
        const params = {};
        if (search.trim()) params.search = search.trim();
        if (typeFilter) params.type = typeFilter;
        api.get('/admin/spaces', { params })
            .then(({ data }) => {
                const list = unwrapData(data);
                setSpaces(Array.isArray(list) ? list : []);
            })
            .catch((err) => {
                setSpaces([]);
                setBanner({ type: 'error', text: err.response?.data?.message || 'Failed to load spaces.' });
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        loadSpaces();
    }, [search, typeFilter]);

    const applySearch = (e) => {
        e.preventDefault();
        setSearch(searchInput);
    };

    const parseApiErrors = (err) => {
        const errors = err.response?.data?.errors;
        if (errors && typeof errors === 'object') return errors;
        return {};
    };

    const createSpace = async (e) => {
        e.preventDefault();
        setCreating(true);
        setFormErrors({});
        setBanner(null);
        try {
            const payload = {
                name: createForm.name.trim(),
                slug: createForm.slug.trim(),
                type: createForm.type,
                is_active: Boolean(createForm.is_active),
            };
            if (createForm.capacity !== '' && createForm.capacity != null) {
                payload.capacity = Number(createForm.capacity);
            }
            await api.post('/admin/spaces', payload);
            setBanner({ type: 'success', text: 'Space created.' });
            setCreateForm({ name: '', slug: '', type: 'avr', capacity: '', is_active: true });
            setCreateOpen(false);
            loadSpaces();
        } catch (err) {
            setFormErrors(parseApiErrors(err));
            setBanner({
                type: 'error',
                text: err.response?.data?.message || 'Could not create space. Check the form.',
            });
        } finally {
            setCreating(false);
        }
    };

    const startEdit = (space) => {
        setEditingId(space.id);
        setEditDraft({
            name: space.name,
            slug: space.slug,
            type: space.type,
            capacity: space.capacity ?? '',
            is_active: Boolean(space.is_active),
        });
        setFormErrors({});
        setBanner(null);
    };

    const cancelEdit = () => {
        setEditingId(null);
        setEditDraft(null);
        setFormErrors({});
    };

    const saveEdit = async (spaceId) => {
        setSavingId(spaceId);
        setFormErrors({});
        setBanner(null);
        try {
            const payload = {
                name: editDraft.name.trim(),
                slug: editDraft.slug.trim(),
                type: editDraft.type,
                is_active: Boolean(editDraft.is_active),
            };
            if (editDraft.capacity === '' || editDraft.capacity == null) {
                payload.capacity = null;
            } else {
                payload.capacity = Number(editDraft.capacity);
            }
            const { data } = await api.put(`/admin/spaces/${spaceId}`, payload);
            const updated = unwrapData(data);
            setSpaces((prev) => prev.map((s) => (s.id === spaceId ? updated : s)));
            setBanner({ type: 'success', text: 'Space updated.' });
            cancelEdit();
        } catch (err) {
            setFormErrors(parseApiErrors(err));
            setBanner({
                type: 'error',
                text: err.response?.data?.message || 'Could not update space.',
            });
        } finally {
            setSavingId(null);
        }
    };

    const toggleActive = async (space) => {
        const next = !space.is_active;
        setSavingId(space.id);
        setBanner(null);
        try {
            const { data } = await api.post(`/admin/spaces/${space.id}/toggle-active`, {
                is_active: next,
            });
            const updated = unwrapData(data);
            setSpaces((prev) => prev.map((s) => (s.id === space.id ? updated : s)));
            setBanner({
                type: 'success',
                text: next ? 'Space activated.' : 'Space deactivated.',
            });
        } catch (err) {
            setBanner({
                type: 'error',
                text: err.response?.data?.message || 'Could not update availability.',
            });
        } finally {
            setSavingId(null);
        }
    };

    return (
        <div>
            <h1 className={`${ui.pageTitle} mb-2`}>Space management</h1>
            <p className="text-sm text-slate-600 mb-4">
                Create and edit rooms. Types <strong>Medical Confab</strong> and <strong>Boardroom</strong> use extra reservation eligibility rules on the backend.
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

            <div className="mb-4 flex flex-wrap gap-2 items-end">
                <form onSubmit={applySearch} className="flex flex-wrap gap-2 items-end">
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Search</label>
                        <input
                            type="text"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            placeholder="Name or slug"
                            className="rounded-lg border border-slate-200 px-3 py-2 w-56 focus:ring-2 focus:ring-xu-secondary/35 focus:border-xu-secondary"
                        />
                    </div>
                    <button type="submit" className={ui.btnPrimary}>
                        Search
                    </button>
                </form>
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">Type</label>
                    <select
                        value={typeFilter}
                        onChange={(e) => setTypeFilter(e.target.value)}
                        className={ui.select}
                    >
                        <option value="">All types</option>
                        {TYPE_OPTIONS.map((o) => (
                            <option key={o.value} value={o.value}>{o.label}</option>
                        ))}
                    </select>
                </div>
                <button
                    type="button"
                    onClick={() => {
                        setCreateOpen((o) => !o);
                        setFormErrors({});
                    }}
                    className="rounded-lg border border-slate-200 px-4 py-2 text-slate-700 hover:bg-xu-page hover:border-xu-secondary/25"
                >
                    {createOpen ? 'Hide form' : 'New space'}
                </button>
            </div>

            {createOpen && (
                <form
                    onSubmit={createSpace}
                    className={`mb-6 space-y-3 max-w-xl p-4 ${ui.cardFlat}`}
                >
                    <h2 className="font-semibold text-xu-primary font-serif">Create space</h2>
                    <div>
                        <label className="block text-xs text-slate-600 mb-1">Name *</label>
                        <input
                            required
                            value={createForm.name}
                            onChange={(e) => setCreateForm((f) => ({ ...f, name: e.target.value }))}
                            className="w-full rounded border border-slate-200 px-3 py-2 focus:ring-2 focus:ring-xu-secondary/35 focus:border-xu-secondary"
                        />
                        {formErrors.name && <p className="text-red-600 text-xs mt-1">{formErrors.name[0]}</p>}
                    </div>
                    <div>
                        <label className="block text-xs text-slate-600 mb-1">Slug * (URL-safe)</label>
                        <input
                            required
                            value={createForm.slug}
                            onChange={(e) => setCreateForm((f) => ({ ...f, slug: e.target.value }))}
                            className="w-full rounded border border-slate-200 px-3 py-2 focus:ring-2 focus:ring-xu-secondary/35 focus:border-xu-secondary"
                        />
                        {formErrors.slug && <p className="text-red-600 text-xs mt-1">{formErrors.slug[0]}</p>}
                    </div>
                    <div>
                        <label className="block text-xs text-slate-600 mb-1">Type *</label>
                        <select
                            value={createForm.type}
                            onChange={(e) => setCreateForm((f) => ({ ...f, type: e.target.value }))}
                            className="w-full rounded border border-slate-200 px-3 py-2 focus:ring-2 focus:ring-xu-secondary/35 focus:border-xu-secondary"
                        >
                            {TYPE_OPTIONS.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                        {formErrors.type && <p className="text-red-600 text-xs mt-1">{formErrors.type[0]}</p>}
                    </div>
                    <div>
                        <label className="block text-xs text-slate-600 mb-1">Capacity</label>
                        <input
                            type="number"
                            min={1}
                            value={createForm.capacity}
                            onChange={(e) => setCreateForm((f) => ({ ...f, capacity: e.target.value }))}
                            className="w-full rounded border border-slate-200 px-3 py-2 focus:ring-2 focus:ring-xu-secondary/35 focus:border-xu-secondary"
                        />
                        {formErrors.capacity && <p className="text-red-600 text-xs mt-1">{formErrors.capacity[0]}</p>}
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={createForm.is_active}
                            onChange={(e) => setCreateForm((f) => ({ ...f, is_active: e.target.checked }))}
                        />
                        Active (available for reservations)
                    </label>
                    <button
                        type="submit"
                        disabled={creating}
                        className={`${ui.btnPrimary} disabled:opacity-50`}
                    >
                        {creating ? 'Creating…' : 'Create space'}
                    </button>
                </form>
            )}

            {loading && <p className="text-slate-600">Loading…</p>}

            {!loading && (
                <div className="overflow-x-auto bg-white rounded-lg border border-slate-200 shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead className="bg-xu-primary/5 text-xu-primary border-b border-slate-200">
                            <tr>
                                <th className="text-left px-3 py-2 font-semibold">Name</th>
                                <th className="text-left px-3 py-2 font-semibold">Slug</th>
                                <th className="text-left px-3 py-2 font-semibold">Type</th>
                                <th className="text-left px-3 py-2 font-semibold">Capacity</th>
                                <th className="text-left px-3 py-2 font-semibold">Active</th>
                                <th className="text-left px-3 py-2 font-semibold">Reservation rules</th>
                                <th className="text-left px-3 py-2 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {spaces.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-3 py-4 text-slate-500">No spaces match.</td>
                                </tr>
                            )}
                            {spaces.map((s) => {
                                const restriction = getSpaceRestrictionLabel(s);
                                const busy = savingId === s.id;
                                const editing = editingId === s.id;
                                return (
                                    <tr key={s.id} className={`border-t border-slate-200 ${busy ? 'opacity-60' : ''}`}>
                                        <td className="px-3 py-2 align-top">
                                            {editing ? (
                                                <input
                                                    value={editDraft.name}
                                                    onChange={(e) => setEditDraft((d) => ({ ...d, name: e.target.value }))}
                                                    disabled={busy}
                                                    className="w-full rounded border border-slate-200 px-2 py-1 focus:ring-2 focus:ring-xu-secondary/30 focus:border-xu-secondary"
                                                />
                                            ) : (
                                                s.name
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top font-mono text-xs">
                                            {editing ? (
                                                <input
                                                    value={editDraft.slug}
                                                    onChange={(e) => setEditDraft((d) => ({ ...d, slug: e.target.value }))}
                                                    disabled={busy}
                                                    className="w-full rounded border border-slate-200 px-2 py-1 focus:ring-2 focus:ring-xu-secondary/30 focus:border-xu-secondary"
                                                />
                                            ) : (
                                                s.slug
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            {editing ? (
                                                <select
                                                    value={editDraft.type}
                                                    onChange={(e) => setEditDraft((d) => ({ ...d, type: e.target.value }))}
                                                    disabled={busy}
                                                    className="rounded border border-slate-200 px-2 py-1 focus:ring-2 focus:ring-xu-secondary/30 focus:border-xu-secondary"
                                                >
                                                    {TYPE_OPTIONS.map((o) => (
                                                        <option key={o.value} value={o.value}>{o.label}</option>
                                                    ))}
                                                </select>
                                            ) : (
                                                typeLabel(s.type)
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            {editing ? (
                                                <input
                                                    type="number"
                                                    min={1}
                                                    value={editDraft.capacity}
                                                    onChange={(e) => setEditDraft((d) => ({ ...d, capacity: e.target.value }))}
                                                    disabled={busy}
                                                    className="w-20 rounded border border-slate-200 px-2 py-1 focus:ring-2 focus:ring-xu-secondary/30 focus:border-xu-secondary"
                                                />
                                            ) : (
                                                s.capacity ?? '—'
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            {editing ? (
                                                <input
                                                    type="checkbox"
                                                    checked={editDraft.is_active}
                                                    onChange={(e) => setEditDraft((d) => ({ ...d, is_active: e.target.checked }))}
                                                    disabled={busy}
                                                />
                                            ) : (
                                                <span className={s.is_active ? 'text-green-700' : 'text-slate-500'}>
                                                    {s.is_active ? 'Yes' : 'No'}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top max-w-xs">
                                            {restriction ? (
                                                <span className="inline-block text-xs font-medium text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-0.5">
                                                    {restriction}
                                                </span>
                                            ) : (
                                                <span className="text-slate-400 text-xs">Standard</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top whitespace-nowrap">
                                            {editing ? (
                                                <div className="flex flex-col gap-1">
                                                    <button
                                                        type="button"
                                                        disabled={busy}
                                                        onClick={() => saveEdit(s.id)}
                                                        className="text-left text-sm text-green-700 hover:underline disabled:opacity-50"
                                                    >
                                                        Save
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={busy}
                                                        onClick={cancelEdit}
                                                        className="text-left text-sm text-slate-600 hover:underline"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            ) : (
                                                <div className="flex flex-col gap-1">
                                                    <button
                                                        type="button"
                                                        disabled={busy || editingId !== null}
                                                        onClick={() => startEdit(s)}
                                                        className="text-left text-sm text-xu-secondary hover:text-xu-primary hover:underline disabled:opacity-50"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={busy || editingId !== null}
                                                        onClick={() => toggleActive(s)}
                                                        className="text-left text-sm text-slate-600 hover:underline disabled:opacity-50"
                                                    >
                                                        {s.is_active ? 'Deactivate' : 'Activate'}
                                                    </button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {editingId && Object.keys(formErrors).length > 0 && (
                <div className="mt-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3">
                    {Object.entries(formErrors).map(([k, v]) => (
                        <p key={k}>{k}: {Array.isArray(v) ? v[0] : v}</p>
                    ))}
                </div>
            )}
        </div>
    );
}

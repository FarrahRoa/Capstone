import { useEffect, useMemo, useState } from 'react';
import api from '../../api';
import { unwrapData } from '../../utils/apiEnvelope';
import { affiliationNamesForType } from '../../constants/affiliationOptions';
import { ui } from '../../theme';

const TYPE_OPTIONS = [
    { value: 'college', label: 'College (Student)' },
    { value: 'office_department', label: 'Office/Department (Employee/Staff)' },
];

const emptyForm = {
    affiliation_type: 'college',
    affiliation_name: '',
    approver_name: '',
    approver_email: '',
    is_active: true,
};

export default function AdminDeanEmails() {
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    const [form, setForm] = useState(emptyForm);

    const [editingId, setEditingId] = useState(null);
    const [editDraft, setEditDraft] = useState(null);

    const addNameOptions = useMemo(() => affiliationNamesForType(form.affiliation_type), [form.affiliation_type]);

    const load = () => {
        setLoading(true);
        setError('');
        api.get('/admin/dean-email-mappings')
            .then(({ data }) => {
                const list = unwrapData(data);
                setRows(Array.isArray(list) ? list : []);
            })
            .catch((err) => setError(err.response?.data?.message || 'Failed to load dean email mappings.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
    }, []);

    const onCreate = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError('');
        try {
            await api.post('/admin/dean-email-mappings', {
                ...form,
                affiliation_name: form.affiliation_name.trim(),
                approver_name: form.approver_name.trim() || null,
                approver_email: form.approver_email.trim(),
            });
            setForm({
                ...emptyForm,
                affiliation_type: form.affiliation_type,
            });
            load();
        } catch (err) {
            const d = err.response?.data;
            setError(
                d?.errors?.affiliation_name?.[0]
                || d?.errors?.approver_email?.[0]
                || d?.message
                || 'Could not create mapping.'
            );
        } finally {
            setSaving(false);
        }
    };

    /** @returns {Promise<boolean>} */
    const updateRow = async (id, patch) => {
        setSaving(true);
        setError('');
        try {
            await api.patch(`/admin/dean-email-mappings/${id}`, patch);
            load();
            return true;
        } catch (err) {
            const d = err.response?.data;
            setError(
                d?.errors?.affiliation_name?.[0]
                || d?.errors?.approver_email?.[0]
                || d?.message
                || 'Could not update mapping.'
            );
            return false;
        } finally {
            setSaving(false);
        }
    };

    const removeRow = async (id) => {
        if (!confirm('Delete this mapping?')) return;
        setSaving(true);
        setError('');
        try {
            await api.delete(`/admin/dean-email-mappings/${id}`);
            if (editingId === id) {
                setEditingId(null);
                setEditDraft(null);
            }
            load();
        } catch (err) {
            setError(err.response?.data?.message || 'Could not delete mapping.');
        } finally {
            setSaving(false);
        }
    };

    const startEdit = (r) => {
        setEditingId(r.id);
        setEditDraft({
            affiliation_type: r.affiliation_type,
            affiliation_name: r.affiliation_name,
            approver_name: r.approver_name || '',
            approver_email: r.approver_email,
            is_active: Boolean(r.is_active),
        });
    };

    const cancelEdit = () => {
        setEditingId(null);
        setEditDraft(null);
    };

    const saveEdit = async () => {
        if (!editingId || !editDraft) return;
        const ok = await updateRow(editingId, {
            affiliation_type: editDraft.affiliation_type,
            affiliation_name: editDraft.affiliation_name.trim(),
            approver_name: editDraft.approver_name.trim() || null,
            approver_email: editDraft.approver_email.trim(),
            is_active: editDraft.is_active,
        });
        if (ok) {
            cancelEdit();
        }
    };

    const editNameOptions = useMemo(() => {
        if (!editDraft) return [];
        return affiliationNamesForType(editDraft.affiliation_type);
    }, [editDraft]);

    const sorted = useMemo(() => rows, [rows]);

    return (
        <div>
            <h1 className={`${ui.pageTitle} mb-2`}>Dean emails</h1>
            <p className="text-sm text-slate-600 mb-4">
                Manage dean approver email mappings used for future reservation notifications. Each affiliation should have at most one active mapping.
                Affiliation names must match the same college/office lists users pick in Complete Profile.
            </p>

            {error && (
                <div className="mb-4 text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded">
                    {error}
                </div>
            )}

            <div className={`mb-6 p-4 ${ui.card}`}>
                <h2 className="text-sm font-semibold text-slate-900 mb-3">Add mapping</h2>
                <form onSubmit={onCreate} className="grid gap-3 md:grid-cols-2">
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Affiliation type</label>
                        <select
                            value={form.affiliation_type}
                            onChange={(e) => {
                                const t = e.target.value;
                                setForm((f) => ({
                                    ...f,
                                    affiliation_type: t,
                                    affiliation_name: affiliationNamesForType(t).includes(f.affiliation_name)
                                        ? f.affiliation_name
                                        : '',
                                }));
                            }}
                            className={ui.select}
                        >
                            {TYPE_OPTIONS.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Affiliation name</label>
                        <select
                            value={form.affiliation_name}
                            onChange={(e) => setForm((f) => ({ ...f, affiliation_name: e.target.value }))}
                            className={ui.select}
                            required
                        >
                            <option value="" disabled>
                                Select affiliation…
                            </option>
                            {addNameOptions.map((name) => (
                                <option key={name} value={name}>{name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Approver name (optional)</label>
                        <input
                            value={form.approver_name}
                            onChange={(e) => setForm((f) => ({ ...f, approver_name: e.target.value }))}
                            className={ui.input}
                            placeholder="e.g. Dean Maria Santos"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Approver email</label>
                        <input
                            type="email"
                            value={form.approver_email}
                            onChange={(e) => setForm((f) => ({ ...f, approver_email: e.target.value }))}
                            className={ui.input}
                            placeholder="dean@xu.edu.ph"
                            required
                        />
                    </div>
                    <div className="flex items-center gap-2 md:col-span-2">
                        <input
                            id="dean-active"
                            type="checkbox"
                            checked={Boolean(form.is_active)}
                            onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
                            className="accent-xu-primary"
                        />
                        <label htmlFor="dean-active" className="text-sm text-slate-700">Active</label>
                    </div>
                    <div className="md:col-span-2">
                        <button type="submit" disabled={saving || !form.affiliation_name} className={ui.btnPrimary}>
                            {saving ? 'Saving…' : 'Add mapping'}
                        </button>
                    </div>
                </form>
            </div>

            <div className="overflow-x-auto bg-white rounded-lg border border-slate-200 shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-xu-primary/5 text-xu-primary border-b border-slate-200">
                        <tr>
                            <th className="text-left px-4 py-2 font-semibold">Type</th>
                            <th className="text-left px-4 py-2 font-semibold">Affiliation</th>
                            <th className="text-left px-4 py-2 font-semibold">Approver</th>
                            <th className="text-left px-4 py-2 font-semibold">Email</th>
                            <th className="text-left px-4 py-2 font-semibold">Active</th>
                            <th className="text-left px-4 py-2 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading && (
                            <tr><td className="px-4 py-3 text-slate-500" colSpan={6}>Loading…</td></tr>
                        )}
                        {!loading && sorted.length === 0 && (
                            <tr><td className="px-4 py-3 text-slate-500" colSpan={6}>No mappings yet.</td></tr>
                        )}
                        {!loading && sorted.map((r) => {
                            const isEditing = editingId === r.id && editDraft;
                            return (
                                <tr key={r.id} className="border-t border-slate-200">
                                    <td className="px-4 py-2 text-slate-700 align-top">
                                        {isEditing ? (
                                            <select
                                                value={editDraft.affiliation_type}
                                                onChange={(e) => {
                                                    const t = e.target.value;
                                                    setEditDraft((d) => ({
                                                        ...d,
                                                        affiliation_type: t,
                                                        affiliation_name: affiliationNamesForType(t).includes(d.affiliation_name)
                                                            ? d.affiliation_name
                                                            : '',
                                                    }));
                                                }}
                                                className={ui.select}
                                                disabled={saving}
                                            >
                                                {TYPE_OPTIONS.map((o) => (
                                                    <option key={o.value} value={o.value}>{o.label}</option>
                                                ))}
                                            </select>
                                        ) : (
                                            r.affiliation_type === 'college' ? 'College' : 'Office/Department'
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-slate-900 font-medium align-top">
                                        {isEditing ? (
                                            <select
                                                value={editDraft.affiliation_name}
                                                onChange={(e) => setEditDraft((d) => ({ ...d, affiliation_name: e.target.value }))}
                                                className={ui.select}
                                                disabled={saving}
                                                required
                                            >
                                                <option value="" disabled>Select…</option>
                                                {editNameOptions.map((name) => (
                                                    <option key={name} value={name}>{name}</option>
                                                ))}
                                            </select>
                                        ) : (
                                            r.affiliation_name
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-slate-700 align-top">
                                        {isEditing ? (
                                            <input
                                                value={editDraft.approver_name}
                                                onChange={(e) => setEditDraft((d) => ({ ...d, approver_name: e.target.value }))}
                                                className={ui.input}
                                                disabled={saving}
                                            />
                                        ) : (
                                            r.approver_name || '—'
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-slate-700 align-top">
                                        {isEditing ? (
                                            <input
                                                type="email"
                                                value={editDraft.approver_email}
                                                onChange={(e) => setEditDraft((d) => ({ ...d, approver_email: e.target.value }))}
                                                className={ui.input}
                                                disabled={saving}
                                            />
                                        ) : (
                                            r.approver_email
                                        )}
                                    </td>
                                    <td className="px-4 py-2 align-top">
                                        {isEditing ? (
                                            <input
                                                type="checkbox"
                                                checked={Boolean(editDraft.is_active)}
                                                disabled={saving}
                                                onChange={(e) => setEditDraft((d) => ({ ...d, is_active: e.target.checked }))}
                                                className="accent-xu-primary"
                                            />
                                        ) : (
                                            <input
                                                type="checkbox"
                                                checked={Boolean(r.is_active)}
                                                disabled={saving}
                                                onChange={(e) => void updateRow(r.id, { is_active: e.target.checked })}
                                                className="accent-xu-primary"
                                            />
                                        )}
                                    </td>
                                    <td className="px-4 py-2 align-top whitespace-nowrap">
                                        {isEditing ? (
                                            <div className="flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    disabled={saving || !editDraft.affiliation_name}
                                                    onClick={() => saveEdit()}
                                                    className="text-xs text-xu-primary font-medium hover:underline disabled:opacity-50"
                                                >
                                                    Save
                                                </button>
                                                <button
                                                    type="button"
                                                    disabled={saving}
                                                    onClick={() => cancelEdit()}
                                                    className="text-xs text-slate-600 hover:underline disabled:opacity-50"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        ) : (
                                            <div className="flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    disabled={saving}
                                                    onClick={() => startEdit(r)}
                                                    className="text-xs text-xu-primary font-medium hover:underline disabled:opacity-50"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => removeRow(r.id)}
                                                    disabled={saving}
                                                    className="text-xs text-red-700 hover:underline disabled:opacity-50"
                                                >
                                                    Delete
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
        </div>
    );
}

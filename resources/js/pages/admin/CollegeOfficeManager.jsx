import { useEffect, useMemo, useState } from 'react';
import api from '../../api';
import { unwrapData } from '../../utils/apiEnvelope';
import { ui } from '../../theme';

const TAB_COLLEGES = 'colleges';
const TAB_OFFICES = 'offices';

function ModalShell({ open, title, onClose, children, footer }) {
    if (!open) return null;
    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/45 p-2 pb-[max(0.5rem,env(safe-area-inset-bottom,0px))] pt-3 sm:items-center sm:p-4 sm:pb-4 sm:pt-4"
            role="dialog"
            aria-modal="true"
        >
            <div className="max-h-[min(90dvh,90vh)] w-full min-w-0 max-w-xl overflow-y-auto overflow-x-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/10">
                <div className="border-b border-slate-200/80 bg-slate-50 px-4 py-3 sm:px-5 sm:py-4">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</p>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Close
                        </button>
                    </div>
                </div>
                <div className="space-y-4 p-4 sm:p-5">{children}</div>
                {footer && <div className="border-t border-slate-200/80 bg-white px-4 py-3 sm:px-5">{footer}</div>}
            </div>
        </div>
    );
}

function EntityFormModal({ open, onClose, mode, kindLabel, initial, onSubmit, saving, error }) {
    const [name, setName] = useState(initial?.name || '');
    const [approverName, setApproverName] = useState(initial?.approver_name || '');
    const [approverEmail, setApproverEmail] = useState(initial?.approver_email || '');
    const [mappingActive, setMappingActive] = useState(
        initial?.mapping_active != null ? Boolean(initial.mapping_active) : true
    );

    useEffect(() => {
        if (!open) return;
        setName(initial?.name || '');
        setApproverName(initial?.approver_name || '');
        setApproverEmail(initial?.approver_email || '');
        setMappingActive(initial?.mapping_active != null ? Boolean(initial.mapping_active) : true);
    }, [open, initial]);

    return (
        <ModalShell
            open={open}
            onClose={onClose}
            title={`${mode === 'edit' ? 'Edit' : 'Add'} ${kindLabel}`}
            footer={
                <div className="flex flex-wrap items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={saving}
                        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            onSubmit({
                                name: name.trim(),
                                approver_name: approverName.trim() || null,
                                approver_email: approverEmail.trim() || null,
                                mapping_active: mappingActive,
                            })
                        }
                        disabled={saving || name.trim() === ''}
                        className={ui.btnPrimary}
                    >
                        {saving ? 'Saving…' : mode === 'edit' ? 'Save changes' : 'Add'}
                    </button>
                </div>
            }
        >
            {error && (
                <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg">{error}</div>
            )}
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    className={ui.input}
                    placeholder={`e.g. ${kindLabel === 'College' ? 'College of Arts and Sciences' : 'SACDEV'}`}
                />
                <p className="mt-1 text-xs text-slate-500">
                    This name is the system-wide source of truth (profiles, dean mapping dropdowns, and routing).
                </p>
            </div>

            <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4">
                <p className="text-xs font-bold uppercase tracking-wide text-slate-600">Approver email (optional)</p>
                <p className="mt-1 text-xs text-slate-500">
                    If set, we will create/update the dean/approver mapping for this {kindLabel.toLowerCase()}.
                </p>
                <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Approver name</label>
                        <input
                            value={approverName}
                            onChange={(e) => setApproverName(e.target.value)}
                            className={ui.input}
                            placeholder="Optional"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Approver email</label>
                        <input
                            type="email"
                            value={approverEmail}
                            onChange={(e) => setApproverEmail(e.target.value)}
                            className={ui.input}
                            placeholder="dean@xu.edu.ph"
                        />
                    </div>
                </div>
                <label className="mt-3 flex items-center gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        className="accent-xu-primary"
                        checked={mappingActive}
                        onChange={(e) => setMappingActive(e.target.checked)}
                    />
                    Mapping active
                </label>
            </div>
        </ModalShell>
    );
}

export default function CollegeOfficeManager() {
    const [tab, setTab] = useState(TAB_COLLEGES);
    const [colleges, setColleges] = useState([]);
    const [offices, setOffices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const [modalOpen, setModalOpen] = useState(false);
    const [modalMode, setModalMode] = useState('create'); // create | edit
    const [editingRow, setEditingRow] = useState(null);
    const [saving, setSaving] = useState(false);
    const [modalError, setModalError] = useState('');

    const kindLabel = tab === TAB_COLLEGES ? 'College' : 'Office';
    const list = tab === TAB_COLLEGES ? colleges : offices;

    const load = () => {
        setLoading(true);
        setError('');
        const collegesReq = api.get('/admin/colleges').then(({ data }) => unwrapData(data));
        const officesReq = api.get('/admin/offices').then(({ data }) => unwrapData(data));
        Promise.all([collegesReq, officesReq])
            .then(([c, o]) => {
                setColleges(Array.isArray(c) ? c : []);
                setOffices(Array.isArray(o) ? o : []);
            })
            .catch((err) => setError(err.response?.data?.message || 'Failed to load colleges and offices.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
    }, []);

    const openCreate = () => {
        setModalMode('create');
        setEditingRow(null);
        setModalError('');
        setModalOpen(true);
    };

    const openEdit = (row) => {
        setModalMode('edit');
        setEditingRow(row);
        setModalError('');
        setModalOpen(true);
    };

    const closeModal = () => {
        if (saving) return;
        setModalOpen(false);
    };

    const endpointBase = tab === TAB_COLLEGES ? '/admin/colleges' : '/admin/offices';

    const submitModal = async (payload) => {
        setSaving(true);
        setModalError('');
        try {
            if (modalMode === 'edit' && editingRow) {
                await api.patch(`${endpointBase}/${editingRow.id}`, payload);
            } else {
                await api.post(endpointBase, payload);
            }
            setModalOpen(false);
            load();
        } catch (err) {
            const d = err.response?.data;
            setModalError(
                d?.errors?.name?.[0] ||
                d?.errors?.approver_email?.[0] ||
                d?.errors?.college?.[0] ||
                d?.errors?.office?.[0] ||
                d?.message ||
                'Save failed.'
            );
        } finally {
            setSaving(false);
        }
    };

    const removeRow = async (row) => {
        if (!row) return;
        if (!confirm(`Delete ${kindLabel.toLowerCase()} “${row.name}”?`)) return;
        setSaving(true);
        setError('');
        try {
            await api.delete(`${endpointBase}/${row.id}`);
            load();
        } catch (err) {
            const d = err.response?.data;
            setError(d?.errors?.college?.[0] || d?.errors?.office?.[0] || d?.message || 'Delete failed.');
        } finally {
            setSaving(false);
        }
    };

    const tabs = useMemo(
        () => [
            { key: TAB_COLLEGES, label: 'Colleges' },
            { key: TAB_OFFICES, label: 'Offices' },
        ],
        []
    );

    return (
        <div className="min-w-0">
            <h1 className={`${ui.pageTitle} mb-2`}>Colleges & Offices</h1>
            <p className="text-sm text-slate-600 mb-4">
                Central source of truth for affiliations used across profiles and dean/approver routing.
            </p>

            {error && (
                <div className="mb-4 text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded">
                    {error}
                </div>
            )}

            <div className={`mb-4 flex flex-wrap items-center justify-between gap-3 p-3 ${ui.cardFlat}`}>
                <div className="flex flex-wrap gap-2">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            onClick={() => setTab(t.key)}
                            className={[
                                'rounded-lg border px-3 py-2 text-sm font-semibold transition',
                                tab === t.key
                                    ? 'border-xu-secondary/40 bg-xu-primary/[0.06] text-xu-primary'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                            ].join(' ')}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
                <button type="button" onClick={openCreate} className={ui.btnPrimary}>
                    Add new {kindLabel.toLowerCase()}
                </button>
            </div>

            <div className="overflow-x-auto bg-white rounded-lg border border-slate-200 shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-xu-primary/5 text-xu-primary border-b border-slate-200">
                        <tr>
                            <th className="text-left px-4 py-2 font-semibold">Name</th>
                            <th className="text-left px-4 py-2 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading && (
                            <tr>
                                <td className="px-4 py-3 text-slate-500" colSpan={2}>
                                    Loading…
                                </td>
                            </tr>
                        )}
                        {!loading && list.length === 0 && (
                            <tr>
                                <td className="px-4 py-3 text-slate-500" colSpan={2}>
                                    No {kindLabel.toLowerCase()} entries yet.
                                </td>
                            </tr>
                        )}
                        {!loading &&
                            list.map((row) => (
                                <tr key={row.id} className="border-t border-slate-200">
                                    <td className="px-4 py-2 text-slate-900 font-medium">{row.name}</td>
                                    <td className="px-4 py-2 whitespace-nowrap">
                                        <div className="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                onClick={() => openEdit(row)}
                                                className="text-xs text-xu-primary font-medium hover:underline disabled:opacity-50"
                                                disabled={saving}
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => removeRow(row)}
                                                className="text-xs text-red-700 hover:underline disabled:opacity-50"
                                                disabled={saving}
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            <EntityFormModal
                open={modalOpen}
                onClose={closeModal}
                mode={modalMode === 'edit' ? 'edit' : 'create'}
                kindLabel={kindLabel}
                initial={editingRow}
                onSubmit={submitModal}
                saving={saving}
                error={modalError}
            />
        </div>
    );
}


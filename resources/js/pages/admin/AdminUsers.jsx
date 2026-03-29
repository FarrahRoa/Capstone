import { useEffect, useMemo, useRef, useState } from 'react';
import api from '../../api';
import { ui } from '../../theme';

const PER_PAGE_OPTIONS = [10, 15, 25];

export default function AdminUsers() {
    const [users, setUsers] = useState([]);
    const [meta, setMeta] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: null,
        to: null,
    });
    const [roles, setRoles] = useState([]);
    const [searchInput, setSearchInput] = useState('');
    const [appliedSearch, setAppliedSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState('');
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(15);
    const [loading, setLoading] = useState(true);
    const [savingUserId, setSavingUserId] = useState(null);
    const [loadError, setLoadError] = useState('');
    const [rowFeedback, setRowFeedback] = useState({});
    const rowFeedbackTimers = useRef({});

    const clearRowTimer = (userId) => {
        if (rowFeedbackTimers.current[userId]) {
            clearTimeout(rowFeedbackTimers.current[userId]);
            delete rowFeedbackTimers.current[userId];
        }
    };

    const setRowMessage = (userId, payload) => {
        clearRowTimer(userId);
        setRowFeedback((prev) => ({ ...prev, [userId]: payload }));
        if (payload?.type === 'success') {
            rowFeedbackTimers.current[userId] = setTimeout(() => {
                setRowFeedback((prev) => {
                    const next = { ...prev };
                    if (next[userId]?.type === 'success') delete next[userId];
                    return next;
                });
                delete rowFeedbackTimers.current[userId];
            }, 2500);
        }
    };

    useEffect(() => {
        const id = setTimeout(() => {
            const next = searchInput.trim();
            setAppliedSearch((prev) => (prev === next ? prev : next));
        }, 400);
        return () => clearTimeout(id);
    }, [searchInput]);

    useEffect(() => {
        setPage(1);
    }, [appliedSearch, roleFilter, perPage]);

    const loadUsers = () => {
        setLoading(true);
        setLoadError('');
        const params = { page, per_page: perPage };
        if (appliedSearch) params.search = appliedSearch;
        if (roleFilter) params.role = roleFilter;
        api.get('/admin/users', { params })
            .then(({ data }) => {
                setUsers(data.data || []);
                setMeta({
                    current_page: data.current_page ?? 1,
                    last_page: data.last_page ?? 1,
                    per_page: data.per_page ?? perPage,
                    total: data.total ?? 0,
                    from: data.from ?? null,
                    to: data.to ?? null,
                });
            })
            .catch((err) => {
                const message = err.response?.data?.message || 'Failed to load users.';
                setLoadError(message);
                setUsers([]);
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        loadUsers();
    }, [appliedSearch, roleFilter, page, perPage]);

    useEffect(() => {
        api.get('/admin/roles')
            .then(({ data }) => setRoles(data))
            .catch(() => setRoles([]));
    }, []);

    useEffect(() => () => {
        Object.values(rowFeedbackTimers.current).forEach(clearTimeout);
    }, []);

    const onSearchSubmit = (e) => {
        e.preventDefault();
        setAppliedSearch(searchInput.trim());
        setPage(1);
    };

    const updateUser = async (user, payload, fallbackMessage) => {
        setSavingUserId(user.id);
        setLoadError('');
        clearRowTimer(user.id);
        setRowFeedback((prev) => {
            const next = { ...prev };
            delete next[user.id];
            return next;
        });
        try {
            const { data } = await api.patch(`/admin/users/${user.id}`, payload);
            setUsers((prev) => prev.map((u) => (u.id === user.id ? data : u)));
            setRowMessage(user.id, { type: 'success', text: 'Saved.' });
        } catch (err) {
            const firstKey = Object.keys(payload)[0];
            const message = err.response?.data?.message || err.response?.data?.errors?.[firstKey]?.[0] || fallbackMessage;
            setRowMessage(user.id, { type: 'error', text: message });
        } finally {
            setSavingUserId(null);
        }
    };

    const updateEligibility = (user, field, value) =>
        updateUser(user, { [field]: value }, 'Failed to update eligibility.');

    const updateRole = (user, roleId) =>
        updateUser(user, { role_id: Number(roleId) }, 'Failed to update user role.');

    const rows = useMemo(() => users, [users]);

    const canPrev = meta.current_page > 1;
    const canNext = meta.current_page < meta.last_page;

    return (
        <div>
            <h1 className={`${ui.pageTitle} mb-4`}>User management</h1>
            <p className="text-sm text-slate-600 mb-4">
                Search and role filter apply together. Results update as you type (short delay) or when you click Search.
            </p>

            <form onSubmit={onSearchSubmit} className="mb-4 flex flex-wrap items-end gap-2">
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">Search name or email</label>
                    <input
                        type="text"
                        value={searchInput}
                        onChange={(e) => setSearchInput(e.target.value)}
                        placeholder="Type to search…"
                        className="w-full max-w-md rounded-lg border border-slate-200 px-3 py-2 focus:ring-2 focus:ring-xu-secondary/35 focus:border-xu-secondary"
                    />
                </div>
                <button type="submit" className={ui.btnPrimary}>
                    Search now
                </button>
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">Role</label>
                    <select
                        value={roleFilter}
                        onChange={(e) => {
                            setRoleFilter(e.target.value);
                            setPage(1);
                        }}
                        className={ui.select}
                    >
                        <option value="">All roles</option>
                        {roles.map((r) => (
                            <option key={r.id} value={r.slug}>{r.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">Rows per page</label>
                    <select
                        value={perPage}
                        onChange={(e) => {
                            setPerPage(Number(e.target.value));
                            setPage(1);
                        }}
                        className={ui.select}
                    >
                        {PER_PAGE_OPTIONS.map((n) => (
                            <option key={n} value={n}>{n}</option>
                        ))}
                    </select>
                </div>
            </form>

            {loadError && (
                <div className="mb-4 text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded">{loadError}</div>
            )}
            {loading && <p className="text-slate-600">Loading…</p>}

            {!loading && (
                <>
                    <div className="mb-2 text-sm text-slate-600">
                        {meta.total === 0
                            ? 'No users match.'
                            : `Showing ${meta.from ?? 0}–${meta.to ?? 0} of ${meta.total}`}
                    </div>
                    <div className="overflow-x-auto bg-white rounded-lg border border-slate-200 shadow-sm">
                        <table className="min-w-full text-sm">
                            <thead className="bg-xu-primary/5 text-xu-primary border-b border-slate-200">
                                <tr>
                                    <th className="text-left px-4 py-2 font-semibold">Name</th>
                                    <th className="text-left px-4 py-2 font-semibold">Email</th>
                                    <th className="text-left px-4 py-2 font-semibold">Role</th>
                                    <th className="text-left px-4 py-2 font-semibold">Med Confab</th>
                                    <th className="text-left px-4 py-2 font-semibold">Boardroom</th>
                                    <th className="text-left px-4 py-2 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <tr>
                                        <td className="px-4 py-3 text-slate-500" colSpan={6}>
                                            No users found.
                                        </td>
                                    </tr>
                                )}
                                {rows.map((u) => {
                                    const busy = savingUserId === u.id;
                                    const fb = rowFeedback[u.id];
                                    return (
                                        <tr
                                            key={u.id}
                                            className={`border-t border-slate-200 ${busy ? 'opacity-60' : ''}`}
                                        >
                                            <td className="px-4 py-2 text-slate-900 font-medium">{u.name}</td>
                                            <td className="px-4 py-2 text-slate-700">{u.email}</td>
                                            <td className="px-4 py-2 text-slate-700">
                                                <select
                                                    value={u.role_id || ''}
                                                    disabled={busy}
                                                    onChange={(e) => updateRole(u, e.target.value)}
                                                    className="rounded border border-slate-200 px-2 py-1 focus:ring-2 focus:ring-xu-secondary/30 disabled:cursor-not-allowed"
                                                >
                                                    {roles.map((r) => (
                                                        <option key={r.id} value={r.id}>{r.name}</option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="px-4 py-2">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(u.med_confab_eligible)}
                                                    disabled={busy}
                                                    onChange={(e) => updateEligibility(u, 'med_confab_eligible', e.target.checked)}
                                                    className="accent-xu-primary disabled:cursor-not-allowed"
                                                />
                                            </td>
                                            <td className="px-4 py-2">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(u.boardroom_eligible)}
                                                    disabled={busy}
                                                    onChange={(e) => updateEligibility(u, 'boardroom_eligible', e.target.checked)}
                                                    className="accent-xu-primary disabled:cursor-not-allowed"
                                                />
                                            </td>
                                            <td className="px-4 py-2 text-xs max-w-[14rem]">
                                                {fb?.type === 'success' && (
                                                    <span className="text-green-700 font-medium">{fb.text}</span>
                                                )}
                                                {fb?.type === 'error' && (
                                                    <span className="text-red-700">{fb.text}</span>
                                                )}
                                                {!fb && busy && <span className="text-slate-500">Saving…</span>}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {meta.last_page > 1 && (
                        <div className="mt-4 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                disabled={!canPrev || loading}
                                onClick={() => setPage((p) => Math.max(1, p - 1))}
                                className="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-700 hover:bg-xu-page hover:border-xu-secondary/30 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Previous
                            </button>
                            <span className="text-sm text-slate-600">
                                Page {meta.current_page} of {meta.last_page}
                            </span>
                            <button
                                type="button"
                                disabled={!canNext || loading}
                                onClick={() => setPage((p) => p + 1)}
                                className="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-700 hover:bg-xu-page hover:border-xu-secondary/30 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Next
                            </button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

import { Link } from 'react-router-dom';
import { formatLogTime, formatReservationRange } from '../../utils/timeDisplay';
import { ui } from '../../theme';

function userInviteSetupLabel(u) {
    if (u?.admin_invited_at && !u?.admin_password_set_at) return 'Invite pending';
    return '—';
}

export default function AdminDashboardPanels({
    canUsers,
    canQueue,
    isAdminContext,
    loading,
    statsError,
    recentUsers,
    feedLoading,
    feedError,
    recentLogs,
}) {
    const recentUsersSection =
        isAdminContext && canUsers ? (
            <section className="rounded-xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm">
                <h2 className={`${ui.sectionLabel} mb-1`}>Recent users</h2>
                <p className="text-xs text-slate-600 mb-4">
                    Newest accounts first (name, role, affiliation, and librarian invite status when applicable).
                </p>
                {loading && <p className="text-sm text-slate-600">Loading…</p>}
                {!loading && statsError && (
                    <p className="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        Recent users could not be loaded.
                    </p>
                )}
                {!loading && !statsError && recentUsers.length === 0 && (
                    <p className="text-sm text-slate-600">No users found.</p>
                )}
                {!loading && !statsError && recentUsers.length > 0 && (
                    <div className="overflow-x-auto -mx-1">
                        <table className="min-w-[40rem] w-full text-sm text-left">
                            <thead>
                                <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                    <th className="py-2 pr-3 font-semibold">Name</th>
                                    <th className="py-2 pr-3 font-semibold">Email</th>
                                    <th className="py-2 pr-3 font-semibold">Role</th>
                                    <th className="py-2 pr-3 font-semibold">Affiliation</th>
                                    <th className="py-2 pr-3 font-semibold whitespace-nowrap">Joined</th>
                                    <th className="py-2 pr-3 font-semibold">Invite / setup</th>
                                </tr>
                            </thead>
                            <tbody className="text-slate-800">
                                {recentUsers.map((u) => (
                                    <tr key={u.id} className="border-b border-slate-100 last:border-0">
                                        <td className="py-2.5 pr-3 font-medium text-xu-primary whitespace-nowrap">{u.name}</td>
                                        <td className="py-2.5 pr-3 text-slate-600 break-all max-w-[12rem]">{u.email}</td>
                                        <td className="py-2.5 pr-3 whitespace-nowrap">{u.role?.name ?? '—'}</td>
                                        <td className="py-2.5 pr-3 text-slate-600 max-w-[14rem]">
                                            {u.college_office ? (
                                                u.college_office
                                            ) : u.user_type ? (
                                                <span className="capitalize">{String(u.user_type).replace(/_/g, ' ')}</span>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="py-2.5 pr-3 text-slate-600 whitespace-nowrap text-xs">
                                            {u.created_at ? formatLogTime(u.created_at) : '—'}
                                        </td>
                                        <td className="py-2.5 pr-3 text-xs text-slate-600 whitespace-nowrap">
                                            {userInviteSetupLabel(u)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Link to="/admin/users" className={`inline-block mt-4 text-sm ${ui.linkAccent}`}>
                    User management →
                </Link>
            </section>
        ) : null;

    const recentReservationActivitySection =
        isAdminContext && canQueue ? (
            <section className="rounded-xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm">
                <h2 className={`${ui.sectionLabel} mb-1`}>Recent reservation activity</h2>
                <p className="text-xs text-slate-600 mb-4">
                    Audit log: created, edited, approved, rejected, cancelled, and override actions with requester, space,
                    and booking window.
                </p>
                {feedLoading && <p className="text-sm text-slate-600">Loading…</p>}
                {!feedLoading && feedError && (
                    <p className="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        Recent activity could not be loaded.
                    </p>
                )}
                {!feedLoading && !feedError && recentLogs.length === 0 && (
                    <p className="text-sm text-slate-600">No reservation activity recorded yet.</p>
                )}
                {!feedLoading && !feedError && recentLogs.length > 0 && (
                    <div className="overflow-x-auto -mx-1">
                        <table className="min-w-[48rem] w-full text-sm text-left">
                            <thead>
                                <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                    <th className="py-2 pr-3 font-semibold whitespace-nowrap">When</th>
                                    <th className="py-2 pr-3 font-semibold">Action</th>
                                    <th className="py-2 pr-3 font-semibold">Requester</th>
                                    <th className="py-2 pr-3 font-semibold">Space</th>
                                    <th className="py-2 pr-3 font-semibold">Booking</th>
                                    <th className="py-2 pr-3 font-semibold">Actor</th>
                                </tr>
                            </thead>
                            <tbody className="text-slate-800">
                                {recentLogs.map((log) => {
                                    const req = log.requester;
                                    const slotLabel =
                                        log.slot?.start_at && log.slot?.end_at
                                            ? formatReservationRange(log.slot.start_at, log.slot.end_at)
                                            : '—';
                                    const actorLabel =
                                        log.actor?.name ||
                                        (log.actor_type === 'system' ? 'System' : log.actor_type === 'admin'
                                            ? 'Admin'
                                            : log.actor_type === 'user'
                                              ? 'User'
                                              : '—');
                                    const notesShort =
                                        log.notes && String(log.notes).length > 80
                                            ? `${String(log.notes).slice(0, 80)}…`
                                            : log.notes || '';
                                    return (
                                        <tr key={log.id} className="border-b border-slate-100 last:border-0 align-top">
                                            <td className="py-2.5 pr-3 text-xs text-slate-600 whitespace-nowrap">
                                                {log.created_at ? formatLogTime(log.created_at) : '—'}
                                            </td>
                                            <td className="py-2.5 pr-3">
                                                <span className="font-medium text-xu-primary">{log.action_label}</span>
                                                {notesShort ? (
                                                    <span className="block text-xs text-slate-500 mt-0.5">{notesShort}</span>
                                                ) : null}
                                            </td>
                                            <td className="py-2.5 pr-3 text-slate-700">
                                                {req ? (
                                                    <>
                                                        <span className="font-medium">{req.name}</span>
                                                        <span className="block text-xs text-slate-500 break-all max-w-[11rem]">
                                                            {req.email}
                                                        </span>
                                                    </>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="py-2.5 pr-3 text-slate-700">{log.space?.name ?? '—'}</td>
                                            <td className="py-2.5 pr-3 text-xs text-slate-600 whitespace-nowrap">{slotLabel}</td>
                                            <td className="py-2.5 pr-3 text-xs text-slate-600">
                                                {log.actor ? (
                                                    <>
                                                        <span className="font-medium text-slate-800">{log.actor.name}</span>
                                                        <span className="block text-slate-500 break-all max-w-[10rem]">
                                                            {log.actor.email}
                                                        </span>
                                                    </>
                                                ) : (
                                                    actorLabel
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
                <Link to="/admin/reservations" className={`inline-block mt-4 text-sm ${ui.linkAccent}`}>
                    Reservation queue →
                </Link>
            </section>
        ) : null;

    return (
        <>
            {recentUsersSection}
            {recentReservationActivitySection}
        </>
    );
}


import { useState, useEffect } from 'react';
import api from '../../api';
import { ui } from '../../theme';

export default function AdminReports() {
    const [period, setPeriod] = useState('monthly');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState('');

    const load = () => {
        setLoading(true);
        setError('');
        const params = { period };
        if (period === 'custom') { params.from = from; params.to = to; }
        api.get('/admin/reports', { params })
            .then(({ data: res }) => setData(res))
            .catch((err) => setError(err.response?.data?.message || 'Failed to load reports.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => { if (period !== 'custom' || (from && to)) load(); }, [period, from, to]);

    const exportPdf = () => {
        setExporting(true);
        const params = { period, format: 'pdf' };
        if (period === 'custom') { params.from = from; params.to = to; }
        api.get('/admin/reports/export', { params, responseType: 'blob' })
            .then((res) => {
                const url = window.URL.createObjectURL(new Blob([res.data]));
                const a = document.createElement('a');
                a.href = url;
                a.setAttribute('download', 'library-report.pdf');
                a.click();
                window.URL.revokeObjectURL(url);
            })
            .finally(() => setExporting(false));
    };

    const exportJson = () => {
        setExporting(true);
        const params = { period, format: 'json' };
        if (period === 'custom') { params.from = from; params.to = to; }
        api.get('/admin/reports/export', { params })
            .then((res) => {
                const json = JSON.stringify(res.data, null, 2);
                const url = window.URL.createObjectURL(new Blob([json], { type: 'application/json' }));
                const a = document.createElement('a');
                a.href = url;
                a.setAttribute('download', 'library-report.json');
                a.click();
                window.URL.revokeObjectURL(url);
            })
            .finally(() => setExporting(false));
    };

    return (
        <div>
            <h1 className={`${ui.pageTitle} mb-4`}>Reports</h1>
            <div className="flex flex-wrap gap-4 mb-6">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Period</label>
                    <select value={period} onChange={(e) => setPeriod(e.target.value)} className={ui.select}>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="annual">Annual</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                {period === 'custom' && (
                    <>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">From</label>
                            <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className={ui.input} />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">To</label>
                            <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className={ui.input} />
                        </div>
                    </>
                )}
                <div className="flex items-end">
                    <button onClick={load} disabled={loading} className={ui.btnPrimary}>
                        Apply
                    </button>
                </div>
                <div className="flex items-end">
                    <button
                        onClick={exportPdf}
                        disabled={exporting || !data}
                        className="rounded-lg border-2 border-xu-secondary text-xu-secondary bg-white px-4 py-2 font-medium hover:bg-xu-page disabled:opacity-50 transition-colors"
                    >
                        Export PDF
                    </button>
                </div>
                <div className="flex items-end">
                    <button
                        onClick={exportJson}
                        disabled={exporting || !data}
                        className="rounded-lg border border-slate-300 text-slate-700 bg-white px-4 py-2 font-medium hover:bg-slate-50 disabled:opacity-50"
                    >
                        Export JSON
                    </button>
                </div>
            </div>
            {error && <div className="mb-4 text-red-700 text-sm bg-red-50 border border-red-200 rounded p-3">{error}</div>}
            {loading && <p className="text-slate-600">Loading…</p>}
            {data && !loading && (
                <div className={`space-y-4 p-6 ${ui.cardFlat}`}>
                    <p className="text-slate-600">
                        Period: {data.period?.from} – {data.period?.to}
                    </p>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Summary</h2>
                        <div className="grid md:grid-cols-4 gap-3 text-sm">
                            <div className="bg-xu-primary/5 border border-slate-200/80 rounded-lg p-3">
                                <strong>Total reservations:</strong> {data.summary?.total_reservations ?? 0}
                            </div>
                            <div className="bg-xu-primary/5 border border-slate-200/80 rounded-lg p-3">
                                <strong>Approved:</strong> {data.summary?.approved_reservations ?? 0}
                            </div>
                            <div className="bg-white border border-slate-200/80 rounded-lg p-3">
                                <strong>Avg duration:</strong> {data.summary?.average_reservation_duration_minutes ?? 0} min
                            </div>
                            <div className="bg-white border border-slate-200/80 rounded-lg p-3">
                                <strong>Avg approval:</strong> {data.summary?.average_approval_time_minutes ?? 0} min
                            </div>
                        </div>
                    </section>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Status totals</h2>
                        <div className="grid md:grid-cols-3 gap-2">
                            {(data.status_totals || []).map((row) => (
                                <div key={row.status} className="bg-xu-page border border-slate-200/80 rounded-lg p-3 text-sm">
                                    <strong>{row.label}:</strong> {row.count}
                                </div>
                            ))}
                        </div>
                    </section>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Action totals</h2>
                        <div className="grid md:grid-cols-3 gap-2">
                            {(data.action_totals || []).map((row) => (
                                <div key={row.action} className="bg-xu-page border border-slate-200/80 rounded-lg p-3 text-sm">
                                    <strong>{row.label}:</strong> {row.count}
                                </div>
                            ))}
                        </div>
                    </section>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Recent activity</h2>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border border-slate-200 rounded-lg overflow-hidden">
                                <thead className="bg-xu-primary/5 text-xu-primary">
                                    <tr>
                                        <th className="px-3 py-2 text-left">When</th>
                                        <th className="px-3 py-2 text-left">Action</th>
                                        <th className="px-3 py-2 text-left">Actor</th>
                                        <th className="px-3 py-2 text-left">Requester</th>
                                        <th className="px-3 py-2 text-left">Space</th>
                                        <th className="px-3 py-2 text-left">Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(data.recent_activity || []).length === 0 ? (
                                        <tr><td className="px-3 py-2 text-slate-500" colSpan={6}>No activity in period.</td></tr>
                                    ) : (
                                        (data.recent_activity || []).map((row) => (
                                            <tr key={row.id} className="border-t border-slate-200">
                                                <td className="px-3 py-2">{row.created_at || '-'}</td>
                                                <td className="px-3 py-2">{row.action_label}</td>
                                                <td className="px-3 py-2">{row.actor_name || '-'}</td>
                                                <td className="px-3 py-2">{row.requester_name || '-'}</td>
                                                <td className="px-3 py-2">{row.space_name || '-'}</td>
                                                <td className="px-3 py-2">{row.notes || '-'}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Reservations by college/office</h2>
                        <pre className="text-sm bg-xu-page border border-slate-200/80 p-3 rounded-lg overflow-auto">{JSON.stringify(data.reservations_by_college_office, null, 2)}</pre>
                    </section>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Student – by college</h2>
                        <pre className="text-sm bg-xu-page border border-slate-200/80 p-3 rounded-lg overflow-auto">{JSON.stringify(data.student_college, null, 2)}</pre>
                    </section>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Student – by year level</h2>
                        <pre className="text-sm bg-xu-page border border-slate-200/80 p-3 rounded-lg overflow-auto">{JSON.stringify(data.student_year_level, null, 2)}</pre>
                    </section>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Room utilization</h2>
                        <pre className="text-sm bg-xu-page border border-slate-200/80 p-3 rounded-lg overflow-auto">{JSON.stringify(data.room_utilization, null, 2)}</pre>
                    </section>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Peak hours</h2>
                        <pre className="text-sm bg-xu-page border border-slate-200/80 p-3 rounded-lg overflow-auto">{JSON.stringify(data.peak_hours, null, 2)}</pre>
                    </section>
                </div>
            )}
        </div>
    );
}

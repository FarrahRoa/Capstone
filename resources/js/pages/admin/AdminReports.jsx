import { useEffect, useMemo, useState } from 'react';
import api from '../../api';
import { unwrapData } from '../../utils/apiEnvelope';
import { ui } from '../../theme';

export default function AdminReports() {
    const [period, setPeriod] = useState('monthly');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState('');
    const [charts, setCharts] = useState(null);

    const load = () => {
        setLoading(true);
        setError('');
        const params = { period };
        if (period === 'custom') { params.from = from; params.to = to; }
        api.get('/admin/reports', { params })
            .then(({ data: body }) => setData(unwrapData(body)))
            .catch((err) => setError(err.response?.data?.message || 'Failed to load reports.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => { if (period !== 'custom' || (from && to)) load(); }, [period, from, to]);

    useEffect(() => {
        if (!data) return;
        if (charts) return;
        let cancelled = false;
        import('../../components/reports/ReportCharts')
            .then((m) => {
                if (!cancelled) setCharts(m);
            })
            .catch(() => {
                if (!cancelled) setCharts(null);
            });
        return () => {
            cancelled = true;
        };
    }, [data, charts]);

    const chartSeries = useMemo(() => {
        if (!data || !charts) return null;
        return {
            collegeOffice: charts.bucketsFromRecord(data.reservations_by_college_office),
            studentCollege: charts.bucketsFromRecord(data.student_college),
            facultyOffice: charts.bucketsFromRecord(data.faculty_staff_office ?? {}),
            yearLevel: charts.bucketsFromRecord(data.student_year_level),
            rooms: charts.itemsFromRoomUtilization(data.room_utilization),
            peakFull: charts.peakHoursFullSeries(data.peak_hours),
        };
    }, [data, charts]);

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

    return (
        <div className="min-w-0">
            <h1 className={`${ui.pageTitle} mb-4`}>Reports</h1>
            <div className="mb-6 flex min-w-0 flex-wrap gap-3 sm:gap-4">
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
            </div>
            {error && <div className="mb-4 text-red-700 text-sm bg-red-50 border border-red-200 rounded p-3">{error}</div>}
            {loading && <p className="text-slate-600">Loading…</p>}
            {data && !loading && (
                <div className={`min-w-0 space-y-4 p-4 sm:p-6 ${ui.cardFlat}`}>
                    <p className="text-slate-600">
                        Period: {data.period?.from} – {data.period?.to}
                    </p>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Summary</h2>
                        <div className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 md:grid-cols-4">
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
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3">
                            {(data.status_totals || []).map((row) => (
                                <div key={row.status} className="bg-xu-page border border-slate-200/80 rounded-lg p-3 text-sm">
                                    <strong>{row.label}:</strong> {row.count}
                                </div>
                            ))}
                        </div>
                    </section>
                    <section>
                        <h2 className="font-semibold text-xu-primary font-serif mb-2">Action totals</h2>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3">
                            {(data.action_totals || []).map((row) => (
                                <div key={row.action} className="bg-xu-page border border-slate-200/80 rounded-lg p-3 text-sm">
                                    <strong>{row.label}:</strong> {row.count}
                                </div>
                            ))}
                        </div>
                    </section>
                    {chartSeries && (
                        <div className="space-y-6">
                            <charts.ChartBlock
                                title="Reservations by college/office"
                                subtitle="Approved and other statuses in range, grouped by requester college or office."
                                empty={chartSeries.collegeOffice.length === 0}
                            >
                                <charts.HorizontalBarChart items={chartSeries.collegeOffice} />
                            </charts.ChartBlock>
                            <charts.ChartBlock
                                title="Student – by college"
                                subtitle="Reservations from student accounts, grouped by saved college."
                                empty={chartSeries.studentCollege.length === 0}
                            >
                                <charts.CategoryColumnChart items={chartSeries.studentCollege} />
                            </charts.ChartBlock>
                            <charts.ChartBlock
                                title="Employee/Staff – by office or department"
                                subtitle="Reservations from faculty/staff accounts, grouped by saved office or department."
                                empty={chartSeries.facultyOffice.length === 0}
                            >
                                <charts.HorizontalBarChart items={chartSeries.facultyOffice} variant="secondary" />
                            </charts.ChartBlock>
                            <charts.ChartBlock
                                title="Student – by year level"
                                subtitle="Distribution of student reservations by year level (donut when there are few categories)."
                                empty={chartSeries.yearLevel.length === 0}
                            >
                                {chartSeries.yearLevel.length <= 8 ? (
                                    <charts.DonutChart items={chartSeries.yearLevel} />
                                ) : (
                                    <charts.CategoryColumnChart items={chartSeries.yearLevel} />
                                )}
                            </charts.ChartBlock>
                            <charts.ChartBlock
                                title="Room utilization"
                                subtitle="Approved reservations per space."
                                empty={chartSeries.rooms.length === 0}
                            >
                                <charts.HorizontalBarChart items={chartSeries.rooms} />
                            </charts.ChartBlock>
                            <charts.ChartBlock
                                title="Peak hours"
                                subtitle="Approved reservations by reservation start hour, full day (00:00–23:00, library timezone)."
                                empty={charts.peakHoursSeriesIsEmpty(chartSeries.peakFull)}
                            >
                                <div className="max-h-[min(70vh,28rem)] overflow-y-auto pr-1">
                                    <charts.HorizontalBarChart
                                        items={chartSeries.peakFull}
                                        compact
                                        labelClassName="font-mono tabular-nums text-slate-800"
                                        labelColClassName="w-[4.75rem] sm:w-[5.25rem] shrink-0"
                                    />
                                </div>
                            </charts.ChartBlock>
                        </div>
                    )}
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
                                        <th className="px-3 py-2 text-left">Requester affiliation</th>
                                        <th className="px-3 py-2 text-left">Space</th>
                                        <th className="px-3 py-2 text-left">Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(data.recent_activity || []).length === 0 ? (
                                        <tr><td className="px-3 py-2 text-slate-500" colSpan={7}>No activity in period.</td></tr>
                                    ) : (
                                        (data.recent_activity || []).map((row) => (
                                            <tr key={row.id} className="border-t border-slate-200">
                                                <td className="px-3 py-2">{row.created_at || '-'}</td>
                                                <td className="px-3 py-2">{row.action_label}</td>
                                                <td className="px-3 py-2">{row.actor_name || '-'}</td>
                                                <td className="px-3 py-2">{row.requester_name || '-'}</td>
                                                <td className="px-3 py-2 text-slate-700">
                                                    {row.requester_affiliation
                                                        || (row.requester_college_office && String(row.requester_college_office).trim())
                                                        || 'Not specified'}
                                                </td>
                                                <td className="px-3 py-2">{row.space_name || '-'}</td>
                                                <td className="px-3 py-2">{row.notes || '-'}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            )}
        </div>
    );
}

import { useState, useEffect } from 'react';
import api from '../../api';

export default function AdminReports() {
    const [period, setPeriod] = useState('monthly');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);

    const load = () => {
        setLoading(true);
        const params = { period };
        if (period === 'custom') { params.from = from; params.to = to; }
        api.get('/admin/reports', { params })
            .then(({ data: res }) => setData(res))
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

    return (
        <div>
            <h1 className="text-2xl font-bold text-slate-800 mb-4">Reports</h1>
            <div className="flex flex-wrap gap-4 mb-6">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Period</label>
                    <select value={period} onChange={(e) => setPeriod(e.target.value)}
                        className="rounded-lg border border-slate-300 px-3 py-2">
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
                            <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">To</label>
                            <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2" />
                        </div>
                    </>
                )}
                <div className="flex items-end">
                    <button onClick={load} disabled={loading} className="bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-700 disabled:opacity-50">Apply</button>
                </div>
                <div className="flex items-end">
                    <button onClick={exportPdf} disabled={exporting || !data} className="bg-slate-600 text-white px-4 py-2 rounded-lg hover:bg-slate-500 disabled:opacity-50">Export PDF</button>
                </div>
            </div>
            {loading && <p className="text-slate-600">Loading...</p>}
            {data && !loading && (
                <div className="space-y-4 bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
                    <p className="text-slate-600">Period: {data.period?.from} – {data.period?.to}</p>
                    <section>
                        <h2 className="font-semibold text-slate-800 mb-2">Reservations by college/office</h2>
                        <pre className="text-sm bg-slate-50 p-3 rounded overflow-auto">{JSON.stringify(data.reservations_by_college_office, null, 2)}</pre>
                    </section>
                    <section>
                        <h2 className="font-semibold text-slate-800 mb-2">Student – by college</h2>
                        <pre className="text-sm bg-slate-50 p-3 rounded overflow-auto">{JSON.stringify(data.student_college, null, 2)}</pre>
                    </section>
                    <section>
                        <h2 className="font-semibold text-slate-800 mb-2">Student – by year level</h2>
                        <pre className="text-sm bg-slate-50 p-3 rounded overflow-auto">{JSON.stringify(data.student_year_level, null, 2)}</pre>
                    </section>
                    <section>
                        <h2 className="font-semibold text-slate-800 mb-2">Room utilization</h2>
                        <pre className="text-sm bg-slate-50 p-3 rounded overflow-auto">{JSON.stringify(data.room_utilization, null, 2)}</pre>
                    </section>
                    <section>
                        <h2 className="font-semibold text-slate-800 mb-2">Peak hours</h2>
                        <pre className="text-sm bg-slate-50 p-3 rounded overflow-auto">{JSON.stringify(data.peak_hours, null, 2)}</pre>
                    </section>
                    <p><strong>Average reservation duration:</strong> {data.average_reservation_duration_minutes} min</p>
                    <p><strong>Average approval time:</strong> {data.average_approval_time_minutes} min</p>
                </div>
            )}
        </div>
    );
}

import { useCallback, useEffect, useState } from 'react';
import api from '../../api';
import { unwrapData } from '../../utils/apiEnvelope';
import { ui } from '../../theme';

function formatTs(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return iso;
        return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return iso;
    }
}

export default function AdminCloudSync() {
    const [status, setStatus] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [uploading, setUploading] = useState(false);
    const [uploadBanner, setUploadBanner] = useState(null);

    const load = useCallback(async () => {
        setError('');
        setLoading(true);
        try {
            const { data } = await api.get('/admin/cloud-sync/status');
            setStatus(unwrapData(data));
        } catch (err) {
            setError(err.response?.data?.message || 'Could not load sync status.');
            setStatus(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const automaticLabel = (s) => {
        if (!s?.automatic_sync) return '—';
        const st = s.automatic_sync.state;
        if (st === 'disabled') return 'Disabled';
        if (st === 'idle') return 'Idle';
        return st;
    };

    const onUpload = async () => {
        setUploadBanner(null);
        setUploading(true);
        try {
            const { data } = await api.post('/admin/cloud-sync/upload');
            const inner = data?.data ?? data;
            const ok = Number(inner?.failed ?? 0) === 0;
            setUploadBanner({
                ok,
                message: data?.message || 'Upload finished.',
                detail: inner,
            });
            await load();
        } catch (err) {
            const d = err.response?.data;
            setUploadBanner({
                ok: false,
                message: d?.message || 'Upload failed.',
                detail: d?.data ?? null,
            });
            await load();
        } finally {
            setUploading(false);
        }
    };

    return (
        <div className="min-w-0">
            <h1 className={`${ui.pageTitle} mb-2`}>Cloud sync &amp; recovery</h1>
            <p className="text-sm text-slate-600 mb-6 max-w-3xl leading-relaxed">
                Monitor fallback-to-primary reservation uploads. Set <span className="font-mono text-xs">APP_SYNC_RECORD_ORIGIN=local_fallback</span> on the
                temporary server so new reservations are tracked here. Configure <span className="font-mono text-xs">CLOUD_SYNC_PUSH_URL</span> for HTTP
                push to the primary cloud.
            </p>

            {error && (
                <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{error}</div>
            )}

            {uploadBanner && (
                <div
                    className={`mb-4 rounded-lg border px-3 py-2 text-sm ${
                        uploadBanner.ok ? 'border-green-200 bg-green-50 text-green-900' : 'border-amber-200 bg-amber-50 text-amber-950'
                    }`}
                >
                    <p className="font-medium">{uploadBanner.message}</p>
                    {uploadBanner.detail && (
                        <ul className="mt-2 list-disc pl-5 text-xs text-slate-700">
                            {(uploadBanner.detail.messages || []).slice(0, 8).map((m, i) => (
                                <li key={i}>{m}</li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {loading && <p className="text-slate-600">Loading…</p>}

            {!loading && status && (
                <div className="space-y-6">
                    <section className={`p-5 sm:p-6 ${ui.cardFlat}`}>
                        <h2 className={`${ui.sectionLabel} mb-3`}>Sync status</h2>
                        <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                            <div className="rounded-lg border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Record origin (this server)</dt>
                                <dd className="mt-1 font-medium text-xu-primary">{status.record_origin}</dd>
                            </div>
                            <div className="rounded-lg border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Automatic sync</dt>
                                <dd className="mt-1 font-medium text-xu-primary">{automaticLabel(status)}</dd>
                                {status.automatic_sync?.note && (
                                    <p className="mt-1 text-xs text-slate-600 leading-relaxed">{status.automatic_sync.note}</p>
                                )}
                            </div>
                            <div className="rounded-lg border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Cloud reachable</dt>
                                <dd className="mt-1 font-medium text-xu-primary">
                                    {status.reachability_url_configured
                                        ? status.cloud_reachable === null
                                            ? 'Unknown'
                                            : status.cloud_reachable
                                              ? 'Yes'
                                              : 'No'
                                        : 'Not configured'}
                                </dd>
                            </div>
                            <div className="rounded-lg border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Push URL configured</dt>
                                <dd className="mt-1 font-medium text-xu-primary">{status.push_url_configured ? 'Yes' : 'No'}</dd>
                            </div>
                            <div className="rounded-lg border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending local changes</dt>
                                <dd className="mt-1 font-medium text-xu-primary">{status.pending_local_changes}</dd>
                            </div>
                            <div className="rounded-lg border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Last successful upload</dt>
                                <dd className="mt-1 font-medium text-xu-primary">{formatTs(status.last_success?.at)}</dd>
                                {status.last_success?.summary && (
                                    <p className="mt-1 text-xs text-slate-600">{status.last_success.summary}</p>
                                )}
                            </div>
                            <div className="rounded-lg border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Last failed upload</dt>
                                <dd className="mt-1 font-medium text-xu-primary">{formatTs(status.last_failure?.at)}</dd>
                                {status.last_failure?.summary && (
                                    <p className="mt-1 text-xs text-slate-600">{status.last_failure.summary}</p>
                                )}
                            </div>
                        </dl>
                        <div className="mt-5">
                            <button
                                type="button"
                                disabled={uploading}
                                onClick={onUpload}
                                className={`${ui.btnPrimary} touch-manipulation`}
                            >
                                {uploading ? 'Uploading…' : 'Upload newest local changes to cloud'}
                            </button>
                            <p className="mt-2 text-xs text-slate-500">
                                Uses each reservation&apos;s stable <span className="font-mono">cloud_sync_uuid</span> as the Idempotency-Key. Re-running upload
                                skips already-synced rows unless they were edited after the last successful push.
                            </p>
                        </div>
                    </section>

                    <section className={`p-5 sm:p-6 ${ui.cardFlat}`}>
                        <h2 className={`${ui.sectionLabel} mb-3`}>Recent sync activity</h2>
                        {(!status.recent_events || status.recent_events.length === 0) && (
                            <p className="text-sm text-slate-600">No events recorded yet.</p>
                        )}
                        {status.recent_events?.length > 0 && (
                            <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200/80 text-sm">
                                {status.recent_events.map((e) => (
                                    <li key={e.id} className="flex flex-col gap-0.5 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                                        <span className="font-medium text-xu-primary">{e.event_type}</span>
                                        <span className="text-xs text-slate-500">{formatTs(e.created_at)}</span>
                                        {e.summary && <span className="text-xs text-slate-700 sm:col-span-2">{e.summary}</span>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className={`p-5 sm:p-6 ${ui.cardFlat}`}>
                        <h2 className={`${ui.sectionLabel} mb-2`}>Cloud change feed</h2>
                        <p className="text-sm text-slate-600">{status.cloud_change_feed?.message || '—'}</p>
                    </section>
                </div>
            )}
        </div>
    );
}

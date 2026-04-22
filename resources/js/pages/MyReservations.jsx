import { useState, useEffect, useRef } from 'react';
import api from '../api';
import { paginatorRows, unwrapData } from '../utils/apiEnvelope';
import { getReservationActionLabel, getReservationStatusLabel } from '../utils/reservationVocabulary';
import { formatLogTime, formatReservationRange } from '../utils/timeDisplay';
import {
    bookingKindFromSpace,
    buildStartEndPayloadFromWallClock,
    initialWallClockFieldsFromReservation,
    validateHalfHourTimesForKind,
    wallClockFieldsFromInstants,
} from '../utils/reservationBookingTimes';
import { BOOKING_TIMEZONE } from '../utils/timeDisplay';
import { ui } from '../theme';
import HalfHourWallClockSelect from '../components/booking/HalfHourWallClockSelect';

function canEditReservation(r) {
    if (!r) return false;
    if (!(r.status === 'pending_approval' || r.status === 'approved')) return false;
    if (!r.end_at) return false;
    return new Date(r.end_at).getTime() > Date.now();
}

function EditReservationModal({ open, onClose, reservation, onSaved }) {
    const [spaces, setSpaces] = useState([]);
    const [spaceId, setSpaceId] = useState('');
    const [wc, setWc] = useState(() => ({ kind: 'standard', date: '', startTime: '09:00', endTime: '10:00' }));
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const prevSpaceIdRef = useRef('');

    useEffect(() => {
        if (!open) return;
        api.get('/spaces')
            .then(({ data }) => {
                const list = unwrapData(data);
                const raw = Array.isArray(list) ? list : [];
                setSpaces(raw.filter((s) => !(s.type === 'confab' && !s.is_confab_pool)));
            })
            .catch(() => setSpaces([]));
    }, [open]);

    useEffect(() => {
        if (!open || !reservation) return;
        setError('');
        const sid = String(reservation.space_id ?? reservation.space?.id ?? '');
        setSpaceId(sid);
        setWc(initialWallClockFieldsFromReservation(reservation));
        prevSpaceIdRef.current = sid;
    }, [open, reservation]);

    useEffect(() => {
        if (!open || !reservation || !spaceId) return;
        if (prevSpaceIdRef.current === spaceId) return;
        const sp = spaces.find((s) => String(s.id) === String(spaceId));
        if (!sp) return;
        prevSpaceIdRef.current = spaceId;
        const k = bookingKindFromSpace(sp);
        setWc(wallClockFieldsFromInstants(k, reservation.start_at, reservation.end_at));
    }, [open, reservation, spaceId, spaces]);

    const selectedSpace = spaces.find((s) => String(s.id) === String(spaceId));

    const onSave = async () => {
        if (!reservation) return;
        setError('');
        if (!spaceId) {
            setError('Select a library space.');
            return;
        }

        const timeErr = validateHalfHourTimesForKind(wc.kind, wc);
        if (timeErr) {
            setError(
                wc.kind === 'half_hour_details'
                    ? 'For this space, times must be on the half-hour (:00 or :30).'
                    : timeErr,
            );
            return;
        }

        setSaving(true);
        const { start_at, end_at } = buildStartEndPayloadFromWallClock(wc.kind, wc);
        try {
            const { data } = await api.patch(`/reservations/${reservation.id}`, {
                space_id: Number(spaceId),
                start_at,
                end_at,
            });
            const updated = unwrapData(data);
            onSaved(updated);
        } catch (err) {
            const d = err.response?.data;
            setError(
                d?.message
                    || d?.errors?.slot?.[0]
                    || d?.errors?.start_at?.[0]
                    || d?.errors?.end_at?.[0]
                    || 'Failed to update reservation.',
            );
        } finally {
            setSaving(false);
        }
    };

    if (!open) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-2 pb-[max(0.5rem,env(safe-area-inset-bottom,0px))] pt-3 sm:items-center sm:p-4 sm:pb-4 sm:pt-4"
            role="dialog"
            aria-modal="true"
        >
            <div className="max-h-[min(90dvh,90vh)] w-full min-w-0 max-w-[min(36rem,calc(100vw-1rem))] overflow-y-auto overflow-x-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/10 sm:max-w-xl">
                <div className="border-b border-slate-200/80 bg-slate-50 px-4 py-3 sm:px-5 sm:py-4">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Edit reservation</p>
                            <p className="mt-0.5 font-serif text-lg font-semibold text-xu-primary">
                                {selectedSpace?.name || reservation?.space?.name || 'Reservation'}
                            </p>
                            <p className="text-sm text-slate-600">Philippines civil time ({BOOKING_TIMEZONE} / PHT)</p>
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

                <div className="space-y-4 p-4 sm:p-5">
                    {error && <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg">{error}</div>}

                    <label className="block">
                        <span className="block text-sm font-medium text-slate-700 mb-1">Library space</span>
                        <select
                            value={spaceId}
                            onChange={(e) => setSpaceId(e.target.value)}
                            className={`w-full ${ui.select}`}
                        >
                            <option value="">Select a room…</option>
                            {spaces.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                    </label>

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Date *</label>
                        <input
                            type="date"
                            value={wc.kind === 'avr_range' ? wc.rangeStartDate : wc.date}
                            onChange={(e) => {
                                const v = e.target.value;
                                setWc((p) => (p.kind === 'avr_range' ? { ...p, rangeStartDate: v } : { ...p, date: v }));
                            }}
                            className={ui.input}
                        />
                    </div>

                    {wc.kind === 'avr_range' ? (
                        <>
                            <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">Start time *</label>
                                    <HalfHourWallClockSelect
                                        idPrefix="edit-range-start"
                                        value={wc.rangeStartTime}
                                        onChange={(v) => setWc((p) => ({ ...p, rangeStartTime: v }))}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">End date *</label>
                                    <input
                                        type="date"
                                        value={wc.rangeEndDate}
                                        onChange={(e) => setWc((p) => ({ ...p, rangeEndDate: e.target.value }))}
                                        className={ui.input}
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">End time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="edit-range-end"
                                    value={wc.rangeEndTime}
                                    onChange={(v) => setWc((p) => ({ ...p, rangeEndTime: v }))}
                                />
                            </div>
                        </>
                    ) : wc.kind === 'half_hour_details' ? (
                        <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Start time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="edit-details-start"
                                    value={wc.rangeStartTime}
                                    onChange={(v) => setWc((p) => ({ ...p, rangeStartTime: v }))}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">End time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="edit-details-end"
                                    value={wc.rangeEndTime}
                                    onChange={(v) => setWc((p) => ({ ...p, rangeEndTime: v }))}
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Start time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="edit-standard-start"
                                    value={wc.startTime}
                                    onChange={(v) => setWc((p) => ({ ...p, startTime: v }))}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">End time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="edit-standard-end"
                                    value={wc.endTime}
                                    onChange={(v) => setWc((p) => ({ ...p, endTime: v }))}
                                />
                            </div>
                        </div>
                    )}

                    <p className="text-xs text-slate-500">
                        Times use half-hour boundaries (<span className="font-medium text-slate-700">:00</span> and{' '}
                        <span className="font-medium text-slate-700">:30</span>), matching new reservations.
                    </p>

                    <button
                        type="button"
                        onClick={onSave}
                        disabled={saving}
                        className={ui.btnPrimaryFull}
                    >
                        {saving ? 'Saving…' : 'Save changes'}
                    </button>
                    <p className="text-xs text-slate-500">
                        After saving, this reservation returns to <span className="font-medium text-slate-700">Pending approval</span> for admin review.
                    </p>
                </div>
            </div>
        </div>
    );
}

export default function MyReservations() {
    const [reservations, setReservations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState(null);

    useEffect(() => {
        api.get('/reservations').then(({ data }) => setReservations(paginatorRows(data))).finally(() => setLoading(false));
    }, []);

    if (loading) return <p className="text-slate-600">Loading…</p>;

    return (
        <div className="min-w-0">
            <h1 className={`${ui.pageTitle} mb-4`}>My reservations</h1>
            <div className="space-y-3">
                {(reservations.length === 0) ? (
                    <p className="text-slate-600">No reservations yet.</p>
                ) : (
                    reservations.map((r) => (
                        <div
                            key={r.id}
                            className={`flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between ${ui.cardFlat}`}
                        >
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-xu-primary">{r.space?.name}</p>
                                <p className="text-sm text-slate-600">{formatReservationRange(r.start_at, r.end_at)}</p>
                                <p className="text-sm text-slate-500">{getReservationStatusLabel(r.status)} {r.reservation_number && `• ${r.reservation_number}`}</p>
                                {r.logs?.length > 0 && (
                                    <div className="mt-2 border-t border-slate-100 pt-2">
                                        <p className="text-xs font-semibold text-slate-700 mb-1">History</p>
                                        <ul className="space-y-1">
                                            {r.logs.map((log) => (
                                                <li key={log.id} className="text-xs text-slate-600">
                                                    <span className="font-medium text-slate-700">{getReservationActionLabel(log.action)}</span>
                                                    {' • '}
                                                    {formatLogTime(log.created_at)}
                                                    {' • '}
                                                    {log.actor?.name || log.admin?.name || 'System'}
                                                    {log.notes ? ` • ${log.notes}` : ''}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </div>
                            {canEditReservation(r) && (
                                <div className="shrink-0 pl-3">
                                    <button
                                        type="button"
                                        onClick={() => setEditing(r)}
                                        className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-xu-primary hover:border-xu-secondary/40 hover:bg-xu-page/40"
                                    >
                                        Edit
                                    </button>
                                </div>
                            )}
                        </div>
                    ))
                )}
            </div>
            <EditReservationModal
                open={Boolean(editing)}
                reservation={editing}
                onClose={() => setEditing(null)}
                onSaved={(updated) => {
                    setReservations((prev) => prev.map((x) => (String(x.id) === String(updated.id) ? updated : x)));
                    setEditing(null);
                    alert('Reservation updated. It is now pending admin approval.');
                }}
            />
        </div>
    );
}

import { useState, useEffect, useRef, useMemo } from 'react';
import api from '../api';
import { paginatorRows, unwrapData } from '../utils/apiEnvelope';
import { getEventRequestTypeLabel, getReservationActionLabel, getReservationStatusLabel } from '../utils/reservationVocabulary';
import { formatDisplayDate, formatDisplayTime, formatLogTime } from '../utils/timeDisplay';
import {
    bookingKindFromSpace,
    buildStartEndPayloadFromWallClock,
    initialWallClockFieldsFromReservation,
    validateWallClockWindowForKind,
    wallClockFieldsFromInstants,
} from '../utils/reservationBookingTimes';
import {
    allowedEndHhmmList,
    allowedEndHhmmListAvrRange,
    allowedStartHhmmList,
    allowedStartHhmmListBeforeEnd,
    normalizeOperatingHoursPayload,
    operatingHoursWallClockError,
    resolveOperatingWindowForYmd,
} from '../utils/operatingHours';
import { BOOKING_TIMEZONE } from '../utils/timeDisplay';
import { ui } from '../theme';
import HalfHourWallClockSelect from '../components/booking/HalfHourWallClockSelect';

function extractFloorFromGuidelineDetails(space) {
    const d = space?.guideline_details && typeof space.guideline_details === 'object' ? space.guideline_details : {};
    const raw = d.location != null ? String(d.location).trim() : '';
    if (!raw) return '';
    const m = raw.match(/\b(ground|[0-9]+(?:st|nd|rd|th)?)\s*floor\b/i);
    if (m) {
        const w = String(m[1] || '').trim();
        if (!w) return '';
        const normalized = /^\d+$/.test(w) ? `${w}th` : w;
        return `${normalized.charAt(0).toUpperCase()}${normalized.slice(1).toLowerCase()} Floor`;
    }
    const any = raw.match(/\b[^.]{0,40}\bfloor\b[^.]{0,40}\b/i);
    return any ? String(any[0]).trim() : '';
}

function canEditReservation(r) {
    if (!r) return false;
    // Match cancel-eligible active statuses: user may adjust details before/after email confirmation.
    if (!['email_verification_pending', 'pending_dean_approval', 'pending_approval', 'approved'].includes(r.status)) return false;
    if (!r.end_at) return false;
    return new Date(r.end_at).getTime() > Date.now();
}

function canCancelReservation(r) {
    if (!r) return false;
    if (r.status === 'cancelled' || r.status === 'rejected') return false;
    if (!r.end_at) return false;
    if (new Date(r.end_at).getTime() <= Date.now()) return false;
    if (!['email_verification_pending', 'pending_dean_approval', 'pending_approval', 'approved'].includes(r.status)) return false;
    return true;
}

function EditReservationModal({ open, onClose, reservation, onSaved }) {
    const [spaces, setSpaces] = useState([]);
    const [spaceId, setSpaceId] = useState('');
    const [wc, setWc] = useState(() => ({ kind: 'standard', date: '', startTime: '09:00', endTime: '10:00' }));
    const [eventRequestType, setEventRequestType] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [operatingHoursConfig, setOperatingHoursConfig] = useState(() => normalizeOperatingHoursPayload(null));
    const prevSpaceIdRef = useRef('');

    useEffect(() => {
        if (!open) return;
        api.get('/policies/operating-hours')
            .then(({ data }) => {
                const payload = unwrapData(data);
                setOperatingHoursConfig(normalizeOperatingHoursPayload(payload?.hours));
            })
            .catch(() => setOperatingHoursConfig(normalizeOperatingHoursPayload(null)));
    }, [open]);

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
        setEventRequestType(reservation.event_request_type || '');
    }, [open, reservation]);

    useEffect(() => {
        if (!open || !spaceId || spaces.length === 0) return;
        const sp = spaces.find((s) => String(s.id) === String(spaceId));
        if (!sp) return;
        if (!(sp.type === 'avr' || sp.type === 'lobby')) {
            setEventRequestType('');
        }
    }, [open, spaceId, spaces]);

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
    const needsEventAudience = Boolean(selectedSpace && (selectedSpace.type === 'avr' || selectedSpace.type === 'lobby'));

    const standardDayWindow = useMemo(
        () => resolveOperatingWindowForYmd(operatingHoursConfig, wc.date || ''),
        [operatingHoursConfig, wc.date],
    );
    const standardStartAllowed = useMemo(
        () => allowedStartHhmmList(standardDayWindow.start, standardDayWindow.end),
        [standardDayWindow],
    );
    const standardEndAllowed = useMemo(
        () => allowedEndHhmmList(standardDayWindow.start, standardDayWindow.end, wc.startTime || '09:00'),
        [standardDayWindow, wc.startTime],
    );

    const detailDayWindow = useMemo(
        () => resolveOperatingWindowForYmd(operatingHoursConfig, wc.date || ''),
        [operatingHoursConfig, wc.date],
    );
    const detailStartAllowed = useMemo(
        () => allowedStartHhmmListBeforeEnd(detailDayWindow.start, detailDayWindow.end, wc.rangeEndTime || '10:00'),
        [detailDayWindow, wc.rangeEndTime],
    );
    const detailEndAllowed = useMemo(
        () => allowedEndHhmmList(detailDayWindow.start, detailDayWindow.end, wc.rangeStartTime || '09:00'),
        [detailDayWindow, wc.rangeStartTime],
    );

    const avrStartWindow = useMemo(
        () => resolveOperatingWindowForYmd(operatingHoursConfig, wc.rangeStartDate || ''),
        [operatingHoursConfig, wc.rangeStartDate],
    );
    const avrEndWindow = useMemo(
        () => resolveOperatingWindowForYmd(operatingHoursConfig, wc.rangeEndDate || ''),
        [operatingHoursConfig, wc.rangeEndDate],
    );
    const avrStartAllowed = useMemo(() => {
        if (!wc.rangeStartDate || !wc.rangeEndDate) return allowedStartHhmmList(avrStartWindow.start, avrStartWindow.end);
        if (wc.rangeStartDate === wc.rangeEndDate) {
            return allowedStartHhmmListBeforeEnd(avrStartWindow.start, avrStartWindow.end, wc.rangeEndTime || '10:00');
        }
        return allowedStartHhmmList(avrStartWindow.start, avrStartWindow.end);
    }, [wc.rangeStartDate, wc.rangeEndDate, wc.rangeEndTime, avrStartWindow]);
    const avrEndAllowed = useMemo(() => {
        if (!wc.rangeStartDate || !wc.rangeEndDate) {
            return allowedEndHhmmList(avrEndWindow.start, avrEndWindow.end, wc.rangeStartTime || '09:00');
        }
        if (wc.rangeStartDate === wc.rangeEndDate) {
            return allowedEndHhmmList(avrStartWindow.start, avrStartWindow.end, wc.rangeStartTime || '09:00');
        }
        return allowedEndHhmmListAvrRange(
            wc.rangeStartDate,
            wc.rangeStartTime || '09:00',
            wc.rangeEndDate,
            avrEndWindow,
            buildStartEndPayloadFromWallClock,
        );
    }, [wc.rangeStartDate, wc.rangeEndDate, wc.rangeStartTime, avrStartWindow, avrEndWindow]);

    const operatingHoursError = useMemo(() => {
        const basic = validateWallClockWindowForKind(wc.kind, wc);
        if (basic) return '';
        return operatingHoursWallClockError(wc.kind, wc, operatingHoursConfig, buildStartEndPayloadFromWallClock);
    }, [wc, operatingHoursConfig]);

    const onSave = async () => {
        if (!reservation) return;
        setError('');
        if (!spaceId) {
            setError('Select a library space.');
            return;
        }
        if (needsEventAudience && !eventRequestType) {
            setError('Select whether this reservation is an organization event or an employee event.');
            return;
        }

        const timeErr = validateWallClockWindowForKind(wc.kind, wc);
        if (timeErr) {
            const halfMsg = 'Times must use half-hour boundaries only (:00 or :30).';
            setError(
                timeErr === halfMsg && wc.kind === 'half_hour_details'
                    ? 'For this space, times must be on the half-hour (:00 or :30).'
                    : timeErr,
            );
            return;
        }

        if (operatingHoursError) {
            setError(operatingHoursError);
            return;
        }

        setSaving(true);
        const { start_at, end_at } = buildStartEndPayloadFromWallClock(wc.kind, wc);
        try {
            const { data: body } = await api.patch(`/reservations/${reservation.id}`, {
                space_id: Number(spaceId),
                start_at,
                end_at,
                ...(needsEventAudience ? { event_request_type: eventRequestType } : {}),
            });
            const updated = unwrapData(body);
            const msg = typeof body?.message === 'string' ? body.message : 'Reservation updated.';
            alert(msg);
            onSaved(updated);
        } catch (err) {
            const d = err.response?.data;
            setError(
                d?.message
                    || d?.errors?.slot?.[0]
                    || d?.errors?.start_at?.[0]
                    || d?.errors?.end_at?.[0]
                    || d?.errors?.event_request_type?.[0]
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
                    {wc.kind === 'avr_range' && wc.rangeStartDate && wc.rangeEndDate && (
                        <p className="mt-1 text-xs text-slate-600">
                            Hours: {resolveOperatingWindowForYmd(operatingHoursConfig, wc.rangeStartDate).start}–
                            {resolveOperatingWindowForYmd(operatingHoursConfig, wc.rangeStartDate).end} (start date) ·{' '}
                            {resolveOperatingWindowForYmd(operatingHoursConfig, wc.rangeEndDate).start}–
                            {resolveOperatingWindowForYmd(operatingHoursConfig, wc.rangeEndDate).end} (end date) PHT
                        </p>
                    )}
                    {wc.kind !== 'avr_range' && wc.date && (
                        <p className="mt-1 text-xs text-slate-600">
                            Hours this day: {resolveOperatingWindowForYmd(operatingHoursConfig, wc.date).start}–
                            {resolveOperatingWindowForYmd(operatingHoursConfig, wc.date).end} PHT
                        </p>
                    )}
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

                    {needsEventAudience && (
                        <label className="block">
                            <span className="block text-sm font-medium text-slate-700 mb-1">Event audience *</span>
                            <select
                                value={eventRequestType}
                                onChange={(e) => setEventRequestType(e.target.value)}
                                className={`w-full ${ui.select}`}
                            >
                                <option value="">Select one…</option>
                                <option value="organization">Organization event</option>
                                <option value="employee">Employee event</option>
                            </select>
                        </label>
                    )}

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
                                        allowedHhmmList={avrStartAllowed}
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
                                    allowedHhmmList={avrEndAllowed}
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
                                    allowedHhmmList={detailStartAllowed}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">End time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="edit-details-end"
                                    value={wc.rangeEndTime}
                                    onChange={(v) => setWc((p) => ({ ...p, rangeEndTime: v }))}
                                    allowedHhmmList={detailEndAllowed}
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
                                    allowedHhmmList={standardStartAllowed}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">End time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="edit-standard-end"
                                    value={wc.endTime}
                                    onChange={(v) => setWc((p) => ({ ...p, endTime: v }))}
                                    allowedHhmmList={standardEndAllowed}
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
                        disabled={saving || Boolean(operatingHoursError)}
                        className={ui.btnPrimaryFull}
                    >
                        {saving ? 'Saving…' : 'Save changes'}
                    </button>
                    <p className="text-xs text-slate-500">
                        {reservation?.status === 'email_verification_pending' ? (
                            <>
                                After saving, confirm your request using the link sent to your XU email if you have not already. You can still adjust details while awaiting confirmation.
                            </>
                        ) : (
                            <>
                                After saving, this reservation returns to <span className="font-medium text-slate-700">Pending approval</span> for admin review.
                            </>
                        )}
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
    const [cancellingId, setCancellingId] = useState(null);

    useEffect(() => {
        api.get('/reservations').then(({ data }) => setReservations(paginatorRows(data))).finally(() => setLoading(false));
    }, []);

    const requestCancel = async (r) => {
        if (!r || !canCancelReservation(r)) return;
        if (!window.confirm('Cancel this reservation? This cannot be undone.')) return;
        setCancellingId(r.id);
        try {
            const { data } = await api.post(`/reservations/${r.id}/cancel`);
            const updated = unwrapData(data);
            setReservations((prev) => prev.map((x) => (String(x.id) === String(updated.id) ? updated : x)));
        } catch (err) {
            const msg = err.response?.data?.message || 'Failed to cancel reservation.';
            window.alert(msg);
        } finally {
            setCancellingId(null);
        }
    };

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
                                <p className="font-medium text-xu-primary">
                                    {r.space?.name ?? '—'}
                                    {extractFloorFromGuidelineDetails(r.space) ? (
                                        <span className="text-slate-600 font-normal">
                                            {' '}
                                            · {extractFloorFromGuidelineDetails(r.space)}
                                        </span>
                                    ) : null}
                                </p>
                                <p className="text-sm text-slate-600">
                                    {formatDisplayDate(r.start_at)}
                                    {formatDisplayDate(r.start_at) !== formatDisplayDate(r.end_at)
                                        ? ` – ${formatDisplayDate(r.end_at)}`
                                        : ''}
                                </p>
                                <p className="text-sm text-slate-600">
                                    {formatDisplayTime(r.start_at)} – {formatDisplayTime(r.end_at)}
                                </p>
                                <div className="mt-2 grid grid-cols-1 gap-1.5 text-sm text-slate-700 sm:grid-cols-2">
                                    <p className="text-slate-500">
                                        <span className="font-medium text-slate-700">Status:</span>{' '}
                                        {getReservationStatusLabel(r.status)}
                                        {r.reservation_number ? ` • ${r.reservation_number}` : ''}
                                    </p>
                                    {r.participant_count != null && String(r.participant_count).trim() !== '' && (
                                        <p className="text-slate-500">
                                            <span className="font-medium text-slate-700">Attendees:</span>{' '}
                                            {r.participant_count}
                                        </p>
                                    )}
                                    {(r.event_title || '').trim() !== '' && (
                                        <p className="sm:col-span-2">
                                            <span className="font-medium text-slate-700">Title:</span>{' '}
                                            {r.event_title}
                                        </p>
                                    )}
                                    {((r.event_description || r.purpose || '').trim() !== '') && (
                                        <p className="sm:col-span-2">
                                            <span className="font-medium text-slate-700">Description:</span>{' '}
                                            <span className="whitespace-pre-wrap">
                                                {(r.event_description || r.purpose || '').trim()}
                                            </span>
                                        </p>
                                    )}
                                </div>
                                {r.event_request_type ? (
                                    <p className="text-sm text-slate-600 mt-0.5">
                                        <span className="font-medium text-slate-700">Event audience:</span>{' '}
                                        {getEventRequestTypeLabel(r.event_request_type)}
                                    </p>
                                ) : null}
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
                            {(canEditReservation(r) || canCancelReservation(r)) && (
                                <div className="flex shrink-0 flex-row flex-wrap items-center gap-2 pl-3">
                                    {canEditReservation(r) && (
                                        <button
                                            type="button"
                                            onClick={() => setEditing(r)}
                                            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-xu-primary hover:border-xu-secondary/40 hover:bg-xu-page/40"
                                        >
                                            Edit
                                        </button>
                                    )}
                                    {canCancelReservation(r) && (
                                        <button
                                            type="button"
                                            onClick={() => requestCancel(r)}
                                            disabled={cancellingId != null && String(cancellingId) === String(r.id)}
                                            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 hover:border-red-200 hover:bg-red-50 disabled:opacity-60"
                                        >
                                            {cancellingId != null && String(cancellingId) === String(r.id) ? 'Cancelling…' : 'Cancel'}
                                        </button>
                                    )}
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
                }}
            />
        </div>
    );
}

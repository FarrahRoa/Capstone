import { Fragment, useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { getSpaceIneligibilityMessage, getSpaceRestrictionLabel, isUserEligibleForSpace } from '../utils/spaceEligibility';
import { BOOKING_TIMEZONE } from '../utils/timeDisplay';
import { manilaYmdFromInstant } from '../utils/manilaTime';
import {
    bookingKindFromSpace,
    buildStartEndPayloadFromWallClock,
    halfHourHhmmFromOptionalQueryParam,
    validateHalfHourTimesForKind,
} from '../utils/reservationBookingTimes';
import { unwrapData } from '../utils/apiEnvelope';
import { ui } from '../theme';
import HalfHourWallClockSelect from '../components/booking/HalfHourWallClockSelect';
import { spaceGuidelinesDetailRows, spaceGuidelinesHasDetails } from '../utils/spaceGuidelineDisplay';

function initialDateFromParams(dateParam) {
    if (dateParam && /^\d{4}-\d{2}-\d{2}$/.test(dateParam)) {
        return dateParam;
    }
    return manilaYmdFromInstant(new Date());
}

export default function ReservationForm() {
    const { user } = useAuth();
    const [searchParams] = useSearchParams();
    const spaceId = searchParams.get('space_id');
    const dateParam = searchParams.get('date');
    const startTimeParam = searchParams.get('start_time');
    const endTimeParam = searchParams.get('end_time');
    const [spaces, setSpaces] = useState([]);
    const [spaceIdVal, setSpaceIdVal] = useState(spaceId || '');
    const [date, setDate] = useState(() => initialDateFromParams(dateParam));
    const [startTime, setStartTime] = useState('09:00');
    const [endTime, setEndTime] = useState('10:00');
    const [rangeStartDate, setRangeStartDate] = useState(() => initialDateFromParams(dateParam));
    const [rangeStartTime, setRangeStartTime] = useState('09:00');
    const [rangeEndDate, setRangeEndDate] = useState(() => initialDateFromParams(dateParam));
    const [rangeEndTime, setRangeEndTime] = useState('09:30');
    const [purpose, setPurpose] = useState('');
    const [eventTitle, setEventTitle] = useState('');
    const [eventDescription, setEventDescription] = useState('');
    const [participantCount, setParticipantCount] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [guidelines, setGuidelines] = useState('');
    const [confabGuidelines, setConfabGuidelines] = useState('');
    const [confabRoomComparisons, setConfabRoomComparisons] = useState([]);
    const navigate = useNavigate();
    const selectedSpace = spaces.find((s) => String(s.id) === String(spaceIdVal));
    const bookingKind = bookingKindFromSpace(selectedSpace);
    const requiresEventMeta = bookingKind === 'avr_range' || bookingKind === 'half_hour_details';

    const selectedRestriction = getSpaceRestrictionLabel(selectedSpace);
    const isSelectedSpaceEligible = isUserEligibleForSpace(user, selectedSpace);
    const selectedSpaceBlockMessage = !isSelectedSpaceEligible ? getSpaceIneligibilityMessage(selectedSpace) : '';

    const isConfabPool = Boolean(selectedSpace?.is_confab_pool);

    useEffect(() => {
        api.get('/spaces').then(({ data }) => {
            const list = unwrapData(data);
            const raw = Array.isArray(list) ? list : [];
            setSpaces(raw.filter((s) => !(s.type === 'confab' && !s.is_confab_pool)));
            if (spaceId && !spaceIdVal) setSpaceIdVal(spaceId);
        });
    }, [spaceId]);

    useEffect(() => {
        if (!spaceIdVal || spaces.length === 0) return;
        const found = spaces.some((s) => String(s.id) === String(spaceIdVal));
        if (!found) setSpaceIdVal('');
    }, [spaces, spaceIdVal]);

    useEffect(() => {
        if (dateParam && /^\d{4}-\d{2}-\d{2}$/.test(dateParam)) {
            setDate(dateParam);
            setRangeStartDate(dateParam);
            setRangeEndDate(dateParam);
        }
    }, [dateParam]);

    useEffect(() => {
        const startQ = halfHourHhmmFromOptionalQueryParam(startTimeParam);
        if (startQ) {
            setStartTime(startQ);
            setRangeStartTime(startQ);
        }
        const endQ = halfHourHhmmFromOptionalQueryParam(endTimeParam);
        if (endQ) {
            setEndTime(endQ);
            setRangeEndTime(endQ);
        }
    }, [startTimeParam, endTimeParam]);

    useEffect(() => {
        api.get('/reservation-guidelines')
            .then(({ data }) => {
                const doc = unwrapData(data);
                setGuidelines((doc && doc.content) || '');
                setConfabGuidelines((doc && doc.confab_guidelines_content) || '');
                const rooms = doc && Array.isArray(doc.confab_room_comparisons) ? doc.confab_room_comparisons : [];
                setConfabRoomComparisons(rooms);
            })
            .catch(() => {
                setGuidelines('');
                setConfabGuidelines('');
                setConfabRoomComparisons([]);
            });
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        if (selectedSpace && !isSelectedSpaceEligible) {
            setError(selectedSpaceBlockMessage);
            return;
        }

        let wallFields;
        if (bookingKind === 'avr_range') {
            wallFields = {
                rangeStartDate,
                rangeStartTime,
                rangeEndDate,
                rangeEndTime,
            };
        } else if (bookingKind === 'half_hour_details') {
            wallFields = { date, rangeStartTime, rangeEndTime };
        } else {
            wallFields = { date, startTime, endTime };
        }

        const timeErr = validateHalfHourTimesForKind(bookingKind, wallFields);
        if (timeErr) {
            setError(
                bookingKind === 'half_hour_details'
                    ? 'For this space, times must be on the half-hour (:00 or :30).'
                    : timeErr,
            );
            return;
        }

        if (requiresEventMeta) {
            if (!eventTitle.trim()) {
                setError('Reservation title is required for this space.');
                return;
            }
            const pc = Number(participantCount);
            if (!pc || pc < 1) {
                setError('Participant count is required for this space.');
                return;
            }
        }

        setLoading(true);
        const { start_at, end_at } = buildStartEndPayloadFromWallClock(bookingKind, wallFields);
        try {
            await api.post('/reservations', {
                space_id: Number(spaceIdVal),
                start_at,
                end_at,
                purpose,
                event_title: requiresEventMeta ? eventTitle : undefined,
                event_description: requiresEventMeta ? eventDescription : undefined,
                participant_count: requiresEventMeta ? Number(participantCount) : undefined,
            });
            navigate('/my-reservations');
            alert('Reservation created. Please confirm via the link sent to your XU email.');
        } catch (err) {
            const d = err.response?.data;
            setError(
                d?.message
                    || d?.errors?.space_id?.[0]
                    || d?.errors?.start_at?.[0]
                    || d?.errors?.end_at?.[0]
                    || d?.errors?.event_title?.[0]
                    || d?.errors?.participant_count?.[0]
                    || d?.errors?.slot?.[0]
                    || d?.errors?.reservation?.[0]
                    || 'Failed to create reservation.'
            );
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-w-0 w-full max-w-xl pb-2 sm:pb-0">
            <h1 className={`${ui.pageTitle} mb-4`}>New reservation</h1>
            {(guidelines.trim() !== '' ||
                (selectedSpace && !isConfabPool && spaceGuidelinesHasDetails(selectedSpace)) ||
                isConfabPool) && (
                <div className="mb-4 space-y-3">
                    {guidelines.trim() !== '' && (
                        <details className="bg-white border border-slate-200/90 rounded-lg p-4 text-sm text-slate-700 shadow-sm border-l-4 border-l-xu-gold/60">
                            <summary className="cursor-pointer font-medium text-xu-primary">General guidelines</summary>
                            <div className="mt-2 whitespace-pre-wrap">{guidelines}</div>
                        </details>
                    )}
                    {isConfabPool && (
                        <>
                            {confabGuidelines.trim() !== '' && (
                                <details className="bg-white border border-slate-200/90 rounded-lg p-4 text-sm text-slate-700 shadow-sm border-l-4 border-l-xu-secondary/50">
                                    <summary className="cursor-pointer font-medium text-xu-primary">Confab guidelines</summary>
                                    <div className="mt-2 whitespace-pre-wrap">{confabGuidelines}</div>
                                </details>
                            )}
                            <div
                                className="rounded-lg border border-xu-secondary/30 bg-xu-primary/[0.06] p-4 text-sm text-xu-primary shadow-sm"
                                role="status"
                            >
                                <p className="font-semibold">How Confab assignment works</p>
                                <p className="mt-2 leading-relaxed text-slate-800">
                                    You are requesting the shared <span className="font-medium">Confab</span> slot, not a
                                    specific numbered room yet.{' '}
                                    <span className="font-medium text-xu-primary">
                                        The final Confab room (Confab 1, Confab 2, etc.) is assigned by library staff
                                        when they approve your request
                                    </span>
                                    , based on availability and suitability.
                                </p>
                            </div>
                            {confabRoomComparisons.length > 0 && (
                                <details
                                    open
                                    className="bg-white border border-slate-200/90 rounded-lg p-4 text-sm text-slate-700 shadow-sm border-l-4 border-l-xu-gold/55"
                                >
                                    <summary className="cursor-pointer font-medium text-xu-primary">
                                        Confab room details (compare numbered rooms)
                                    </summary>
                                    <p className="mt-2 text-xs leading-relaxed text-slate-600">
                                        Each row is a physical Confab room the library may assign after approval. This is
                                        for reference only — your booking remains a general Confab request until staff
                                        assigns a room.
                                    </p>
                                    <div className="mt-4 space-y-4">
                                        {confabRoomComparisons.map((room, roomIdx) => {
                                            const rows = spaceGuidelinesDetailRows({
                                                capacity: room.capacity,
                                                guideline_details: room.guideline_details,
                                            });
                                            return (
                                                <div
                                                    key={`${roomIdx}-${room.name}`}
                                                    className="rounded-lg border border-slate-200/90 bg-slate-50/60 p-4"
                                                >
                                                    <h3 className="font-serif text-base font-semibold text-xu-primary">
                                                        {room.name}
                                                    </h3>
                                                    {rows.length === 0 ? (
                                                        <p className="mt-2 text-xs text-slate-600">
                                                            No facility details on file for this room yet. Ask the
                                                            library if you need specifics.
                                                        </p>
                                                    ) : (
                                                        <dl className="mt-3 grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-[minmax(0,10rem)_1fr]">
                                                            {rows.map(({ label, value }, idx) => (
                                                                <Fragment key={`${room.name}-${label}-${idx}`}>
                                                                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                                        {label}
                                                                    </dt>
                                                                    <dd className="text-slate-800 whitespace-pre-wrap">
                                                                        {value}
                                                                    </dd>
                                                                </Fragment>
                                                            ))}
                                                        </dl>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </details>
                            )}
                        </>
                    )}
                    {selectedSpace && !isConfabPool && spaceGuidelinesHasDetails(selectedSpace) && (
                        <details className="bg-white border border-slate-200/90 rounded-lg p-4 text-sm text-slate-700 shadow-sm border-l-4 border-l-xu-secondary/50">
                            <summary className="cursor-pointer font-medium text-xu-primary">
                                {selectedSpace.name} — room details
                            </summary>
                            <dl className="mt-3 grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-[minmax(0,10rem)_1fr]">
                                {spaceGuidelinesDetailRows(selectedSpace).map(({ label, value }, idx) => (
                                    <Fragment key={`${label}-${idx}`}>
                                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            {label}
                                        </dt>
                                        <dd className="text-slate-800 whitespace-pre-wrap">{value}</dd>
                                    </Fragment>
                                ))}
                            </dl>
                        </details>
                    )}
                </div>
            )}
            <form onSubmit={handleSubmit} className={`min-w-0 space-y-4 p-4 sm:p-6 ${ui.cardFlat}`}>
                {error && <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg">{error}</div>}
                <p className="text-xs text-slate-500 -mt-1 mb-1">
                    Date and times are in Philippines civil time ({BOOKING_TIMEZONE} / PHT), matching the server.
                </p>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Room *</label>
                    <select value={spaceIdVal} onChange={(e) => setSpaceIdVal(e.target.value)} required className={`w-full ${ui.select}`}>
                        <option value="">Select room</option>
                        {spaces.map((s) => {
                            const restriction = getSpaceRestrictionLabel(s);
                            const eligible = isUserEligibleForSpace(user, s);
                            return (
                                <option key={s.id} value={s.id} disabled={!eligible}>
                                    {restriction ? `${s.name} (${restriction})` : s.name}
                                </option>
                            );
                        })}
                    </select>
                    {selectedRestriction && (
                        <p className="mt-2 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 inline-block">
                            {selectedRestriction}
                        </p>
                    )}
                    {selectedSpace && !isSelectedSpaceEligible && (
                        <p className="mt-2 text-sm text-red-700">
                            {selectedSpaceBlockMessage}
                        </p>
                    )}
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Date *</label>
                    <input
                        type="date"
                        value={bookingKind === 'avr_range' ? rangeStartDate : date}
                        onChange={(e) => {
                            const v = e.target.value;
                            if (bookingKind === 'avr_range') setRangeStartDate(v);
                            else setDate(v);
                        }}
                        required
                        className={ui.input}
                    />
                </div>
                {bookingKind === 'avr_range' ? (
                    <>
                        <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Start time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="res-range-start"
                                    value={rangeStartTime}
                                    onChange={setRangeStartTime}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">End date *</label>
                                <input
                                    type="date"
                                    value={rangeEndDate}
                                    onChange={(e) => setRangeEndDate(e.target.value)}
                                    required
                                    className={ui.input}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">End time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="res-range-end"
                                    value={rangeEndTime}
                                    onChange={setRangeEndTime}
                                />
                            </div>
                            <div className="hidden min-[520px]:block" aria-hidden="true" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Reservation title *</label>
                            <input
                                type="text"
                                value={eventTitle}
                                onChange={(e) => setEventTitle(e.target.value)}
                                required
                                className={ui.input}
                                placeholder="Reservation title"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                Event description / justification / notes (optional)
                            </label>
                            <textarea
                                value={eventDescription}
                                onChange={(e) => setEventDescription(e.target.value)}
                                rows={4}
                                className={ui.input}
                                placeholder="Add details for approvers"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Number of participants *</label>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                value={participantCount}
                                onChange={(e) => setParticipantCount(e.target.value)}
                                required
                                className={ui.input}
                                placeholder="e.g. 50"
                            />
                        </div>
                    </>
                ) : bookingKind === 'half_hour_details' ? (
                    <>
                        <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Start time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="res-details-start"
                                    value={rangeStartTime}
                                    onChange={setRangeStartTime}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">End time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="res-details-end"
                                    value={rangeEndTime}
                                    onChange={setRangeEndTime}
                                />
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Reservation title *</label>
                            <input
                                type="text"
                                value={eventTitle}
                                onChange={(e) => setEventTitle(e.target.value)}
                                required
                                className={ui.input}
                                placeholder="Reservation title"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                Description / justification / notes (optional)
                            </label>
                            <textarea
                                value={eventDescription}
                                onChange={(e) => setEventDescription(e.target.value)}
                                rows={4}
                                className={ui.input}
                                placeholder="Add details for approvers"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Number of participants *</label>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                value={participantCount}
                                onChange={(e) => setParticipantCount(e.target.value)}
                                required
                                className={ui.input}
                                placeholder="e.g. 50"
                            />
                        </div>
                    </>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Start time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="res-standard-start"
                                    value={startTime}
                                    onChange={setStartTime}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">End time *</label>
                                <HalfHourWallClockSelect
                                    idPrefix="res-standard-end"
                                    value={endTime}
                                    onChange={setEndTime}
                                />
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Purpose (optional)</label>
                            <textarea value={purpose} onChange={(e) => setPurpose(e.target.value)} rows={3} className={ui.input} placeholder="Brief purpose of use" />
                        </div>
                    </>
                )}
                <button type="submit" disabled={loading || (selectedSpace && !isSelectedSpaceEligible)} className={ui.btnPrimaryFull}>
                    Submit reservation
                </button>
            </form>
        </div>
    );
}

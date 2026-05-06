import { Fragment, useState, useEffect, useMemo, useRef } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { getSpaceIneligibilityMessage, getSpaceRestrictionLabel, isUserEligibleForSpace } from '../utils/spaceEligibility';
import { BOOKING_TIMEZONE, formatReservationRange } from '../utils/timeDisplay';
import { manilaYmdFromInstant } from '../utils/manilaTime';
import {
    bookingKindFromSpace,
    buildStartEndPayloadFromWallClock,
    halfHourHhmmFromOptionalQueryParam,
    validateWallClockWindowForKind,
} from '../utils/reservationBookingTimes';
import { unwrapData } from '../utils/apiEnvelope';
import {
    allowedEndHhmmList,
    allowedEndHhmmListAvrRange,
    allowedStartHhmmList,
    allowedStartHhmmListBeforeEnd,
    normalizeOperatingHoursPayload,
    operatingHoursWallClockError,
    resolveOperatingWindowForYmd,
} from '../utils/operatingHours';
import { ui } from '../theme';
import HalfHourWallClockSelect from '../components/booking/HalfHourWallClockSelect';
import SpaceShowcaseCarousel from '../components/booking/SpaceShowcaseCarousel';
import { spaceGuidelinesDetailRows, spaceGuidelinesHasDetails } from '../utils/spaceGuidelineDisplay';

function holidayForYmd(holidays, ymd) {
    if (!ymd || !Array.isArray(holidays)) return null;
    const md = String(ymd).slice(5);
    return (
        holidays.find((h) => {
            const d = String(h?.date || '');
            if (!d) return false;
            const recurring = Boolean(h?.is_recurring);
            return recurring ? d.slice(5) === md : d === ymd;
        }) || null
    );
}

function initialDateFromParams(dateParam) {
    if (dateParam && /^\d{4}-\d{2}-\d{2}$/.test(dateParam)) {
        return dateParam;
    }
    return manilaYmdFromInstant(new Date());
}

/** Staff portal roles use the same /reserve page with visible time sublabels for accessibility audits. */
const STAFF_PORTAL_ROLE_SLUGS = new Set(['admin', 'librarian', 'student_assistant']);

export default function ReservationForm() {
    const { user } = useAuth();
    const visibleReservationTimeLabels = STAFF_PORTAL_ROLE_SLUGS.has((user?.role?.slug || '').toLowerCase());
    const [searchParams] = useSearchParams();
    const spaceId = searchParams.get('space_id');
    const dateParam = searchParams.get('date');
    const startTimeParam = searchParams.get('start_time');
    const endTimeParam = searchParams.get('end_time');
    const [spaces, setSpaces] = useState([]);
    /** Full active space list for the photo showcase (includes numbered Confab rooms). */
    const [showcaseSpaces, setShowcaseSpaces] = useState([]);
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
    const [eventRequestType, setEventRequestType] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [guidelines, setGuidelines] = useState('');
    const [confabGuidelines, setConfabGuidelines] = useState('');
    const [confabRoomComparisons, setConfabRoomComparisons] = useState([]);
    const [holidays, setHolidays] = useState([]);
    const [operatingHoursConfig, setOperatingHoursConfig] = useState(() => normalizeOperatingHoursPayload(null));
    const navigate = useNavigate();
    const selectedSpace = spaces.find((s) => String(s.id) === String(spaceIdVal));
    const bookingKind = bookingKindFromSpace(selectedSpace);
    const requiresEventMeta = bookingKind === 'avr_range' || bookingKind === 'half_hour_details';
    const needsEventAudience = Boolean(
        selectedSpace && (selectedSpace.type === 'avr' || selectedSpace.type === 'lobby'),
    );

    const selectedRestriction = getSpaceRestrictionLabel(selectedSpace);
    const isSelectedSpaceEligible = isUserEligibleForSpace(user, selectedSpace);
    const selectedSpaceBlockMessage = !isSelectedSpaceEligible ? getSpaceIneligibilityMessage(selectedSpace, user) : '';

    const isConfabPool = Boolean(selectedSpace?.is_confab_pool);

    /** From `spaces.capacity` via /spaces; confab assignment pool is excluded (server skips enforcement there). */
    const effectiveSeatingCapacity =
        selectedSpace &&
        !selectedSpace.is_confab_pool &&
        selectedSpace.capacity != null &&
        String(selectedSpace.capacity).trim() !== '' &&
        Number(selectedSpace.capacity) > 0
            ? Number(selectedSpace.capacity)
            : null;

    const participantCountNum = participantCount === '' ? NaN : Number(participantCount);
    const participantOverCapacity =
        requiresEventMeta &&
        effectiveSeatingCapacity != null &&
        participantCount !== '' &&
        Number.isFinite(participantCountNum) &&
        participantCountNum > effectiveSeatingCapacity;

    const capacityExceededMessage =
        effectiveSeatingCapacity != null
            ? `The number of attendees exceeds the seating capacity for this space (Max: ${effectiveSeatingCapacity} seats).`
            : 'The number of attendees exceeds the seating capacity for this space.';

    const reservationWindowPreview = useMemo(() => {
        if (!spaceIdVal || !selectedSpace) {
            return null;
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
        if (validateWallClockWindowForKind(bookingKind, wallFields)) {
            return null;
        }
        const { start_at, end_at } = buildStartEndPayloadFromWallClock(bookingKind, wallFields);
        return formatReservationRange(start_at, end_at);
    }, [
        spaceIdVal,
        selectedSpace,
        bookingKind,
        rangeStartDate,
        rangeStartTime,
        rangeEndDate,
        rangeEndTime,
        date,
        startTime,
        endTime,
    ]);

    const roleSlug = (user?.role?.slug || '').toLowerCase();
    const userType = (user?.user_type || '').toLowerCase();
    const maxBookingMinutes = roleSlug === 'student' || userType === 'student' ? 120 : 180;
    const maxBookingHours = maxBookingMinutes / 60;

    const durationLimitError = useMemo(() => {
        if (!spaceIdVal || !selectedSpace) {
            return '';
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
        const timeErr = validateWallClockWindowForKind(bookingKind, wallFields);
        if (timeErr) {
            return '';
        }
        const { start_at, end_at } = buildStartEndPayloadFromWallClock(bookingKind, wallFields);
        const mins = Math.round((new Date(end_at).getTime() - new Date(start_at).getTime()) / 60000);
        if (!Number.isFinite(mins) || mins <= 0) {
            return '';
        }
        if (mins > maxBookingMinutes) {
            return `You have exceeded your maximum booking limit of ${maxBookingHours} hours for your account type.`;
        }
        return '';
    }, [
        spaceIdVal,
        selectedSpace,
        bookingKind,
        rangeStartDate,
        rangeStartTime,
        rangeEndDate,
        rangeEndTime,
        date,
        startTime,
        endTime,
        maxBookingMinutes,
        maxBookingHours,
    ]);

    useEffect(() => {
        api.get('/spaces').then(({ data }) => {
            const list = unwrapData(data);
            const raw = Array.isArray(list) ? list : [];
            setShowcaseSpaces(raw);
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

    const wallSnapRef = useRef({});
    wallSnapRef.current = {
        startTime,
        endTime,
        rangeStartTime,
        rangeEndTime,
        date,
        rangeStartDate,
        rangeEndDate,
    };

    const prevBookingKindRef = useRef(null);
    useEffect(() => {
        const snap = wallSnapRef.current;
        const prev = prevBookingKindRef.current;
        if (prev !== null && prev !== bookingKind) {
            if (prev === 'standard') {
                if (bookingKind === 'half_hour_details' || bookingKind === 'avr_range') {
                    setRangeStartTime(snap.startTime);
                    setRangeEndTime(snap.endTime);
                }
                if (bookingKind === 'avr_range') {
                    setRangeStartDate(snap.date);
                    setRangeEndDate(snap.date);
                }
            } else if (prev === 'half_hour_details') {
                if (bookingKind === 'standard') {
                    setStartTime(snap.rangeStartTime);
                    setEndTime(snap.rangeEndTime);
                } else if (bookingKind === 'avr_range') {
                    setRangeStartDate(snap.date);
                    setRangeEndDate(snap.date);
                }
            } else if (prev === 'avr_range') {
                if (bookingKind === 'standard') {
                    setDate(snap.rangeStartDate);
                    setStartTime(snap.rangeStartTime);
                    setEndTime(snap.rangeEndTime);
                } else if (bookingKind === 'half_hour_details') {
                    setDate(snap.rangeStartDate);
                    setRangeStartTime(snap.rangeStartTime);
                    setRangeEndTime(snap.rangeEndTime);
                }
            }
        }
        prevBookingKindRef.current = bookingKind;
    }, [bookingKind]);

    useEffect(() => {
        if (!needsEventAudience) {
            setEventRequestType('');
        }
    }, [needsEventAudience, selectedSpace?.id]);

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

    useEffect(() => {
        api.get('/policies/operating-hours')
            .then(({ data }) => {
                const payload = unwrapData(data);
                setHolidays(Array.isArray(payload?.holidays) ? payload.holidays : []);
                setOperatingHoursConfig(normalizeOperatingHoursPayload(payload?.hours));
            })
            .catch(() => {
                setHolidays([]);
                setOperatingHoursConfig(normalizeOperatingHoursPayload(null));
            });
    }, []);

    const selectedYmdForHoliday = bookingKind === 'avr_range' ? rangeStartDate : date;
    const holidayHit = useMemo(() => holidayForYmd(holidays, selectedYmdForHoliday), [holidays, selectedYmdForHoliday]);
    const holidayBlockMessage = holidayHit ? `Reservations are closed for this date due to ${holidayHit.name}.` : '';

    const standardDayWindow = useMemo(
        () => resolveOperatingWindowForYmd(operatingHoursConfig, date),
        [operatingHoursConfig, date],
    );
    const standardStartAllowed = useMemo(
        () => allowedStartHhmmList(standardDayWindow.start, standardDayWindow.end),
        [standardDayWindow],
    );
    const standardEndAllowed = useMemo(
        () => allowedEndHhmmList(standardDayWindow.start, standardDayWindow.end, startTime),
        [standardDayWindow, startTime],
    );

    const detailDayWindow = useMemo(
        () => resolveOperatingWindowForYmd(operatingHoursConfig, date),
        [operatingHoursConfig, date],
    );
    const detailStartAllowed = useMemo(
        () => allowedStartHhmmListBeforeEnd(detailDayWindow.start, detailDayWindow.end, rangeEndTime),
        [detailDayWindow, rangeEndTime],
    );
    const detailEndAllowed = useMemo(
        () => allowedEndHhmmList(detailDayWindow.start, detailDayWindow.end, rangeStartTime),
        [detailDayWindow, rangeStartTime],
    );

    const avrStartWindow = useMemo(
        () => resolveOperatingWindowForYmd(operatingHoursConfig, rangeStartDate),
        [operatingHoursConfig, rangeStartDate],
    );
    const avrEndWindow = useMemo(
        () => resolveOperatingWindowForYmd(operatingHoursConfig, rangeEndDate),
        [operatingHoursConfig, rangeEndDate],
    );
    const avrStartAllowed = useMemo(() => {
        if (rangeStartDate === rangeEndDate) {
            return allowedStartHhmmListBeforeEnd(avrStartWindow.start, avrStartWindow.end, rangeEndTime);
        }
        return allowedStartHhmmList(avrStartWindow.start, avrStartWindow.end);
    }, [rangeStartDate, rangeEndDate, avrStartWindow, rangeEndTime]);
    const avrEndAllowed = useMemo(() => {
        if (rangeStartDate === rangeEndDate) {
            return allowedEndHhmmList(avrStartWindow.start, avrStartWindow.end, rangeStartTime);
        }
        return allowedEndHhmmListAvrRange(
            rangeStartDate,
            rangeStartTime,
            rangeEndDate,
            avrEndWindow,
            buildStartEndPayloadFromWallClock,
        );
    }, [rangeStartDate, rangeEndDate, avrStartWindow, avrEndWindow, rangeStartTime]);

    const operatingHoursHint = useMemo(() => {
        if (holidayHit) return '';
        if (bookingKind === 'avr_range') {
            const a = resolveOperatingWindowForYmd(operatingHoursConfig, rangeStartDate);
            const b = resolveOperatingWindowForYmd(operatingHoursConfig, rangeEndDate);
            if (rangeStartDate === rangeEndDate) {
                return `Library hours this day: ${a.start}–${a.end} (PHT). Half-hour slots; you may end exactly at closing.`;
            }
            return `Start date hours: ${a.start}–${a.end} (PHT). End date hours: ${b.start}–${b.end} (PHT).`;
        }
        const w = resolveOperatingWindowForYmd(operatingHoursConfig, date);
        return `Library hours this day: ${w.start}–${w.end} (PHT). Half-hour slots; you may end exactly at closing.`;
    }, [bookingKind, date, rangeStartDate, rangeEndDate, operatingHoursConfig, holidayHit]);

    const operatingHoursError = useMemo(() => {
        if (holidayHit) return '';
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
        const basic = validateWallClockWindowForKind(bookingKind, wallFields);
        if (basic) return '';
        return operatingHoursWallClockError(
            bookingKind,
            wallFields,
            operatingHoursConfig,
            buildStartEndPayloadFromWallClock,
        );
    }, [
        holidayHit,
        bookingKind,
        rangeStartDate,
        rangeStartTime,
        rangeEndDate,
        rangeEndTime,
        date,
        startTime,
        endTime,
        operatingHoursConfig,
    ]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        if (holidayHit) {
            setError('Cannot reserve on a holiday.');
            return;
        }
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

        const timeErr = validateWallClockWindowForKind(bookingKind, wallFields);
        if (timeErr) {
            const halfMsg = 'Times must use half-hour boundaries only (:00 or :30).';
            setError(
                timeErr === halfMsg && bookingKind === 'half_hour_details'
                    ? 'For this space, times must be on the half-hour (:00 or :30).'
                    : timeErr,
            );
            return;
        }

        if (durationLimitError) {
            setError(durationLimitError);
            return;
        }

        if (operatingHoursError) {
            setError(operatingHoursError);
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
            if (
                effectiveSeatingCapacity != null &&
                Number.isFinite(pc) &&
                pc > effectiveSeatingCapacity
            ) {
                setError(capacityExceededMessage);
                return;
            }
        }
        if (needsEventAudience) {
            if (!eventRequestType) {
                setError('Select whether this reservation is an organization event or an employee event.');
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
                ...(needsEventAudience ? { event_request_type: eventRequestType } : {}),
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
                    || d?.errors?.event_request_type?.[0]
                    || d?.errors?.slot?.[0]
                    || d?.errors?.reservation?.[0]
                    || 'Failed to create reservation.'
            );
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-w-0 w-full max-w-3xl pb-2 sm:pb-0">
            <h1 className={`${ui.pageTitle} mb-4 max-w-xl`}>New reservation</h1>
            {showcaseSpaces.length > 0 && (
                <div className="mb-6 w-full min-w-0">
                    <SpaceShowcaseCarousel
                        spaces={showcaseSpaces}
                        onSpaceSelect={(s) => {
                            if (s?.type === 'confab' && !s?.is_confab_pool) {
                                const pool = showcaseSpaces.find((x) => x.is_confab_pool);
                                if (pool) {
                                    setSpaceIdVal(String(pool.id));
                                    return;
                                }
                            }
                            setSpaceIdVal(String(s.id));
                        }}
                        heading="Choose a space"
                    />
                </div>
            )}
            {(guidelines.trim() !== '' ||
                (selectedSpace && !isConfabPool && spaceGuidelinesHasDetails(selectedSpace)) ||
                isConfabPool) && (
                <div className="mb-4 space-y-3 max-w-xl">
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
            <form onSubmit={handleSubmit} className={`min-w-0 max-w-xl space-y-4 p-4 sm:p-6 ${ui.cardFlat}`}>
                {error && <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg">{error}</div>}
                <p id="reserve-timezone-hint" className="text-xs text-slate-500 -mt-1 mb-1">
                    Date and times are in Philippines civil time ({BOOKING_TIMEZONE} / PHT), matching the server.
                </p>
                {operatingHoursHint && (
                    <p className="text-xs text-slate-600 -mt-1 mb-1" role="status">
                        {operatingHoursHint}
                    </p>
                )}
                <div>
                    <label htmlFor="reserve-room" className="block text-sm font-medium text-slate-700 mb-1">Room *</label>
                    <select
                        id="reserve-room"
                        value={spaceIdVal}
                        onChange={(e) => setSpaceIdVal(e.target.value)}
                        required
                        className={`w-full ${ui.select}`}
                        aria-describedby="reserve-timezone-hint"
                    >
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
                    {selectedSpace && !isSelectedSpaceEligible && selectedSpaceBlockMessage && (
                        <p className="mt-2 text-xs text-slate-600">
                            {selectedSpaceBlockMessage}
                        </p>
                    )}
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
                {needsEventAudience && (
                    <div>
                        <label htmlFor="reserve-event-audience" className="block text-sm font-medium text-slate-700 mb-1">
                            Event audience *
                        </label>
                        <select
                            id="reserve-event-audience"
                            value={eventRequestType}
                            onChange={(e) => setEventRequestType(e.target.value)}
                            required
                            className={`w-full ${ui.select}`}
                            aria-describedby="reserve-timezone-hint"
                        >
                            <option value="">Select one…</option>
                            <option value="organization">Organization event</option>
                            <option value="employee">Employee event</option>
                        </select>
                        <p className="mt-1 text-xs text-slate-500">
                            Organization events route to SACDEV for approval; employee events route to your saved college or office.
                        </p>
                    </div>
                )}
                <div>
                    <label htmlFor="reserve-date" className="block text-sm font-medium text-slate-700 mb-1">Date *</label>
                    <input
                        id="reserve-date"
                        type="date"
                        value={bookingKind === 'avr_range' ? rangeStartDate : date}
                        onChange={(e) => {
                            const v = e.target.value;
                            if (bookingKind === 'avr_range') setRangeStartDate(v);
                            else setDate(v);
                        }}
                        required
                        className={ui.input}
                        aria-describedby="reserve-timezone-hint"
                    />
                    {holidayBlockMessage && (
                        <p className="mt-2 text-sm font-medium text-red-700" role="alert">
                            {holidayBlockMessage}
                        </p>
                    )}
                </div>
                {bookingKind === 'avr_range' ? (
                    <>
                        <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                            <fieldset aria-describedby="reserve-timezone-hint">
                                <legend className="block text-sm font-medium text-slate-700 mb-1">Start time *</legend>
                                <HalfHourWallClockSelect
                                    idPrefix="res-range-start"
                                    hourLabel="Start time, hour"
                                    minuteLabel="Start time, minute"
                                    visibleFieldLabels={visibleReservationTimeLabels}
                                    value={rangeStartTime}
                                    onChange={setRangeStartTime}
                                    disabled={Boolean(holidayHit)}
                                    allowedHhmmList={avrStartAllowed}
                                />
                            </fieldset>
                            <div>
                                <label htmlFor="reserve-end-date" className="block text-sm font-medium text-slate-700 mb-1">End date *</label>
                                <input
                                    id="reserve-end-date"
                                    type="date"
                                    value={rangeEndDate}
                                    onChange={(e) => setRangeEndDate(e.target.value)}
                                    required
                                    className={ui.input}
                                    aria-describedby="reserve-timezone-hint"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                            <fieldset aria-describedby="reserve-timezone-hint">
                                <legend className="block text-sm font-medium text-slate-700 mb-1">End time *</legend>
                                <HalfHourWallClockSelect
                                    idPrefix="res-range-end"
                                    hourLabel="End time, hour"
                                    minuteLabel="End time, minute"
                                    visibleFieldLabels={visibleReservationTimeLabels}
                                    value={rangeEndTime}
                                    onChange={setRangeEndTime}
                                    disabled={Boolean(holidayHit)}
                                    allowedHhmmList={avrEndAllowed}
                                />
                            </fieldset>
                            <div className="hidden min-[520px]:block" aria-hidden="true" />
                        </div>
                        <div>
                            <label htmlFor="reserve-event-title" className="block text-sm font-medium text-slate-700 mb-1">Reservation title *</label>
                            <input
                                id="reserve-event-title"
                                type="text"
                                value={eventTitle}
                                onChange={(e) => setEventTitle(e.target.value)}
                                required
                                className={ui.input}
                                placeholder="Reservation title"
                            />
                        </div>
                        <div>
                            <label htmlFor="reserve-event-description" className="block text-sm font-medium text-slate-700 mb-1">
                                Event description / justification / notes (optional)
                            </label>
                            <textarea
                                id="reserve-event-description"
                                value={eventDescription}
                                onChange={(e) => setEventDescription(e.target.value)}
                                rows={4}
                                className={ui.input}
                                placeholder="Add details for approvers"
                            />
                        </div>
                        <div>
                            <label htmlFor="reserve-participant-count" className="block text-sm font-medium text-slate-700 mb-1">Number of participants *</label>
                            <input
                                id="reserve-participant-count"
                                type="number"
                                min="1"
                                max={effectiveSeatingCapacity ?? undefined}
                                step="1"
                                value={participantCount}
                                onChange={(e) => setParticipantCount(e.target.value)}
                                required
                                className={ui.input}
                                placeholder="e.g. 50"
                                aria-invalid={participantOverCapacity || undefined}
                            />
                            {effectiveSeatingCapacity != null && (
                                <p className="mt-1 text-xs text-slate-600">
                                    Seating capacity for this room: <span className="font-medium text-slate-800">{effectiveSeatingCapacity}</span>.
                                </p>
                            )}
                            {participantOverCapacity && (
                                <p className="mt-1 text-sm text-red-700" role="alert">
                                    {capacityExceededMessage}
                                </p>
                            )}
                        </div>
                    </>
                ) : bookingKind === 'half_hour_details' ? (
                    <>
                        <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                            <fieldset aria-describedby="reserve-timezone-hint">
                                <legend className="block text-sm font-medium text-slate-700 mb-1">Start time *</legend>
                                <HalfHourWallClockSelect
                                    idPrefix="res-details-start"
                                    hourLabel="Start time, hour"
                                    minuteLabel="Start time, minute"
                                    visibleFieldLabels={visibleReservationTimeLabels}
                                    value={rangeStartTime}
                                    onChange={setRangeStartTime}
                                    disabled={Boolean(holidayHit)}
                                    allowedHhmmList={detailStartAllowed}
                                />
                            </fieldset>
                            <fieldset aria-describedby="reserve-timezone-hint">
                                <legend className="block text-sm font-medium text-slate-700 mb-1">End time *</legend>
                                <HalfHourWallClockSelect
                                    idPrefix="res-details-end"
                                    hourLabel="End time, hour"
                                    minuteLabel="End time, minute"
                                    visibleFieldLabels={visibleReservationTimeLabels}
                                    value={rangeEndTime}
                                    onChange={setRangeEndTime}
                                    disabled={Boolean(holidayHit)}
                                    allowedHhmmList={detailEndAllowed}
                                />
                            </fieldset>
                        </div>
                        <div>
                            <label htmlFor="reserve-event-title" className="block text-sm font-medium text-slate-700 mb-1">Reservation title *</label>
                            <input
                                id="reserve-event-title"
                                type="text"
                                value={eventTitle}
                                onChange={(e) => setEventTitle(e.target.value)}
                                required
                                className={ui.input}
                                placeholder="Reservation title"
                            />
                        </div>
                        <div>
                            <label htmlFor="reserve-event-description" className="block text-sm font-medium text-slate-700 mb-1">
                                Description / justification / notes (optional)
                            </label>
                            <textarea
                                id="reserve-event-description"
                                value={eventDescription}
                                onChange={(e) => setEventDescription(e.target.value)}
                                rows={4}
                                className={ui.input}
                                placeholder="Add details for approvers"
                            />
                        </div>
                        <div>
                            <label htmlFor="reserve-participant-count" className="block text-sm font-medium text-slate-700 mb-1">Number of participants *</label>
                            <input
                                id="reserve-participant-count"
                                type="number"
                                min="1"
                                max={effectiveSeatingCapacity ?? undefined}
                                step="1"
                                value={participantCount}
                                onChange={(e) => setParticipantCount(e.target.value)}
                                required
                                className={ui.input}
                                placeholder="e.g. 50"
                                aria-invalid={participantOverCapacity || undefined}
                            />
                            {effectiveSeatingCapacity != null && (
                                <p className="mt-1 text-xs text-slate-600">
                                    Seating capacity for this room: <span className="font-medium text-slate-800">{effectiveSeatingCapacity}</span>.
                                </p>
                            )}
                            {participantOverCapacity && (
                                <p className="mt-1 text-sm text-red-700" role="alert">
                                    Over the seating capacity.
                                </p>
                            )}
                        </div>
                    </>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-4 min-[520px]:grid-cols-2">
                            <fieldset aria-describedby="reserve-timezone-hint">
                                <legend className="block text-sm font-medium text-slate-700 mb-1">Start time *</legend>
                                <HalfHourWallClockSelect
                                    idPrefix="res-standard-start"
                                    hourLabel="Start time, hour"
                                    minuteLabel="Start time, minute"
                                    visibleFieldLabels={visibleReservationTimeLabels}
                                    value={startTime}
                                    onChange={setStartTime}
                                    disabled={Boolean(holidayHit)}
                                    allowedHhmmList={standardStartAllowed}
                                />
                            </fieldset>
                            <fieldset aria-describedby="reserve-timezone-hint">
                                <legend className="block text-sm font-medium text-slate-700 mb-1">End time *</legend>
                                <HalfHourWallClockSelect
                                    idPrefix="res-standard-end"
                                    hourLabel="End time, hour"
                                    minuteLabel="End time, minute"
                                    visibleFieldLabels={visibleReservationTimeLabels}
                                    value={endTime}
                                    onChange={setEndTime}
                                    disabled={Boolean(holidayHit)}
                                    allowedHhmmList={standardEndAllowed}
                                />
                            </fieldset>
                        </div>
                        <div>
                            <label htmlFor="reserve-purpose" className="block text-sm font-medium text-slate-700 mb-1">Purpose (optional)</label>
                            <textarea id="reserve-purpose" value={purpose} onChange={(e) => setPurpose(e.target.value)} rows={3} className={ui.input} placeholder="Brief purpose of use" />
                        </div>
                    </>
                )}
                {reservationWindowPreview && (
                    <p className="text-sm text-slate-700" role="status">
                        <span className="font-medium text-xu-primary">Reservation window:</span>{' '}
                        {reservationWindowPreview}
                    </p>
                )}
                <button
                    type="submit"
                    disabled={
                        loading ||
                        (selectedSpace && !isSelectedSpaceEligible) ||
                        participantOverCapacity ||
                        Boolean(holidayHit) ||
                        Boolean(durationLimitError) ||
                        Boolean(operatingHoursError)
                    }
                    className={ui.btnPrimaryFull}
                >
                    Submit reservation
                </button>
                {operatingHoursError && !holidayHit && (
                    <p className="text-sm text-red-700" role="alert">
                        {operatingHoursError}
                    </p>
                )}
                {durationLimitError && (
                    <p className="text-sm text-red-700" role="alert">
                        {durationLimitError}
                    </p>
                )}
            </form>
        </div>
    );
}

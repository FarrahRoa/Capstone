import { useMemo, useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api';
import { useAuth } from '../../contexts/AuthContext';
import { isAdminScheduleViewer } from '../../utils/isAdminScheduleViewer';
import { userFacingSpaceName, dedupeConfabFamilyForLegend } from '../../utils/userFacingSpaceName';
import { getSpaceIneligibilityMessage, getSpaceRestrictionLabel, isUserEligibleForSpace } from '../../utils/spaceEligibility';
import {
    buildManilaHalfHourSlots,
    buildManilaMonthCells,
    formatManilaHalfHourSlotLabel,
    formatManilaSlotGutterTimes,
    MANILA_OFFSET,
    manilaMonthYearLabel,
    manilaSelectedDayTitle,
    manilaTimeParamFromHour,
    manilaTodayParts,
    manilaYmdFromInstant,
    manilaYmdFromParts,
} from '../../utils/manilaTime';
import {
    exemptFromReservationLeadTime,
    policyBlockReasonForManilaReservationDay,
} from '../../utils/reservationLeadTimePolicy';
import { useBookingPolicyClock } from '../../utils/useBookingPolicyClock';
import AvailableBookingSlotCard from './AvailableBookingSlotCard';
import { unwrapData } from '../../utils/apiEnvelope';
import { BOOKING_TIMEZONE } from '../../utils/timeDisplay';
import {
    isCalendarViewMonthAtOrBeyondMax,
    isYmdAfterMaxBooking,
    normalizeOperatingHoursPayload,
} from '../../utils/operatingHours';
import { colorForOperationalSpaceId, colorForSpaceId } from '../../utils/spaceColors';
import { getReservationStatusLabel } from '../../utils/reservationVocabulary';
import SpaceShowcaseCarousel from './SpaceShowcaseCarousel';
import UserDashboardSlotsPanel from './UserDashboardSlotsPanel';
import StatusIndicator from './StatusIndicator';

const DAY_START_HOUR = 9;
const DAY_END_HOUR = 18;

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function slotEndInstantForManilaYmd(ymd, slot) {
    const hh = String(slot?.hourEnd ?? '').padStart(2, '0');
    const mm = String(slot?.minuteEnd ?? '').padStart(2, '0');
    return Date.parse(`${ymd}T${hh}:${mm}:00${MANILA_OFFSET}`);
}

/** Hide same-day slots that are already over, using Manila civil date for "today". */
function shouldHidePastSlotOnSelectedManilaDay(selectedYmd, slot, clock) {
    if (!selectedYmd || !clock) return false;
    const todayYmd = manilaYmdFromInstant(clock);
    if (selectedYmd !== todayYmd) return false;
    const endMs = slotEndInstantForManilaYmd(selectedYmd, slot);
    if (!Number.isFinite(endMs)) return false;
    return endMs <= clock.getTime();
}

/** Compact label for tiny calendar chips; full name stays in title/tooltip. */
function abbreviateSpaceName(name) {
    if (!name || typeof name !== 'string') return '?';
    const t = name.trim();
    if (t === 'Confab' || t === 'Medical Confab') return t;
    const medical = t.match(/^Medical\s+Confab\s+(\d+)/i);
    if (medical) return `M${medical[1]}`;
    const confab = t.match(/^Confab\s+(\d+)/i);
    if (confab) return `C${confab[1]}`;
    if (t.length <= 6) return t;
    return `${t.slice(0, 5)}…`;
}

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

function reservationForSlot(slot, reservedSlots) {
    const list = Array.isArray(reservedSlots) ? reservedSlots : [];
    const slotStart = new Date(slot.start_at).getTime();
    const slotEnd = new Date(slot.end_at).getTime();
    for (const r of list) {
        const rs = new Date(r.start_at).getTime();
        const re = new Date(r.end_at).getTime();
        if (slotStart < re && slotEnd > rs) {
            return r;
        }
    }
    return null;
}

function manilaTimeRangeLabel(startIso, endIso) {
    const fmt = new Intl.DateTimeFormat('en-US', {
        timeZone: BOOKING_TIMEZONE,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
    return `${fmt.format(new Date(startIso))} - ${fmt.format(new Date(endIso))}`;
}

function slotOccupiedStatusLabel(reservation) {
    if (!reservation?.status) return 'Occupied';
    return getReservationStatusLabel(reservation.status);
}

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

function overviewRowDedupeKey(space, adminSchedule, readOnly) {
    if (!space) return '';
    if (adminSchedule && !readOnly) return String(space.id);
    return String(space.id);
}

/**
 * @param {Array<string|number>} spaceIds
 * @param {Array<{ id: number|string, name: string, record_name?: string, type?: string, is_confab_pool?: boolean }>} spaces
 */
function overviewSpaceRows(spaceIds, spaces, readOnly = false, adminSchedule = false) {
    if (!Array.isArray(spaceIds) || spaceIds.length === 0) return [];
    const out = [];
    const seen = new Set();
    for (const id of spaceIds) {
        const s = spaces.find((x) => String(x.id) === String(id));
        const name = s
            ? adminSchedule && !readOnly
                ? (s.record_name != null && String(s.record_name).trim()) || s.name || ''
                : userFacingSpaceName(s)
            : `Space ${id}`;
        const row = s ? { ...s, name } : { id, name };
        const dedupeKey = s ? overviewRowDedupeKey(s, adminSchedule, readOnly) : `missing-${id}`;
        if (seen.has(dedupeKey)) continue;
        seen.add(dedupeKey);
        out.push(row);
    }
    return out;
}

function legendPillLabel(space, adminSchedule, readOnly) {
    if (adminSchedule && !readOnly) {
        return (space?.record_name != null && String(space.record_name).trim()) || space?.name || '';
    }
    return userFacingSpaceName(space);
}

function initialManilaCalendarState() {
    const t = manilaTodayParts();
    return {
        selectedYmd: manilaYmdFromParts(t.year, t.monthIndex0, t.day),
        viewYear: t.year,
        viewMonth: t.monthIndex0,
    };
}

function initialBookingCalendarSelection() {
    return initialManilaCalendarState();
}

export default function BookingCalendar({
    user,
    spaces,
    /** Student dashboard only: full API list filtered for image preview (Confab 1–6, medical when eligible). */
    showcaseSpaces,
    spacesLoadError,
    embedded = false,
    /** Larger calendar + hides overview legend; only pass from User Dashboard embedded booking card. */
    userDashboardEmbedded = false,
    readOnly = false,
    headingLevel = 3,
}) {
    const { hasPermission } = useAuth();
    const adminSchedule = isAdminScheduleViewer(user, hasPermission);
    const reservationLeadTimeExempt = exemptFromReservationLeadTime(user);
    const carouselSpaces = showcaseSpaces ?? spaces;

    const bookableSpaces = useMemo(() => {
        if (adminSchedule) return spaces;
        return spaces.filter((s) => !(s?.type === 'confab' && !s?.is_confab_pool));
    }, [spaces, adminSchedule]);

    /** Physical / bookable locations for the public board (excludes confab assignment pool). */
    const timelineSpaces = useMemo(
        () => spaces.filter((s) => !s?.is_confab_pool),
        [spaces]
    );

    /** Legend: show one pill per space (including Confab 1..N). */
    const legendSourceSpaces = useMemo(() => {
        if (adminSchedule) return timelineSpaces;
        return dedupeConfabFamilyForLegend(timelineSpaces);
    }, [adminSchedule, timelineSpaces]);

    const [cal, setCal] = useState(() => initialBookingCalendarSelection());
    const { selectedYmd, viewYear, viewMonth } = cal;
    const [selectedSpaceId, setSelectedSpaceId] = useState('');
    const calendarClock = useBookingPolicyClock();

    const cells = useMemo(() => buildManilaMonthCells(viewYear, viewMonth), [viewYear, viewMonth]);

    const cellYmdBounds = useMemo(() => {
        if (!cells.length) {
            return { min: '', max: '' };
        }
        let min = cells[0].ymd;
        let max = cells[0].ymd;
        for (const c of cells) {
            if (c.ymd < min) min = c.ymd;
            if (c.ymd > max) max = c.ymd;
        }
        return { min, max };
    }, [cells]);

    const todayYmd = useMemo(() => manilaYmdFromInstant(calendarClock), [calendarClock]);

    useEffect(() => {
        const pool = readOnly ? timelineSpaces : bookableSpaces;
        if (pool.length === 0) {
            setSelectedSpaceId('');
            return;
        }
        const exists = pool.some((s) => String(s.id) === String(selectedSpaceId));
        if (!selectedSpaceId || !exists) {
            setSelectedSpaceId(String(pool[0].id));
        }
    }, [bookableSpaces, timelineSpaces, selectedSpaceId, readOnly]);

    const isPastDay = useCallback((ymd) => String(ymd) < String(todayYmd), [todayYmd]);

    const leadPolicyReasonForSelectedDay = useMemo(() => {
        if (reservationLeadTimeExempt || !selectedYmd) return null;
        return policyBlockReasonForManilaReservationDay(selectedYmd, calendarClock);
    }, [reservationLeadTimeExempt, selectedYmd, calendarClock]);

    /** Policy note for sidebar + aggregated schedule (shown for every viewer except exempt admin). */
    const activeLeadPolicyBlockMessage = leadPolicyReasonForSelectedDay || '';

    /** Blocks Book links / aggregated open-slot CTAs for the selected Manila calendar day when policy forbids reservations. */
    const schedulingRulesBlockBookings = Boolean(activeLeadPolicyBlockMessage);

    const [reservedSlots, setReservedSlots] = useState([]);
    const [publicScheduleRows, setPublicScheduleRows] = useState([]);
    const [loadingSlots, setLoadingSlots] = useState(() => Boolean(readOnly));
    const [loginRequiredNudge, setLoginRequiredNudge] = useState('');
    const [fullyBookedYmd, setFullyBookedYmd] = useState({});
    const [summaryLoading, setSummaryLoading] = useState(false);
    const [overviewByDate, setOverviewByDate] = useState({});
    const [overviewLoading, setOverviewLoading] = useState(false);
    const [confabRoomsDayRows, setConfabRoomsDayRows] = useState([]);
    const [holidays, setHolidays] = useState([]);
    const [maxBookingDate, setMaxBookingDate] = useState(null);

    useEffect(() => {
        if (!cellYmdBounds.min || !cellYmdBounds.max) {
            setOverviewByDate({});
            setOverviewLoading(false);
            return;
        }
        let cancelled = false;
        setOverviewLoading(true);
        api.get(readOnly ? '/public/availability/month-overview' : '/availability/month-overview', {
            params: {
                from: cellYmdBounds.min,
                to: cellYmdBounds.max,
            },
        })
            .then(({ data }) => {
                if (cancelled) return;
                const payload = unwrapData(data);
                setOverviewByDate(payload?.dates && typeof payload.dates === 'object' ? payload.dates : {});
            })
            .catch(() => {
                if (!cancelled) setOverviewByDate({});
            })
            .finally(() => {
                if (!cancelled) setOverviewLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, [cellYmdBounds.min, cellYmdBounds.max, readOnly]);

    useEffect(() => {
        api.get('/policies/operating-hours')
            .then(({ data }) => {
                const payload = unwrapData(data);
                setHolidays(Array.isArray(payload?.holidays) ? payload.holidays : []);
                const hours = normalizeOperatingHoursPayload(payload?.hours);
                setMaxBookingDate(hours.max_booking_date);
            })
            .catch(() => {
                setHolidays([]);
                setMaxBookingDate(null);
            });
    }, []);

    const nextMonthDisabled = useMemo(
        () => isCalendarViewMonthAtOrBeyondMax(viewYear, viewMonth, maxBookingDate),
        [viewYear, viewMonth, maxBookingDate]
    );

    useEffect(() => {
        if (!maxBookingDate || !selectedYmd || selectedYmd <= maxBookingDate) return;
        const [y, m] = maxBookingDate.split('-').map(Number);
        setCal((c) => ({ ...c, selectedYmd: maxBookingDate, viewYear: y, viewMonth: m - 1 }));
    }, [maxBookingDate, selectedYmd]);

    useEffect(() => {
        if (readOnly) {
            setFullyBookedYmd({});
            setSummaryLoading(false);
            return;
        }
        if (!selectedSpaceId || !cellYmdBounds.min || !cellYmdBounds.max) {
            setFullyBookedYmd({});
            setSummaryLoading(false);
            return;
        }
        let cancelled = false;
        setSummaryLoading(true);
        api.get('/availability/month-summary', {
            params: {
                space_id: selectedSpaceId,
                from: cellYmdBounds.min,
                to: cellYmdBounds.max,
            },
        })
            .then(({ data }) => {
                if (cancelled) return;
                const payload = unwrapData(data);
                const list = Array.isArray(payload?.fully_booked_dates) ? payload.fully_booked_dates : [];
                const next = {};
                list.forEach((d) => {
                    next[String(d)] = true;
                });
                setFullyBookedYmd(next);
            })
            .catch(() => {
                if (!cancelled) setFullyBookedYmd({});
            })
            .finally(() => {
                if (!cancelled) setSummaryLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, [selectedSpaceId, cellYmdBounds.min, cellYmdBounds.max, readOnly]);

    const isFullyBooked = useCallback(
        (ymd) => Boolean(selectedSpaceId && !summaryLoading && fullyBookedYmd[ymd]),
        [selectedSpaceId, summaryLoading, fullyBookedYmd]
    );

    useEffect(() => {
        if (readOnly) {
            if (!selectedYmd) {
                setPublicScheduleRows([]);
                return;
            }
            let cancelled = false;
            setLoadingSlots(true);
            setLoginRequiredNudge('');
            const params = { date: selectedYmd };
            if (selectedSpaceId) {
                params.space_id = selectedSpaceId;
            }
            api.get('/public/schedule-overview', { params })
                .then(({ data }) => {
                    if (cancelled) return;
                    const payload = unwrapData(data);
                    setPublicScheduleRows(Array.isArray(payload?.spaces) ? payload.spaces : []);
                })
                .catch(() => {
                    if (!cancelled) setPublicScheduleRows([]);
                })
                .finally(() => {
                    if (!cancelled) setLoadingSlots(false);
                });
            return () => {
                cancelled = true;
            };
        }

        if (!selectedSpaceId || !selectedYmd) {
            setReservedSlots([]);
            return;
        }
        setLoadingSlots(true);
        setLoginRequiredNudge('');

        api.get('/availability', { params: { date: selectedYmd, space_id: selectedSpaceId } })
            .then(({ data }) => {
                const rows = unwrapData(data);
                const row = Array.isArray(rows)
                    ? rows.find((r) => String(r.space?.id) === String(selectedSpaceId))
                    : null;
                setReservedSlots(row?.reserved_slots || []);
            })
            .catch(() => setReservedSlots([]))
            .finally(() => setLoadingSlots(false));
    }, [selectedYmd, selectedSpaceId, readOnly]);

    const spaceOptionsForSelect = readOnly ? timelineSpaces : bookableSpaces;
    const selectedSpace = spaceOptionsForSelect.find((s) => String(s.id) === String(selectedSpaceId));
    const eligible = readOnly ? true : (selectedSpace ? isUserEligibleForSpace(user, selectedSpace) : false);
    const restrictionLabel = selectedSpace ? getSpaceRestrictionLabel(selectedSpace) : '';
    const selectedIsConfabPool = Boolean(selectedSpace?.type === 'confab' && selectedSpace?.is_confab_pool);

    const slots = useMemo(
        () => buildManilaHalfHourSlots(selectedYmd, reservedSlots, DAY_START_HOUR, DAY_END_HOUR),
        [selectedYmd, reservedSlots]
    );
    const visibleSlots = useMemo(
        () => slots.filter((slot) => !shouldHidePastSlotOnSelectedManilaDay(selectedYmd, slot, calendarClock)),
        [slots, selectedYmd, calendarClock]
    );

    useEffect(() => {
        if (readOnly) {
            setConfabRoomsDayRows([]);
            return;
        }
        if (!selectedIsConfabPool || !selectedYmd) {
            setConfabRoomsDayRows([]);
            return;
        }
        let cancelled = false;
        api.get('/availability', { params: { date: selectedYmd } })
            .then(({ data }) => {
                if (cancelled) return;
                const rows = unwrapData(data);
                setConfabRoomsDayRows(Array.isArray(rows) ? rows : []);
            })
            .catch(() => {
                if (!cancelled) setConfabRoomsDayRows([]);
            });
        return () => {
            cancelled = true;
        };
    }, [selectedIsConfabPool, selectedYmd, readOnly]);

    const hasBlockingReservationsInView = useMemo(() => {
        if (selectedIsConfabPool) {
            return (Array.isArray(confabRoomsDayRows) ? confabRoomsDayRows : []).some(
                (row) => Array.isArray(row?.reserved_slots) && row.reserved_slots.length > 0
            );
        }
        return Array.isArray(reservedSlots) && reservedSlots.length > 0;
    }, [selectedIsConfabPool, confabRoomsDayRows, reservedSlots]);

    const reservationsForDetailPanel = useMemo(() => {
        /** @type {{ id: number|string, reservation_number?: string|null, start_at: string, end_at: string, details_revealed?: boolean, title?: string|null, description?: string|null, user?: {id:number|string,name:string}|null, space_name?: string }[]} */
        const out = [];

        if (!selectedYmd) return out;

        const pushSlot = (r, space_name) => {
            if (!r) return;
            const viewerId = user?.id;
            const isOwner =
                viewerId != null && r.user?.id != null && String(viewerId) === String(r.user.id);
            const revealed =
                typeof r.details_revealed === 'boolean' ? r.details_revealed : isOwner;
            out.push({
                ...r,
                space_name: space_name || '',
                details_revealed: revealed,
            });
        };

        if (selectedIsConfabPool) {
            const rows = Array.isArray(confabRoomsDayRows) ? confabRoomsDayRows : [];
            rows.forEach((row) => {
                const space = row?.space;
                if (!space || space.is_confab_pool || space.type !== 'confab') return;
                const list = Array.isArray(row.reserved_slots) ? row.reserved_slots : [];
                list.forEach((r) => pushSlot(r, space.name || ''));
            });
        } else {
            const list = Array.isArray(reservedSlots) ? reservedSlots : [];
            list.forEach((r) => pushSlot(r, selectedSpace?.name || ''));
        }

        out.sort((a, b) => new Date(a.start_at).getTime() - new Date(b.start_at).getTime());

        const seen = new Set();
        return out.filter((r) => {
            const key = `${r.id}-${r.start_at}-${r.end_at}-${r.space_name || ''}`;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    }, [selectedYmd, selectedIsConfabPool, confabRoomsDayRows, reservedSlots, selectedSpace?.name, user?.id]);
    const publicReadOnlyReservedSlots = useMemo(() => {
        if (!readOnly || !selectedSpaceId) return [];
        const row = publicScheduleRows.find((r) => String(r?.space?.id) === String(selectedSpaceId));
        return Array.isArray(row?.occupied_slots) ? row.occupied_slots : [];
    }, [readOnly, selectedSpaceId, publicScheduleRows]);

    const publicReadOnlySlots = useMemo(() => {
        if (!readOnly || !selectedYmd || !selectedSpaceId) return [];
        return buildManilaHalfHourSlots(
            selectedYmd,
            publicReadOnlyReservedSlots,
            DAY_START_HOUR,
            DAY_END_HOUR
        );
    }, [readOnly, selectedYmd, selectedSpaceId, publicReadOnlyReservedSlots]);

    const visiblePublicReadOnlySlots = useMemo(
        () =>
            publicReadOnlySlots.filter(
                (slot) => !shouldHidePastSlotOnSelectedManilaDay(selectedYmd, slot, calendarClock)
            ),
        [publicReadOnlySlots, selectedYmd, calendarClock]
    );
    const spacesWithColors = useMemo(() => {
        const pickColor = adminSchedule && !readOnly ? colorForOperationalSpaceId : colorForSpaceId;
        return legendSourceSpaces.map((s) => ({ ...s, __color: pickColor(s.id, spaces) }));
    }, [legendSourceSpaces, adminSchedule, readOnly, spaces]);

    const goPrevMonth = useCallback(() => {
        setCal((c) => {
            if (c.viewMonth === 0) {
                return { ...c, viewYear: c.viewYear - 1, viewMonth: 11 };
            }
            return { ...c, viewMonth: c.viewMonth - 1 };
        });
    }, []);

    const goNextMonth = useCallback(() => {
        setCal((c) => {
            if (isCalendarViewMonthAtOrBeyondMax(c.viewYear, c.viewMonth, maxBookingDate)) {
                return c;
            }
            if (c.viewMonth === 11) {
                return { ...c, viewYear: c.viewYear + 1, viewMonth: 0 };
            }
            return { ...c, viewMonth: c.viewMonth + 1 };
        });
    }, [maxBookingDate]);

    const onPickDate = (cell) => {
        if (isPastDay(cell.ymd)) return;
        if (isYmdAfterMaxBooking(cell.ymd, maxBookingDate)) return;
        if (holidayForYmd(holidays, cell.ymd)) return;
        if (isFullyBooked(cell.ymd)) return;
        const [y, m] = cell.ymd.split('-').map(Number);
        setCal({ selectedYmd: cell.ymd, viewYear: y, viewMonth: m - 1 });
    };

    const shellClass = embedded
        ? 'bg-white min-w-0 rounded-2xl border border-slate-200/90 shadow-lg shadow-slate-300/25 overflow-hidden ring-1 ring-slate-200/70'
        : 'bg-xu-page min-w-0 -mx-4 px-4 py-8 sm:mx-0 sm:rounded-2xl sm:px-8 border border-slate-200/60 sm:border-0';

    const uDashLayout = Boolean(userDashboardEmbedded && embedded && !readOnly);
    /** Student & Employee Home Dashboard: calendar + time slots + reservation details. */
    const userDashThreeCol = uDashLayout;
    const calCellBox = uDashLayout
        ? 'h-[4.35rem] w-[3.65rem] sm:h-[4.85rem] sm:w-[4.1rem]'
        : 'h-[3.1rem] w-[2.6rem] sm:h-[3.4rem] sm:w-[3rem]';
    const calPickBtnW = uDashLayout ? 'w-[3.65rem] sm:w-[4.1rem]' : 'w-[2.6rem] sm:w-[3rem]';

    const HeadingTag = `h${headingLevel}`;

    const reservationDetailsPanel = (
        <article className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
            <div className="shrink-0 border-b border-slate-100 px-4 py-3 sm:px-5">
                <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Reservation Details</p>
                <p className="mt-1 text-xs text-slate-500">
                    {selectedSpace ? userFacingSpaceName(selectedSpace) : 'Space'} · {manilaSelectedDayTitle(selectedYmd)}
                </p>
            </div>

            {reservationsForDetailPanel.length === 0 ? (
                <p className="px-4 py-4 text-sm text-slate-600 sm:px-5">
                    {hasBlockingReservationsInView
                        ? 'You do not have a reservation for this date.'
                        : 'No reservations for this date.'}
                </p>
            ) : (
                <section className="min-h-0 flex-1 space-y-3 overflow-y-auto overflow-x-hidden px-4 py-3 sm:px-5 [scrollbar-width:thin]">
                    {reservationsForDetailPanel.map((r) => {
                        const idLabel = r.reservation_number ? String(r.reservation_number) : `#${r.id}`;
                        if (!r.details_revealed) {
                            return (
                                <div
                                    key={`${r.id}-${r.start_at}-${r.end_at}-${r.space_name || ''}`}
                                    className="rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2.5 shadow-sm"
                                >
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Reservation ID
                                    </p>
                                    <p className="mt-1 text-sm font-semibold tabular-nums text-slate-900">{idLabel}</p>
                                </div>
                            );
                        }
                        const title = (r.title || '').trim() || '—';
                        const desc = (r.description || '').trim();
                        const roomName = r.space_name ? String(r.space_name).trim() : '';
                        return (
                            <div
                                key={`${r.id}-${r.start_at}-${r.end_at}-${roomName}`}
                                className="rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2.5 shadow-sm"
                            >
                                <p className="text-xs font-semibold text-xu-primary">
                                    {manilaTimeRangeLabel(r.start_at, r.end_at)}
                                </p>
                                <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Reservation ID
                                </p>
                                <p className="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{idLabel}</p>
                                <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Reservation Title
                                </p>
                                <p className="mt-0.5 text-sm font-semibold text-slate-900">{title}</p>
                                {selectedIsConfabPool && roomName ? (
                                    <p className="mt-1 text-xs text-slate-600">
                                        <span className="font-medium text-slate-700">Library space:</span> {roomName}
                                    </p>
                                ) : null}
                                {!selectedIsConfabPool && selectedSpace ? (
                                    <p className="mt-1 text-xs text-slate-600">
                                        <span className="font-medium text-slate-700">Library space:</span>{' '}
                                        {userFacingSpaceName(selectedSpace)}
                                    </p>
                                ) : null}
                                {desc ? (
                                    <>
                                        <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Reservation Description
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-700 whitespace-pre-wrap leading-relaxed">
                                            {desc}
                                        </p>
                                    </>
                                ) : null}
                            </div>
                        );
                    })}
                </section>
            )}
        </article>
    );

    return (
        <div id={embedded ? 'book-a-space' : undefined} className={shellClass}>
            <div
                className={
                    readOnly && embedded
                        ? 'mx-auto w-full min-w-0 max-w-none'
                        : uDashLayout
                          ? 'mx-auto min-w-0 w-full max-w-[104rem] px-2 sm:px-4'
                          : 'mx-auto min-w-0 max-w-7xl'
                }
            >
                {spacesLoadError && (
                    <p className="text-sm text-red-700 bg-red-50/90 border border-red-100 rounded-md px-3 py-2 m-4 mb-0">
                        Could not load the room list. Refresh the page or try again later.
                    </p>
                )}

                {!spacesLoadError && carouselSpaces.length > 0 && (
                    <div className="px-4 pt-4 sm:px-6 sm:pt-6">
                        <SpaceShowcaseCarousel
                            className="mb-4 sm:mb-6"
                            spaces={carouselSpaces}
                            onSpaceSelect={
                                readOnly
                                    ? undefined
                                    : (s) => {
                                        if (s?.type === 'confab' && !s?.is_confab_pool) {
                                            const pool = spaces.find((x) => x.is_confab_pool);
                                            if (pool) {
                                                setSelectedSpaceId(String(pool.id));
                                                return;
                                            }
                                        }
                                        setSelectedSpaceId(String(s.id));
                                    }
                            }
                        />
                    </div>
                )}

                <div className="border-b border-slate-200/90 bg-gradient-to-r from-xu-primary/[0.07] via-white to-xu-page/80 px-4 py-4 sm:px-6 sm:py-4">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between xl:items-center">
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase tracking-wider text-xu-secondary">Schedule board</p>
                            <HeadingTag className="mt-0.5 font-serif text-xl font-semibold text-xu-primary tracking-tight">
                                Library Venue Schedule
                            </HeadingTag>
                            <p className="mt-1 text-xs text-slate-600 max-w-xl">
                                {readOnly ? (
                                    <>
                                        Browse available times (read-only). Log in to reserve. Times are{' '}
                                    </>
                                ) : (
                                    <>
                                        Choose a space, pick a day, then use the timeline to book a free slot on the half-hour (:00 / :30). Times are{' '}
                                    </>
                                )}
                                <span className="font-medium text-xu-primary">{BOOKING_TIMEZONE}</span>.
                            </p>
                            {readOnly && (
                                <p className="mt-2 inline-flex items-center gap-2 rounded-lg border border-xu-secondary/25 bg-xu-primary/[0.06] px-3 py-2 text-xs font-semibold text-xu-primary">
                                    Viewing only. Log in to reserve a space.
                                </p>
                            )}
                        </div>
                        <div className="flex w-full min-w-0 shrink-0 flex-col gap-2 sm:flex-row sm:items-center sm:gap-3 lg:max-w-md xl:max-w-none xl:w-auto">
                            {readOnly ? (
                                <p className="max-w-md text-xs font-medium leading-snug text-slate-600 sm:text-sm">
                                    Pick a space and day to see half-hour availability. Approved reservations show as{' '}
                                    <span className="text-xu-primary">Reserved</span>; open slots show as{' '}
                                    <span className="text-emerald-800">Available</span>. Log in to book.
                                </p>
                            ) : (
                            <label className="flex min-w-0 flex-col gap-1 text-xs font-medium text-slate-600 sm:max-w-md md:min-w-[12rem]">
                                <span className="text-xu-primary">Library space</span>
                                <select
                                    value={selectedSpaceId}
                                    onChange={(e) => setSelectedSpaceId(e.target.value)}
                                    className="w-full truncate rounded-lg border border-slate-200 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm focus:border-xu-secondary focus:outline-none focus:ring-2 focus:ring-xu-secondary/25"
                                >
                                    <option value="">Select a room…</option>
                                    {bookableSpaces.map((s) => {
                                        const r = getSpaceRestrictionLabel(s);
                                        const label = userFacingSpaceName(s);
                                        return (
                                            <option key={s.id} value={s.id}>
                                                {r ? `${label} (${r})` : label}
                                            </option>
                                        );
                                    })}
                                </select>
                            </label>
                            )}
                            {!readOnly && restrictionLabel && (
                                <span className="self-start text-xs font-medium text-amber-900 bg-amber-50 border border-amber-200/90 rounded-lg px-2.5 py-1.5">
                                    {restrictionLabel}
                                </span>
                            )}
                        </div>
                    </div>
                    {spacesWithColors.length > 0 && !uDashLayout && (
                        <div className="mt-3 flex flex-col gap-2">
                            <div className="flex items-center gap-3">
                                    <span className="text-xs font-bold uppercase tracking-wide text-slate-500">Overview legend</span>
                                {overviewLoading && (
                                    <span className="text-xs font-medium text-slate-500">Loading overview…</span>
                                )}
                            </div>
                            <div className="flex min-w-0 items-center gap-2">
                                <div className="flex w-full min-w-0 flex-wrap gap-2 overflow-x-auto pb-0.5 [scrollbar-width:thin]">
                                {spacesWithColors.map((s) => (
                                    <span
                                        key={s.id}
                                        className={`inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700 shadow-sm ring-1 ${s.__color.ring}`}
                                        title={legendPillLabel(s, adminSchedule, readOnly)}
                                    >
                                        <span className={`h-2.5 w-2.5 rounded-full ${s.__color.bg}`} aria-hidden="true" />
                                        <span className="max-w-[14rem] truncate sm:max-w-none">
                                            {legendPillLabel(s, adminSchedule, readOnly)}
                                        </span>
                                    </span>
                                ))}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                <div className="bg-white">
                    <div
                        className={`grid min-w-0 grid-cols-1 ${
                            userDashThreeCol
                                ? 'lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)] lg:items-stretch lg:gap-5 xl:gap-6'
                                : 'lg:grid-cols-[minmax(0,min(100%,26rem))_minmax(0,1fr)] lg:gap-6'
                        }`}
                    >
                        <div
                            className={`min-w-0 border-b border-slate-200/80 lg:border-b-0 lg:pr-6 ${uDashLayout ? 'p-5 sm:p-6 lg:p-7' : 'p-4 sm:p-5'}`}
                        >
                            <div
                                className={`rounded-xl border border-slate-200/90 bg-slate-50/70 shadow-inner ${uDashLayout ? 'p-5 sm:p-6 lg:p-7' : 'p-4 sm:p-5'}`}
                            >
                                <div className="mb-4 flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        onClick={goPrevMonth}
                                        className="rounded-md p-1.5 text-slate-500 hover:bg-white hover:text-xu-primary hover:shadow-sm"
                                        aria-label="Previous month"
                                    >
                                        <span className="text-lg leading-none">‹</span>
                                    </button>
                                    <span
                                        className={`text-center font-semibold text-xu-primary font-serif ${uDashLayout ? 'text-lg sm:text-xl' : 'text-base'}`}
                                    >
                                        {manilaMonthYearLabel(viewYear, viewMonth)}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={goNextMonth}
                                        disabled={nextMonthDisabled}
                                        className={[
                                            'rounded-md p-1.5',
                                            nextMonthDisabled
                                                ? 'cursor-not-allowed text-slate-300'
                                                : 'text-slate-500 hover:bg-white hover:text-xu-primary hover:shadow-sm',
                                        ].join(' ')}
                                        aria-label="Next month"
                                        aria-disabled={nextMonthDisabled}
                                    >
                                        <span className="text-lg leading-none">›</span>
                                    </button>
                                </div>
                                <div
                                    className={`grid grid-cols-7 text-center ${uDashLayout ? 'gap-x-2 gap-y-3 sm:gap-x-2.5 sm:gap-y-3.5' : 'gap-x-1.5 gap-y-2.5'}`}
                                >
                                    {WEEKDAYS.map((w) => (
                                        <div key={w} className="text-xs font-bold uppercase tracking-wide text-xu-secondary pb-1.5">
                                            {w.slice(0, 1)}
                                        </div>
                                    ))}
                                    {cells.map((cell, idx) => {
                                        const isSelected = cell.ymd === selectedYmd;
                                        const isTodayCell = cell.ymd === todayYmd;
                                        const isPast = isPastDay(cell.ymd);
                                        const beyondMax = isYmdAfterMaxBooking(cell.ymd, maxBookingDate);
                                        const scheduleDayMuted = isPast || beyondMax;
                                        const holiday = holidayForYmd(holidays, cell.ymd);
                                        const full = isFullyBooked(cell.ymd);
                                        const spaceIds = Array.isArray(overviewByDate?.[cell.ymd]) ? overviewByDate[cell.ymd] : [];
                                        const overviewRows = overviewSpaceRows(spaceIds, spaces, readOnly, adminSchedule);
                                        const overviewNameList = overviewRows.map((s) => s.name).join(', ');
                                        const overviewTooltip =
                                            overviewRows.length > 0
                                                ? `Spaces with reservations: ${overviewNameList}`
                                                : '';
                                        const showNamedOverview = cell.inMonth && overviewRows.length > 0;
                                        const namedPreview = overviewRows.slice(0, 2);
                                        const namedMore = overviewRows.length > 2 ? overviewRows.length - 2 : 0;
                                        return (
                                            <div key={idx} className="flex items-center justify-center py-0.5">
                                                {scheduleDayMuted ? (
                                                    <div
                                                        title={
                                                            beyondMax
                                                                ? 'Beyond the maximum bookable date'
                                                                : overviewTooltip
                                                                  ? `Past date is not reservable. ${overviewTooltip}`
                                                                  : 'Past date is not reservable'
                                                        }
                                                        className={[
                                                            `flex ${calCellBox} cursor-not-allowed flex-col items-center justify-center rounded-xl border border-slate-200/80 bg-slate-100/70 text-sm font-semibold tabular-nums leading-none text-slate-500`,
                                                            !cell.inMonth && 'opacity-40',
                                                            isSelected &&
                                                                'border-xu-primary bg-xu-primary/15 text-xu-primary shadow-inner ring-[3px] ring-xu-gold/55 ring-offset-2 ring-offset-slate-50',
                                                            isTodayCell && !isSelected && cell.inMonth && 'ring-2 ring-amber-300/60',
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' ')}
                                                        aria-label={`${cell.dayNum} past date`}
                                                    >
                                                        <span>{cell.dayNum}</span>
                                                    </div>
                                                ) : holiday ? (
                                                    <div
                                                        title={`Holiday: ${holiday.name}`}
                                                        className={[
                                                            `flex ${calCellBox} cursor-not-allowed flex-col items-center justify-center rounded-xl border border-rose-200/80 bg-rose-50 text-sm font-semibold tabular-nums leading-none text-rose-700`,
                                                            !cell.inMonth && 'opacity-40',
                                                            isSelected &&
                                                                'border-rose-400 bg-rose-100 text-rose-800 shadow-inner ring-[3px] ring-rose-200/70 ring-offset-2 ring-offset-slate-50',
                                                            isTodayCell && !isSelected && cell.inMonth && 'ring-2 ring-rose-200/70',
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' ')}
                                                        aria-label={`${cell.dayNum} holiday`}
                                                    >
                                                        <span>{cell.dayNum}</span>
                                                        {cell.inMonth && (
                                                            <span className="mt-1 text-[10px] font-bold uppercase tracking-wide text-rose-700">
                                                                Holiday
                                                            </span>
                                                        )}
                                                    </div>
                                                ) : full ? (
                                                    <div
                                                        title={
                                                            overviewTooltip
                                                                ? `No open slots for this room on this day. ${overviewTooltip}`
                                                                : 'No open slots for this room on this day'
                                                        }
                                                        className={[
                                                            `flex ${calCellBox} cursor-not-allowed flex-col items-center justify-center rounded-xl border border-dashed border-slate-300/80 bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,rgba(148,163,184,0.12)_4px,rgba(148,163,184,0.12)_5px)] text-sm font-semibold tabular-nums leading-none text-slate-500`,
                                                            !cell.inMonth && 'opacity-40',
                                                            isSelected &&
                                                                'border-xu-primary bg-xu-primary/15 text-xu-primary ring-[3px] ring-xu-gold/55 ring-offset-2 ring-offset-slate-50',
                                                            isTodayCell && !isSelected && cell.inMonth && 'ring-2 ring-amber-300/60',
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' ')}
                                                        aria-label={`${cell.dayNum} fully booked`}
                                                    >
                                                        <span>{cell.dayNum}</span>
                                                        {cell.inMonth && (
                                                            <span className="mt-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                                                Full
                                                            </span>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() => onPickDate(cell)}
                                                        title={showNamedOverview ? overviewTooltip : undefined}
                                                        aria-label={
                                                            showNamedOverview
                                                                ? `${cell.dayNum}, ${overviewTooltip}`
                                                                : `${cell.dayNum}`
                                                        }
                                                        className={[
                                                            `relative flex ${calPickBtnW} flex-col items-center justify-between rounded-xl px-1 pb-1.5 pt-1 ${uDashLayout ? 'text-lg' : 'text-base'} font-semibold tabular-nums transition`,
                                                            showNamedOverview
                                                                ? uDashLayout
                                                                    ? 'min-h-[4.35rem] sm:min-h-[4.65rem]'
                                                                    : 'min-h-[3.85rem] sm:min-h-[4.1rem]'
                                                                : uDashLayout
                                                                  ? 'min-h-[3.65rem] sm:min-h-[4rem]'
                                                                  : 'min-h-[3.25rem] sm:min-h-[3.6rem]',
                                                            !cell.inMonth && 'text-slate-300',
                                                            cell.inMonth &&
                                                                !isSelected &&
                                                                'border-2 border-slate-200/90 bg-white text-slate-900 shadow-sm hover:border-xu-secondary/50 hover:bg-xu-page/50 hover:shadow',
                                                            isSelected &&
                                                                'z-[1] border-[3px] border-xu-primary bg-xu-primary text-white shadow-lg ring-[3px] ring-xu-gold/60 ring-offset-2 ring-offset-white',
                                                            isTodayCell && !isSelected && cell.inMonth && 'ring-2 ring-xu-secondary/50',
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' ')}
                                                    >
                                                        <span className="leading-none tabular-nums">{cell.dayNum}</span>
                                                        {showNamedOverview && (
                                                            <span className="flex max-w-full flex-wrap items-center justify-center gap-1">
                                                                {namedPreview.map((s) => {
                                                                    const c =
                                                                        adminSchedule && !readOnly
                                                                            ? colorForOperationalSpaceId(s.id, spaces)
                                                                            : colorForSpaceId(s.id, spaces);
                                                                    return (
                                                                        <span
                                                                            key={s.id}
                                                                            className={`max-w-[3rem] truncate rounded-md px-1 py-0.5 text-center text-[9px] font-bold leading-tight text-white shadow-md ring-1 ring-black/15 sm:max-w-[3.35rem] sm:text-[10px] ${c.bg}`}
                                                                            title={s.name}
                                                                        >
                                                                            {abbreviateSpaceName(s.name)}
                                                                        </span>
                                                                    );
                                                                })}
                                                                {namedMore > 0 && (
                                                                    <span
                                                                        className={[
                                                                            'text-[9px] font-bold leading-tight sm:text-[10px]',
                                                                            isSelected ? 'text-white/95' : 'text-slate-600',
                                                                        ].join(' ')}
                                                                        title={overviewNameList}
                                                                    >
                                                                        +{namedMore}
                                                                    </span>
                                                                )}
                                                            </span>
                                                        )}
                                                    </button>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>

                        <section
                            className={`flex min-w-0 flex-col overflow-hidden bg-gradient-to-b from-white to-slate-50/40 ${
                                userDashThreeCol
                                    ? 'min-h-[18rem] border-t border-slate-200/80 lg:min-h-0 lg:max-h-[min(40rem,78vh)] lg:border-t-0 lg:border-l lg:border-slate-200/80'
                                    : 'min-h-[20rem]'
                            }`}
                        >
                            <div className="border-b border-slate-200/80 px-4 py-3 sm:px-5">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        {readOnly ? (
                                            <>
                                                <p
                                                    className="text-xs font-semibold uppercase tracking-wide text-xu-secondary"
                                                    id="booking-selected-space"
                                                >
                                                    Library space
                                                </p>
                                                <p className="font-serif text-lg font-semibold text-xu-primary">
                                                    {selectedSpace ? userFacingSpaceName(selectedSpace) : 'Select a space'}
                                                </p>
                                            </>
                                        ) : (
                                            <>
                                                {selectedSpaceId && selectedSpace && (
                                                    <p
                                                        className="text-xs font-semibold uppercase tracking-wide text-xu-secondary"
                                                        id="booking-selected-space"
                                                    >
                                                        Selected space
                                                    </p>
                                                )}
                                                <p className="font-serif text-lg font-semibold text-xu-primary">
                                                    {selectedSpace ? userFacingSpaceName(selectedSpace) : 'No room selected'}
                                                </p>
                                            </>
                                        )}
                                        <p className="text-sm text-slate-600">{manilaSelectedDayTitle(selectedYmd)}</p>
                                        <p className="mt-0.5 text-xs tabular-nums text-slate-500">{selectedYmd} · {BOOKING_TIMEZONE}</p>
                                        {activeLeadPolicyBlockMessage ? (
                                            <p
                                                className="mt-2 rounded-lg border border-slate-300/80 bg-slate-100/80 px-2.5 py-1.5 text-xs font-medium leading-snug text-slate-700"
                                                role="status"
                                            >
                                                {activeLeadPolicyBlockMessage}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="flex flex-wrap gap-3 text-xs">
                                        <StatusIndicator status="available" label="Available" />
                                        <StatusIndicator
                                            status="unavailable"
                                            label={readOnly ? 'Reserved' : 'Not available'}
                                        />
                                    </div>
                                </div>
                                {readOnly && (
                                    <label className="mt-3 flex w-full min-w-0 flex-col gap-1 text-xs font-medium text-slate-600">
                                        <span className="text-xu-primary">View schedule for</span>
                                        <select
                                            value={selectedSpaceId}
                                            onChange={(e) => setSelectedSpaceId(e.target.value)}
                                            className="w-full max-w-md truncate rounded-lg border border-slate-200 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm focus:border-xu-secondary focus:outline-none focus:ring-2 focus:ring-xu-secondary/25"
                                            aria-labelledby="booking-selected-space"
                                        >
                                            {timelineSpaces.length === 0 ? (
                                                <option value="">Loading spaces…</option>
                                            ) : (
                                                timelineSpaces.map((s) => (
                                                    <option key={s.id} value={s.id}>
                                                        {userFacingSpaceName(s)}
                                                    </option>
                                                ))
                                            )}
                                        </select>
                                    </label>
                                )}
                                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 border-t border-slate-100 pt-2 text-xs text-slate-500">
                                    <span>
                                        <span className="font-medium text-xu-primary">Slots:</span> half-hour grid (:00 / :30)
                                    </span>
                                    {readOnly ? (
                                        <span>
                                            <span className="font-medium text-xu-primary">View:</span> one room at a time — green available, red reserved
                                        </span>
                                    ) : (
                                        selectedSpaceId && (
                                            <span>
                                                <span className="font-medium text-xu-primary">Calendar:</span> dashed{' '}
                                                <span className="font-semibold">Full</span> = no open slots for this room that day
                                            </span>
                                        )
                                    )}
                                </div>
                                {readOnly && loginRequiredNudge && (
                                    <div className="mt-2 rounded-lg border border-xu-secondary/25 bg-xu-primary/[0.06] px-3 py-2 text-xs font-semibold text-xu-primary">
                                        {loginRequiredNudge}
                                    </div>
                                )}
                            </div>

                            {!readOnly && !selectedSpaceId && (
                                <div className="flex flex-1 items-center justify-center px-6 py-12">
                                    <p className="max-w-sm text-center text-sm text-slate-500">
                                        Select a library space above to load the schedule for{' '}
                                        <span className="font-medium text-slate-700">{manilaSelectedDayTitle(selectedYmd)}</span>.
                                    </p>
                                </div>
                            )}

                            {readOnly && loadingSlots && (
                                <div className="flex flex-1 items-center justify-center py-16">
                                    <p className="text-sm font-medium text-slate-500">Loading schedule…</p>
                                </div>
                            )}

                            {readOnly && !loadingSlots && selectedSpaceId && publicScheduleRows.length === 0 && (
                                <div className="flex flex-1 items-center justify-center px-6 py-12">
                                    <p className="max-w-sm text-center text-sm text-slate-500">
                                        No schedule data for this space and date. Refresh the page or try again later.
                                    </p>
                                </div>
                            )}

                            {!readOnly && selectedSpaceId && loadingSlots && (
                                <div className="flex flex-1 items-center justify-center py-16">
                                    <p className="text-sm font-medium text-slate-500">Loading schedule…</p>
                                </div>
                            )}

                            {!readOnly && selectedSpaceId && !loadingSlots && !eligible && (
                                <div className="flex flex-1 items-center justify-center px-6 py-10">
                                    <p className="max-w-md text-center text-sm text-red-700">{getSpaceIneligibilityMessage(selectedSpace)}</p>
                                </div>
                            )}

                            {readOnly && !loadingSlots && selectedSpaceId && visiblePublicReadOnlySlots.length > 0 && (
                                <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                                    {visiblePublicReadOnlySlots.every((s) => !s.available) && (
                                        <div className="mx-4 mt-3 shrink-0 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs font-medium text-amber-950 sm:mx-5">
                                            No open half-hour windows — this room is fully reserved for this date.
                                        </div>
                                    )}
                                    <div
                                        className="mt-2 min-h-0 max-h-[min(28rem,50vh,65dvh)] flex-1 overflow-y-auto overflow-x-auto border-t border-slate-200/80 bg-white [scrollbar-width:thin]"
                                        role="region"
                                        aria-label={`Schedule for ${selectedSpace ? userFacingSpaceName(selectedSpace) : 'room'} on ${selectedYmd}`}
                                    >
                                        <ul className="m-0 min-w-0 list-none divide-y divide-slate-100 p-0">
                                            {visiblePublicReadOnlySlots.map((slot) => {
                                                const label = formatManilaHalfHourSlotLabel(
                                                    slot.hourStart,
                                                    slot.minuteStart,
                                                    slot.hourEnd,
                                                    slot.minuteEnd
                                                );
                                                const rowKey = `pub-${selectedSpaceId}-${selectedYmd}-${slot.hourStart}-${slot.minuteStart}`;
                                                const gutter = formatManilaSlotGutterTimes(slot);

                                                if (slot.available) {
                                                    if (false && schedulingRulesBlockBookings) {
                                                        return (
                                                            <li key={rowKey} className="list-none">
                                                                <div className="grid grid-cols-[4.25rem_1fr] gap-0 sm:grid-cols-[5rem_1fr]">
                                                                    <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-white py-3 pr-2 pl-1 text-right">
                                                                        <span className="text-xs font-bold tabular-nums text-slate-500">{gutter.start}</span>
                                                                        <span className="text-xs tabular-nums text-slate-500">{gutter.end}</span>
                                                                    </div>
                                                                    <div className="p-2 sm:p-2.5">
                                                                        <div
                                                                            title={activeLeadPolicyBlockMessage}
                                                                            className="flex h-full min-h-[3rem] w-full cursor-not-allowed flex-col justify-center gap-1 rounded-lg border border-slate-300/90 bg-slate-100/85 px-3 py-2 text-left opacity-80 shadow-inner"
                                                                            role="group"
                                                                        >
                                                                            <p className="text-sm font-semibold text-slate-700">{label}</p>
                                                                            <p className="text-xs font-medium leading-snug text-slate-600">
                                                                                {activeLeadPolicyBlockMessage}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </li>
                                                        );
                                                    }
                                                    return (
                                                        <li key={rowKey} className="list-none">
                                                            <div className="grid grid-cols-[4.25rem_1fr] gap-0 sm:grid-cols-[5rem_1fr]">
                                                                <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-white py-3 pr-2 pl-1 text-right">
                                                                    <span className="text-xs font-bold tabular-nums text-xu-primary">{gutter.start}</span>
                                                                    <span className="text-xs tabular-nums text-slate-500">{gutter.end}</span>
                                                                </div>
                                                                <div className="p-2 sm:p-2.5">
                                                                    <button
                                                                        type="button"
                                                                        disabled={schedulingRulesBlockBookings}
                                                                        title={
                                                                            schedulingRulesBlockBookings
                                                                                ? activeLeadPolicyBlockMessage
                                                                                : undefined
                                                                        }
                                                                        onClick={() => {
                                                                            if (schedulingRulesBlockBookings) return;
                                                                            setLoginRequiredNudge('Log in first to reserve a slot.');
                                                                        }}
                                                                        aria-label={`Available ${label} — log in to reserve`}
                                                                        className={[
                                                                            'group flex h-full min-h-[3rem] w-full items-center justify-between gap-2 rounded-lg border-2 px-3 py-2 text-left shadow-sm',
                                                                            schedulingRulesBlockBookings
                                                                                ? 'cursor-not-allowed border-slate-200/90 bg-white opacity-90'
                                                                                : 'border-emerald-200/90 bg-emerald-50/50 transition hover:border-emerald-300 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-xu-secondary',
                                                                        ].join(' ')}
                                                                    >
                                                                        <div className="min-w-0 text-left">
                                                                            <p
                                                                                className={`text-sm font-semibold ${schedulingRulesBlockBookings ? 'text-slate-900' : 'text-emerald-900'}`}
                                                                            >
                                                                                {label}
                                                                            </p>
                                                                            <p
                                                                                className={`text-xs ${schedulingRulesBlockBookings ? 'text-slate-600' : 'text-emerald-800/90'}`}
                                                                            >
                                                                                {schedulingRulesBlockBookings
                                                                                    ? 'Available · booking unavailable'
                                                                                    : 'Available · log in to reserve'}
                                                                            </p>
                                                                        </div>
                                                                        <span
                                                                            className={`shrink-0 rounded-md px-2 py-1 text-xs font-bold uppercase tracking-wide ${
                                                                                schedulingRulesBlockBookings
                                                                                    ? 'border border-slate-300 bg-slate-100 text-slate-500'
                                                                                    : 'bg-emerald-600/10 text-emerald-800'
                                                                            }`}
                                                                        >
                                                                            Log in
                                                                        </span>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    );
                                                }

                                                return (
                                                    <li key={rowKey} className="list-none">
                                                        <div className="grid grid-cols-[4.25rem_1fr] gap-0 sm:grid-cols-[5rem_1fr]">
                                                            <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-slate-50 py-3 pr-2 pl-1 text-right">
                                                                <span className="text-xs font-bold tabular-nums text-slate-500">{gutter.start}</span>
                                                                <span className="text-xs tabular-nums text-slate-500">{gutter.end}</span>
                                                            </div>
                                                            <div className="p-2 sm:p-2.5">
                                                                <div
                                                                    title={`Reserved — ${selectedSpace ? userFacingSpaceName(selectedSpace) : 'this room'}`}
                                                                    aria-label={`${label} — reserved`}
                                                                    className="flex min-h-[3rem] items-center justify-between gap-2 rounded-lg border-2 border-red-200/90 bg-red-50/60 px-3 py-2 shadow-inner"
                                                                >
                                                                    <div className="min-w-0">
                                                                        <p className="text-sm font-semibold text-red-950">{label}</p>
                                                                        <p className="text-xs font-medium text-red-900/90">Reserved</p>
                                                                    </div>
                                                                    <span className="shrink-0 rounded-md border border-red-300/80 bg-red-100/90 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-red-900">
                                                                        Reserved
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </div>
                                </div>
                            )}

                            {!readOnly && selectedSpaceId && !loadingSlots && eligible && (
                                <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden border-t border-slate-200/80">
                                    {visibleSlots.every((s) => !s.available) && (
                                        <div className="mx-4 mt-3 shrink-0 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs font-medium text-amber-950 sm:mx-5">
                                            No open slots — every row below is reserved for this room and date.
                                        </div>
                                    )}
                                    {userDashThreeCol ? (
                                        <UserDashboardSlotsPanel
                                            visibleSlots={visibleSlots}
                                            selectedSpaceId={selectedSpaceId}
                                            selectedYmd={selectedYmd}
                                            selectedSpace={selectedSpace}
                                            reservedSlots={reservedSlots}
                                            schedulingRulesBlockBookings={schedulingRulesBlockBookings}
                                            activeLeadPolicyBlockMessage={activeLeadPolicyBlockMessage}
                                        />
                                    ) : (
                                    <div
                                        className="mt-2 min-h-0 max-h-[min(28rem,50vh,65dvh)] flex-1 overflow-y-auto overflow-x-hidden border-t border-slate-200/80 bg-white [scrollbar-width:thin]"
                                        role="region"
                                        aria-label={`Schedule for ${selectedSpace ? userFacingSpaceName(selectedSpace) : 'room'} on ${selectedYmd}`}
                                    >
                                        <div className="grid grid-cols-1 gap-6 p-3 sm:p-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                                            <ul className="m-0 min-w-0 list-none space-y-2 rounded-xl border border-slate-200/80 bg-white p-2 shadow-sm">
                                                {visibleSlots.map((slot) => {
                                                    const label = formatManilaHalfHourSlotLabel(
                                                        slot.hourStart,
                                                        slot.minuteStart,
                                                        slot.hourEnd,
                                                        slot.minuteEnd
                                                    );
                                                    const reserveUrl = `/reserve?space_id=${selectedSpaceId}&date=${selectedYmd}&start_time=${manilaTimeParamFromHour(
                                                        slot.hourStart,
                                                        slot.minuteStart
                                                    )}&end_time=${manilaTimeParamFromHour(slot.hourEnd, slot.minuteEnd)}`;
                                                    const rowKey = `${selectedSpaceId}-${selectedYmd}-${slot.hourStart}-${slot.minuteStart}`;
                                                    const gutter = formatManilaSlotGutterTimes(slot);
                                                    const r = slot.available ? null : reservationForSlot(slot, reservedSlots);

                                                    if (!slot.available) {
                                                        return (
                                                            <li key={rowKey} className="list-none">
                                                                <div className="grid w-full grid-cols-[4.25rem_1fr] gap-0 text-left sm:grid-cols-[5rem_1fr]">
                                                                    <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-slate-50 py-4 pr-2.5 pl-1.5 text-right">
                                                                        <span className="text-xs font-bold tabular-nums text-slate-500">{gutter.start}</span>
                                                                        <span className="text-xs tabular-nums text-slate-500">{gutter.end}</span>
                                                                    </div>
                                                                    <div className="p-3 sm:p-3.5">
                                                                        <div className="flex min-h-[3.5rem] items-center justify-between gap-3 rounded-xl border border-slate-300/90 bg-slate-100/90 px-4 py-3 shadow-inner">
                                                                            <div className="min-w-0">
                                                                                <p className="text-sm font-semibold text-slate-700">{label}</p>
                                                                                <p className="text-xs font-medium text-slate-600">
                                                                                    {r?.title ? 'Occupied' : 'Occupied'}
                                                                                </p>
                                                                            </div>
                                                                            <span className="shrink-0 rounded-md border border-slate-400/50 bg-slate-200/80 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-700">
                                                                                Occupied
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </li>
                                                        );
                                                    }

                                                    return (
                                                        <li key={rowKey} className="list-none">
                                                            <div className="grid grid-cols-[4.25rem_1fr] gap-0 sm:grid-cols-[5rem_1fr]">
                                                                <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-white py-4 pr-2.5 pl-1.5 text-right">
                                                                    <span className="text-xs font-bold tabular-nums text-xu-primary">{gutter.start}</span>
                                                                    <span className="text-xs tabular-nums text-slate-500">{gutter.end}</span>
                                                                </div>
                                                                <div className="p-3 sm:p-3.5">
                                                                    <AvailableBookingSlotCard
                                                                        label={label}
                                                                        reserveUrl={reserveUrl}
                                                                        spaceLabel={
                                                                            selectedSpace ? userFacingSpaceName(selectedSpace) : 'this room'
                                                                        }
                                                                        bookDisabled={schedulingRulesBlockBookings}
                                                                        disabledTitle={activeLeadPolicyBlockMessage}
                                                                    />
                                                                </div>
                                                            </div>
                                                        </li>
                                                    );
                                                })}
                                            </ul>

                                            <aside className={`min-w-0 ${uDashLayout ? 'lg:min-w-[26rem]' : 'lg:min-w-[22rem]'}`}>
                                                <div className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm">
                                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                        Reservation Details
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {selectedSpace ? userFacingSpaceName(selectedSpace) : 'Space'} · {manilaSelectedDayTitle(selectedYmd)}
                                                    </p>

                                                    {reservationsForDetailPanel.length === 0 ? (
                                                        <p className="mt-3 text-sm text-slate-600">
                                                            {hasBlockingReservationsInView
                                                                ? 'You do not have a reservation for this date.'
                                                                : 'No reservations for this date.'}
                                                        </p>
                                                    ) : (
                                                        <div className="mt-3 max-h-[18rem] space-y-2 overflow-y-auto pr-1 [scrollbar-width:thin]">
                                                            {reservationsForDetailPanel.map((r) => {
                                                                const idLabel = r.reservation_number
                                                                    ? String(r.reservation_number)
                                                                    : `#${r.id}`;
                                                                if (!r.details_revealed) {
                                                                    return (
                                                                        <div
                                                                            key={`${r.id}-${r.start_at}-${r.end_at}-${r.space_name || ''}`}
                                                                            className="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm"
                                                                        >
                                                                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                                                Reservation ID
                                                                            </p>
                                                                            <p className="mt-1 text-sm font-semibold tabular-nums text-slate-900">
                                                                                {idLabel}
                                                                            </p>
                                                                        </div>
                                                                    );
                                                                }
                                                                const title = r.title || 'Reserved';
                                                                const desc = (r.description || '').trim();
                                                                const roomName = r.space_name ? String(r.space_name).trim() : '';
                                                                return (
                                                                    <div
                                                                        key={`${r.id}-${r.start_at}-${r.end_at}-${roomName}`}
                                                                        className="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm"
                                                                    >
                                                                        <p className="text-xs font-semibold text-xu-primary">
                                                                            {manilaTimeRangeLabel(r.start_at, r.end_at)}
                                                                        </p>
                                                                        <p className="mt-1 text-xs text-slate-600">
                                                                            <span className="font-medium text-slate-700">
                                                                                Reservation ID:
                                                                            </span>{' '}
                                                                            <span className="font-semibold tabular-nums text-slate-900">
                                                                                {idLabel}
                                                                            </span>
                                                                        </p>
                                                                        <p className="mt-0.5 text-sm font-semibold text-slate-900">
                                                                            {title}
                                                                        </p>
                                                                        {selectedIsConfabPool && roomName ? (
                                                                            <p className="text-xs text-slate-600">
                                                                                <span className="font-medium text-slate-700">
                                                                                    Library space:
                                                                                </span>{' '}
                                                                                {roomName}
                                                                            </p>
                                                                        ) : null}
                                                                        {!selectedIsConfabPool && selectedSpace ? (
                                                                            <p className="text-xs text-slate-600">
                                                                                <span className="font-medium text-slate-700">
                                                                                    Library space:
                                                                                </span>{' '}
                                                                                {userFacingSpaceName(selectedSpace)}
                                                                            </p>
                                                                        ) : null}
                                                                        {desc ? (
                                                                            <p className="mt-1 text-xs text-slate-700 whitespace-pre-wrap">
                                                                                <span className="font-medium text-slate-700">
                                                                                    Description:
                                                                                </span>{' '}
                                                                                {desc}
                                                                            </p>
                                                                        ) : null}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    )}
                                                </div>
                                            </aside>
                                        </div>
                                    </div>
                                    )}
                                </div>
                            )}
                        </section>

                        {userDashThreeCol && (
                            <aside
                                className="flex min-h-[14rem] min-w-0 flex-col overflow-hidden border-t border-slate-200/80 px-2 pb-3 pt-3 sm:px-3 lg:min-h-0 lg:max-h-[min(40rem,78vh)] lg:border-t-0 lg:border-l lg:border-slate-200/80 lg:pl-4 lg:pr-2 xl:pl-5"
                                aria-label="Reservation details"
                            >
                                {selectedSpaceId ? (
                                    reservationDetailsPanel
                                ) : (
                                    <p className="flex min-h-[10rem] flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white/80 px-4 py-6 text-center m-0">
                                        <p className="text-sm text-slate-600">
                                            Select a library space to view reservation details for this date.
                                        </p>
                                    </p>
                                )}
                            </aside>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

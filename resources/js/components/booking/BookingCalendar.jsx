import { useMemo, useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api';
import { useAuth } from '../../contexts/AuthContext';
import { isAdminScheduleViewer } from '../../utils/isAdminScheduleViewer';
import { userFacingSpaceName } from '../../utils/userFacingSpaceName';
import { getSpaceIneligibilityMessage, getSpaceRestrictionLabel, isUserEligibleForSpace } from '../../utils/spaceEligibility';
import {
    buildManilaHalfHourSlots,
    buildManilaMonthCells,
    buildManilaWeekStripContaining,
    formatManilaHalfHourSlotLabel,
    formatManilaSlotGutterTimes,
    manilaMonthYearLabel,
    manilaSelectedDayTitle,
    manilaShortDayLabel,
    manilaTimeParamFromHour,
    manilaTodayParts,
    manilaYmdFromParts,
    shiftManilaYmd,
} from '../../utils/manilaTime';
import { unwrapData } from '../../utils/apiEnvelope';
import { BOOKING_TIMEZONE } from '../../utils/timeDisplay';
import { colorForSpaceId } from '../../utils/spaceColors';

const DAY_START_HOUR = 9;
const DAY_END_HOUR = 18;

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** Compact label for tiny calendar chips; full name stays in title/tooltip. */
function abbreviateSpaceName(name) {
    if (!name || typeof name !== 'string') return '?';
    const t = name.trim();
    const medical = t.match(/^Medical\s+Confab\s+(\d+)/i);
    if (medical) return `M${medical[1]}`;
    const confab = t.match(/^Confab\s+(\d+)/i);
    if (confab) return `C${confab[1]}`;
    if (t.length <= 6) return t;
    return `${t.slice(0, 5)}…`;
}

/**
 * @param {Array<string|number>} spaceIds
 * @param {Array<{ id: number|string, name: string }>} spaces
 */
function overviewSpaceRows(spaceIds, spaces) {
    if (!Array.isArray(spaceIds) || spaceIds.length === 0) return [];
    const out = [];
    const seen = new Set();
    for (const id of spaceIds) {
        const s = spaces.find((x) => String(x.id) === String(id));
        const row = s ? { ...s, name: userFacingSpaceName(s) } : { id, name: `Space ${id}` };
        if (seen.has(String(row.id))) continue;
        seen.add(String(row.id));
        out.push(row);
    }
    return out;
}

function initialManilaCalendarState() {
    const t = manilaTodayParts();
    return {
        selectedYmd: manilaYmdFromParts(t.year, t.monthIndex0, t.day),
        viewYear: t.year,
        viewMonth: t.monthIndex0,
    };
}

export default function BookingCalendar({
    user,
    spaces,
    spacesLoadError,
    embedded = false,
    readOnly = false,
    headingLevel = 3,
}) {
    const { hasPermission } = useAuth();
    const adminSchedule = isAdminScheduleViewer(user, hasPermission);
    const bookableSpaces = useMemo(() => {
        if (adminSchedule) return spaces;
        return spaces.filter((s) => !(s?.type === 'confab' && !s?.is_confab_pool));
    }, [spaces, adminSchedule]);

    const [cal, setCal] = useState(initialManilaCalendarState);
    const { selectedYmd, viewYear, viewMonth } = cal;
    const [selectedSpaceId, setSelectedSpaceId] = useState('');

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

    const todayYmd = useMemo(() => {
        const t = manilaTodayParts();
        return manilaYmdFromParts(t.year, t.monthIndex0, t.day);
    }, []);

    useEffect(() => {
        if (bookableSpaces.length === 0) {
            setSelectedSpaceId('');
            return;
        }
        const exists = bookableSpaces.some((s) => String(s.id) === String(selectedSpaceId));
        if (!selectedSpaceId || !exists) {
            setSelectedSpaceId(String(bookableSpaces[0].id));
        }
    }, [bookableSpaces, selectedSpaceId]);

    const isPastDay = useCallback((ymd) => String(ymd) < String(todayYmd), [todayYmd]);

    const weekStripYmds = useMemo(() => buildManilaWeekStripContaining(selectedYmd), [selectedYmd]);

    const [reservedSlots, setReservedSlots] = useState([]);
    const [loadingSlots, setLoadingSlots] = useState(false);
    const [loginRequiredNudge, setLoginRequiredNudge] = useState('');
    const [fullyBookedYmd, setFullyBookedYmd] = useState({});
    const [summaryLoading, setSummaryLoading] = useState(false);
    const [overviewByDate, setOverviewByDate] = useState({});
    const [overviewLoading, setOverviewLoading] = useState(false);

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
        if (!selectedSpaceId || !cellYmdBounds.min || !cellYmdBounds.max) {
            setFullyBookedYmd({});
            setSummaryLoading(false);
            return;
        }
        let cancelled = false;
        setSummaryLoading(true);
        api.get(readOnly ? '/public/availability/month-summary' : '/availability/month-summary', {
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
        if (!selectedSpaceId || !selectedYmd) {
            setReservedSlots([]);
            return;
        }
        setLoadingSlots(true);
        setLoginRequiredNudge('');

        const req = readOnly
            ? api.get('/public/schedule-overview', { params: { date: selectedYmd, space_id: selectedSpaceId } })
            : api.get('/availability', { params: { date: selectedYmd, space_id: selectedSpaceId } });

        req.then(({ data }) => {
            if (readOnly) {
                const payload = unwrapData(data);
                const rows = Array.isArray(payload?.spaces) ? payload.spaces : [];
                const row = rows.find((r) => String(r?.space?.id) === String(selectedSpaceId));
                setReservedSlots(Array.isArray(row?.occupied_slots) ? row.occupied_slots : []);
                return;
            }

            const rows = unwrapData(data);
            const row = Array.isArray(rows)
                ? rows.find((r) => String(r.space?.id) === String(selectedSpaceId))
                : null;
            setReservedSlots(row?.reserved_slots || []);
        })
            .catch(() => setReservedSlots([]))
            .finally(() => setLoadingSlots(false));
    }, [selectedYmd, selectedSpaceId, readOnly]);

    const selectedSpace = bookableSpaces.find((s) => String(s.id) === String(selectedSpaceId));
    const eligible = readOnly ? true : (selectedSpace ? isUserEligibleForSpace(user, selectedSpace) : false);
    const restrictionLabel = selectedSpace ? getSpaceRestrictionLabel(selectedSpace) : '';

    const slots = useMemo(
        () => buildManilaHalfHourSlots(selectedYmd, reservedSlots, DAY_START_HOUR, DAY_END_HOUR),
        [selectedYmd, reservedSlots]
    );
    const spacesWithColors = useMemo(() => {
        return bookableSpaces.map((s) => ({ ...s, __color: colorForSpaceId(s.id, spaces) }));
    }, [bookableSpaces, spaces]);

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
            if (c.viewMonth === 11) {
                return { ...c, viewYear: c.viewYear + 1, viewMonth: 0 };
            }
            return { ...c, viewMonth: c.viewMonth + 1 };
        });
    }, []);

    const onPickDate = (cell) => {
        if (isPastDay(cell.ymd)) return;
        if (isFullyBooked(cell.ymd)) return;
        const [y, m] = cell.ymd.split('-').map(Number);
        setCal({ selectedYmd: cell.ymd, viewYear: y, viewMonth: m - 1 });
    };

    const onPickWeekDay = (ymd) => {
        if (isPastDay(ymd)) return;
        if (isFullyBooked(ymd)) return;
        const [y, m] = ymd.split('-').map(Number);
        setCal({ selectedYmd: ymd, viewYear: y, viewMonth: m - 1 });
    };

    const goPrevWeek = useCallback(() => {
        const next = shiftManilaYmd(selectedYmd, -7);
        const [y, m] = next.split('-').map(Number);
        setCal({ selectedYmd: next, viewYear: y, viewMonth: m - 1 });
    }, [selectedYmd]);

    const goNextWeek = useCallback(() => {
        const next = shiftManilaYmd(selectedYmd, 7);
        const [y, m] = next.split('-').map(Number);
        setCal({ selectedYmd: next, viewYear: y, viewMonth: m - 1 });
    }, [selectedYmd]);

    const shellClass = embedded
        ? 'bg-white min-w-0 rounded-2xl border border-slate-200/90 shadow-lg shadow-slate-300/25 overflow-hidden ring-1 ring-slate-200/70'
        : 'bg-xu-page min-w-0 -mx-4 px-4 py-8 sm:mx-0 sm:rounded-2xl sm:px-8 border border-slate-200/60 sm:border-0';

    const HeadingTag = `h${headingLevel}`;

    return (
        <div id={embedded ? 'book-a-space' : undefined} className={shellClass}>
            <div
                className={
                    readOnly && embedded ? 'mx-auto w-full min-w-0 max-w-none' : 'mx-auto min-w-0 max-w-6xl'
                }
            >
                {spacesLoadError && (
                    <p className="text-sm text-red-700 bg-red-50/90 border border-red-100 rounded-md px-3 py-2 m-4 mb-0">
                        Could not load the room list. Refresh the page or try again later.
                    </p>
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
                            {!readOnly && restrictionLabel && (
                                <span className="self-start text-xs font-medium text-amber-900 bg-amber-50 border border-amber-200/90 rounded-lg px-2.5 py-1.5">
                                    {restrictionLabel}
                                </span>
                            )}
                        </div>
                    </div>
                    {bookableSpaces.length > 0 && (
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
                                        title={userFacingSpaceName(s)}
                                    >
                                        <span className={`h-2.5 w-2.5 rounded-full ${s.__color.bg}`} aria-hidden="true" />
                                        <span className="max-w-[14rem] truncate sm:max-w-none">{userFacingSpaceName(s)}</span>
                                    </span>
                                ))}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                <div className="flex items-stretch gap-1 border-b border-slate-200/80 bg-slate-50/90 px-2 py-2 sm:px-4">
                    <button
                        type="button"
                        onClick={goPrevWeek}
                        className="shrink-0 rounded-lg border border-slate-200/90 bg-white px-2 py-2 text-slate-500 hover:border-xu-secondary/40 hover:text-xu-primary shadow-sm transition"
                        aria-label="Previous week"
                    >
                        <span className="text-lg leading-none">‹</span>
                    </button>
                    <div className="flex min-w-0 flex-1 gap-1 overflow-x-auto pb-0.5 [scrollbar-width:thin]">
                        {weekStripYmds.map((ymd) => {
                            const selected = ymd === selectedYmd;
                            const isToday = ymd === todayYmd;
                            const isPast = isPastDay(ymd);
                            const full = isFullyBooked(ymd);
                            const stripIds = Array.isArray(overviewByDate?.[ymd]) ? overviewByDate[ymd] : [];
                            const stripRows = overviewSpaceRows(stripIds, spaces);
                            const stripOverviewTip =
                                stripRows.length > 0
                                    ? `Spaces with reservations: ${stripRows.map((s) => s.name).join(', ')}`
                                    : '';
                            if (isPast) {
                                return (
                                    <div
                                        key={ymd}
                                        role="presentation"
                                        title={
                                            stripOverviewTip
                                                ? `Past date is not reservable. ${stripOverviewTip}`
                                                : 'Past date is not reservable'
                                        }
                                        className={[
                                            'min-w-[3.25rem] shrink-0 cursor-not-allowed rounded-lg border border-slate-200/90 bg-slate-100/70 px-2 py-2 text-center text-xs font-medium text-slate-500 sm:min-w-[3.5rem] sm:px-2.5',
                                            selected &&
                                                'border-xu-primary/50 bg-xu-primary/10 text-xu-primary ring-2 ring-xu-gold/30 ring-offset-1 ring-offset-slate-50',
                                        ]
                                            .filter(Boolean)
                                            .join(' ')}
                                    >
                                        <span className="block leading-tight opacity-70">{manilaShortDayLabel(ymd).split(' ')[0]}</span>
                                        <span className="mt-0.5 block text-sm font-semibold tabular-nums leading-none">{ymd.split('-')[2]}</span>
                                    </div>
                                );
                            }
                            if (full) {
                                return (
                                    <div
                                        key={ymd}
                                        role="presentation"
                                        title={
                                            stripOverviewTip
                                                ? `No open slots for this room on this day. ${stripOverviewTip}`
                                                : 'No open slots for this room on this day'
                                        }
                                        className={[
                                            'min-w-[3.25rem] shrink-0 cursor-not-allowed rounded-lg border border-dashed border-slate-300/90 bg-slate-100/90 px-2 py-2 text-center text-xs font-medium text-slate-500 sm:min-w-[3.5rem] sm:px-2.5',
                                            selected &&
                                                'border-xu-primary/50 bg-xu-primary/15 text-xu-primary ring-2 ring-xu-gold/40 ring-offset-1 ring-offset-slate-50',
                                        ]
                                            .filter(Boolean)
                                            .join(' ')}
                                    >
                                        <span className="block leading-tight opacity-80">{manilaShortDayLabel(ymd).split(' ')[0]}</span>
                                        <span className="mt-0.5 block text-sm font-semibold tabular-nums leading-none">{ymd.split('-')[2]}</span>
                                        <span className="mt-0.5 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Full</span>
                                    </div>
                                );
                            }
                            return (
                                <button
                                    key={ymd}
                                    type="button"
                                    onClick={() => onPickWeekDay(ymd)}
                                    title={stripOverviewTip || undefined}
                                    className={[
                                        'min-w-[3.25rem] shrink-0 rounded-lg border px-2 py-2 text-center text-xs font-medium transition sm:min-w-[3.5rem] sm:px-2.5',
                                        selected
                                            ? 'border-xu-primary bg-xu-primary text-white shadow-md ring-2 ring-xu-gold/50 ring-offset-1 ring-offset-slate-50'
                                            : isToday
                                              ? 'border-xu-secondary/50 bg-white text-xu-primary shadow-sm hover:border-xu-secondary'
                                              : 'border-slate-200/90 bg-white text-slate-700 hover:border-xu-secondary/35 hover:bg-xu-page/60',
                                    ].join(' ')}
                                >
                                    <span className="block leading-tight opacity-90">{manilaShortDayLabel(ymd).split(' ')[0]}</span>
                                    <span className="mt-0.5 block text-sm font-semibold tabular-nums leading-none">{ymd.split('-')[2]}</span>
                                </button>
                            );
                        })}
                    </div>
                    <button
                        type="button"
                        onClick={goNextWeek}
                        className="shrink-0 rounded-lg border border-slate-200/90 bg-white px-2 py-2 text-slate-500 hover:border-xu-secondary/40 hover:text-xu-primary shadow-sm transition"
                        aria-label="Next week"
                    >
                        <span className="text-lg leading-none">›</span>
                    </button>
                </div>

                <div className="bg-white">
                    <div className="grid min-w-0 grid-cols-1 lg:grid-cols-[minmax(0,min(100%,34rem))_minmax(0,1fr)] lg:divide-x lg:divide-slate-200/80">
                        <div className="min-w-0 border-b border-slate-200/80 p-4 sm:p-5 lg:border-b-0 lg:pr-6">
                            <div className="rounded-xl border border-slate-200/90 bg-slate-50/70 p-4 shadow-inner sm:p-5">
                                <div className="mb-4 flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        onClick={goPrevMonth}
                                        className="rounded-md p-1.5 text-slate-500 hover:bg-white hover:text-xu-primary hover:shadow-sm"
                                        aria-label="Previous month"
                                    >
                                        <span className="text-lg leading-none">‹</span>
                                    </button>
                                    <span className="text-center text-base font-semibold text-xu-primary font-serif">
                                        {manilaMonthYearLabel(viewYear, viewMonth)}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={goNextMonth}
                                        className="rounded-md p-1.5 text-slate-500 hover:bg-white hover:text-xu-primary hover:shadow-sm"
                                        aria-label="Next month"
                                    >
                                        <span className="text-lg leading-none">›</span>
                                    </button>
                                </div>
                                <div className="grid grid-cols-7 gap-x-1.5 gap-y-2.5 text-center">
                                    {WEEKDAYS.map((w) => (
                                        <div key={w} className="text-xs font-bold uppercase tracking-wide text-xu-secondary pb-1.5">
                                            {w.slice(0, 1)}
                                        </div>
                                    ))}
                                    {cells.map((cell, idx) => {
                                        const isSelected = cell.ymd === selectedYmd;
                                        const isTodayCell = cell.ymd === todayYmd;
                                        const isPast = isPastDay(cell.ymd);
                                        const full = isFullyBooked(cell.ymd);
                                        const spaceIds = Array.isArray(overviewByDate?.[cell.ymd]) ? overviewByDate[cell.ymd] : [];
                                        const overviewRows = overviewSpaceRows(spaceIds, spaces);
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
                                                {isPast ? (
                                                    <div
                                                        title={
                                                            overviewTooltip
                                                                ? `Past date is not reservable. ${overviewTooltip}`
                                                                : 'Past date is not reservable'
                                                        }
                                                        className={[
                                                            'flex h-[3.25rem] w-[2.875rem] cursor-not-allowed flex-col items-center justify-center rounded-xl border border-slate-200/80 bg-slate-100/70 text-sm font-semibold tabular-nums leading-none text-slate-500 sm:h-[3.6rem] sm:w-[3.25rem]',
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
                                                ) : full ? (
                                                    <div
                                                        title={
                                                            overviewTooltip
                                                                ? `No open slots for this room on this day. ${overviewTooltip}`
                                                                : 'No open slots for this room on this day'
                                                        }
                                                        className={[
                                                            'flex h-[3.25rem] w-[2.875rem] cursor-not-allowed flex-col items-center justify-center rounded-xl border border-dashed border-slate-300/80 bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,rgba(148,163,184,0.12)_4px,rgba(148,163,184,0.12)_5px)] text-sm font-semibold tabular-nums leading-none text-slate-500 sm:h-[3.6rem] sm:w-[3.25rem]',
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
                                                            'relative flex w-[2.875rem] flex-col items-center justify-between rounded-xl px-1 pb-1.5 pt-1 text-base font-semibold tabular-nums transition sm:w-[3.25rem]',
                                                            showNamedOverview ? 'min-h-[3.85rem] sm:min-h-[4.1rem]' : 'min-h-[3.25rem] sm:min-h-[3.6rem]',
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
                                                                    const c = colorForSpaceId(s.id, spaces);
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

                        <div className="flex min-h-[20rem] min-w-0 flex-col overflow-hidden bg-gradient-to-b from-white to-slate-50/40">
                            <div className="border-b border-slate-200/80 px-4 py-3 sm:px-5">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        {selectedSpaceId && selectedSpace && (
                                            <p className="text-xs font-semibold uppercase tracking-wide text-xu-secondary" id="booking-selected-space">
                                                Selected space
                                            </p>
                                        )}
                                        <p className="font-serif text-lg font-semibold text-xu-primary">
                                            {selectedSpace ? userFacingSpaceName(selectedSpace) : 'No room selected'}
                                        </p>
                                        <p className="text-sm text-slate-600">{manilaSelectedDayTitle(selectedYmd)}</p>
                                        <p className="mt-0.5 text-xs tabular-nums text-slate-500">{selectedYmd} · {BOOKING_TIMEZONE}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-3 text-xs text-slate-600">
                                        <span className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-sm">
                                            <span className="h-2 w-2 rounded-sm border-2 border-xu-secondary/50 bg-white" />
                                            Available
                                        </span>
                                        <span className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-sm">
                                            <span className="h-2 w-2 rounded-sm bg-slate-300 border border-slate-400/60" />
                                            Not available
                                        </span>
                                    </div>
                                </div>
                                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 border-t border-slate-100 pt-2 text-xs text-slate-500">
                                    <span>
                                        <span className="font-medium text-xu-primary">Slots:</span> half-hour grid (:00 / :30)
                                    </span>
                                    {selectedSpaceId && (
                                        <span>
                                            <span className="font-medium text-xu-primary">Calendar:</span> dashed{' '}
                                            <span className="font-semibold">Full</span> = no open slots for this room that day
                                        </span>
                                    )}
                                </div>
                                {readOnly && loginRequiredNudge && (
                                    <div className="mt-2 rounded-lg border border-xu-secondary/25 bg-xu-primary/[0.06] px-3 py-2 text-xs font-semibold text-xu-primary">
                                        {loginRequiredNudge}
                                    </div>
                                )}
                            </div>

                            {!selectedSpaceId && (
                                <div className="flex flex-1 items-center justify-center px-6 py-12">
                                    <p className="max-w-sm text-center text-sm text-slate-500">
                                        Select a library space above to load the schedule for{' '}
                                        <span className="font-medium text-slate-700">{manilaSelectedDayTitle(selectedYmd)}</span>.
                                    </p>
                                </div>
                            )}

                            {selectedSpaceId && loadingSlots && (
                                <div className="flex flex-1 items-center justify-center py-16">
                                    <p className="text-sm font-medium text-slate-500">Loading schedule…</p>
                                </div>
                            )}

                            {!readOnly && selectedSpaceId && !loadingSlots && !eligible && (
                                <div className="flex flex-1 items-center justify-center px-6 py-10">
                                    <p className="max-w-md text-center text-sm text-red-700">{getSpaceIneligibilityMessage(selectedSpace)}</p>
                                </div>
                            )}

                            {selectedSpaceId && !loadingSlots && eligible && (
                                <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                                    {slots.every((s) => !s.available) && (
                                        <div className="mx-4 mt-3 shrink-0 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs font-medium text-amber-950 sm:mx-5">
                                            No open slots — every row below is reserved for this room and date.
                                        </div>
                                    )}
                                    <div
                                        className="mt-2 min-h-0 max-h-[min(28rem,50vh,65dvh)] flex-1 overflow-y-auto overflow-x-auto border-t border-slate-200/80 bg-white [scrollbar-width:thin]"
                                        role="region"
                                        aria-label={`Schedule for ${selectedSpace ? userFacingSpaceName(selectedSpace) : 'room'} on ${selectedYmd}`}
                                    >
                                        <ul className="m-0 min-w-0 list-none divide-y divide-slate-100 p-0">
                                            {slots.map((slot) => {
                                                const label = formatManilaHalfHourSlotLabel(slot.hourStart, slot.minuteStart, slot.hourEnd, slot.minuteEnd);
                                                const reserveUrl = `/reserve?space_id=${selectedSpaceId}&date=${selectedYmd}&start_time=${manilaTimeParamFromHour(slot.hourStart, slot.minuteStart)}&end_time=${manilaTimeParamFromHour(slot.hourEnd, slot.minuteEnd)}`;
                                                const rowKey = `${selectedSpaceId}-${selectedYmd}-${slot.hourStart}-${slot.minuteStart}`;
                                                const gutter = formatManilaSlotGutterTimes(slot);
                                                if (!slot.available) {
                                                    return (
                                                        <li key={rowKey} className="list-none">
                                                            <div className="grid grid-cols-[4.25rem_1fr] gap-0 sm:grid-cols-[5rem_1fr]">
                                                                <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-slate-50 py-3 pr-2 pl-1 text-right">
                                                                    <span className="text-xs font-bold tabular-nums text-slate-500">
                                                                        {gutter.start}
                                                                    </span>
                                                                    <span className="text-xs tabular-nums text-slate-500">{gutter.end}</span>
                                                                </div>
                                                                <div className="p-2 sm:p-2.5">
                                                                    <div
                                                                        aria-label={`${label} — reserved`}
                                                                        className="flex h-full min-h-[3rem] items-center justify-between gap-2 rounded-lg border border-slate-300/90 bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,rgba(148,163,184,0.12)_6px,rgba(148,163,184,0.12)_7px)] bg-slate-100/90 px-3 py-2 shadow-inner"
                                                                    >
                                                                        <div className="min-w-0">
                                                                            <p className="text-sm font-semibold text-slate-600">{label}</p>
                                                                            <p className="text-xs text-slate-500">
                                                                                {selectedSpace ? userFacingSpaceName(selectedSpace) : ''} · not available
                                                                            </p>
                                                                        </div>
                                                                        <span className="shrink-0 rounded-md border border-slate-400/50 bg-slate-200/80 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-700">
                                                                            Not available
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    );
                                                }
                                                if (readOnly) {
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
                                                                        onClick={() => setLoginRequiredNudge('Log in first to reserve a slot.')}
                                                                        aria-label={`Available ${label} — log in to reserve`}
                                                                        className="group flex h-full min-h-[3rem] w-full items-center justify-between gap-2 rounded-lg border-2 border-emerald-200/90 bg-emerald-50/50 px-3 py-2 text-left shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-xu-secondary"
                                                                    >
                                                                        <div className="min-w-0 text-left">
                                                                            <p className="text-sm font-semibold text-emerald-900">{label}</p>
                                                                            <p className="text-xs text-emerald-800/90">Available · log in to reserve</p>
                                                                        </div>
                                                                        <span className="shrink-0 rounded-md bg-emerald-600/10 px-2 py-1 text-xs font-bold uppercase tracking-wide text-emerald-800">
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
                                                            <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-white py-3 pr-2 pl-1 text-right">
                                                                <span className="text-xs font-bold tabular-nums text-xu-primary">{gutter.start}</span>
                                                                <span className="text-xs tabular-nums text-slate-500">{gutter.end}</span>
                                                            </div>
                                                            <div className="p-2 sm:p-2.5">
                                                                <Link
                                                                    to={reserveUrl}
                                                                    aria-label={`Book ${label} in ${selectedSpace ? userFacingSpaceName(selectedSpace) : 'this room'}`}
                                                                    className="group flex h-full min-h-[3rem] items-center justify-between gap-2 rounded-lg border-2 border-slate-200/90 bg-white px-3 py-2 shadow-sm transition hover:border-xu-secondary hover:bg-xu-page/50 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-xu-secondary"
                                                                >
                                                                    <div className="min-w-0 text-left">
                                                                        <p className="text-sm font-semibold text-slate-900 group-hover:text-xu-primary">
                                                                            {label}
                                                                        </p>
                                                                        <p className="text-xs text-xu-secondary/90">Click to reserve this slot</p>
                                                                    </div>
                                                                    <span className="shrink-0 rounded-md bg-xu-primary/10 px-2 py-1 text-xs font-bold uppercase tracking-wide text-xu-primary group-hover:bg-xu-primary group-hover:text-white">
                                                                        Book
                                                                    </span>
                                                                </Link>
                                                            </div>
                                                        </div>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

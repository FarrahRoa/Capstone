import { useMemo, useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api';
import { getSpaceIneligibilityMessage, getSpaceRestrictionLabel, isUserEligibleForSpace } from '../../utils/spaceEligibility';

const SLOT_MINUTES = 60;
const DAY_START_HOUR = 9;
const DAY_END_HOUR = 18;

const TIMEZONE_OPTIONS = [
    { id: 'Europe/London', label: 'London', abbr: 'BST' },
    { id: 'Asia/Manila', label: 'Manila', abbr: 'PHT' },
    { id: 'UTC', label: 'UTC', abbr: 'UTC' },
];

function pad2(n) {
    return String(n).padStart(2, '0');
}

function toYmd(d) {
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function parseYmd(str) {
    const [y, m, day] = str.split('-').map(Number);
    return new Date(y, m - 1, day);
}

function ordinalDay(n) {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
}

function monthYearLabel(d) {
    return d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
}

function selectedDayTitle(d) {
    const weekday = d.toLocaleDateString('en-GB', { weekday: 'long' });
    const month = d.toLocaleDateString('en-GB', { month: 'long' });
    return `${weekday} ${ordinalDay(d.getDate())} ${month}`;
}

/** Build 6-row calendar grid cells: { date: Date, inMonth: boolean } */
function buildMonthCells(viewYear, viewMonthIndex) {
    const first = new Date(viewYear, viewMonthIndex, 1);
    const startPad = first.getDay();
    const daysInMonth = new Date(viewYear, viewMonthIndex + 1, 0).getDate();
    const prevMonthDays = new Date(viewYear, viewMonthIndex, 0).getDate();

    const cells = [];
    for (let i = 0; i < startPad; i++) {
        const day = prevMonthDays - startPad + i + 1;
        cells.push({
            date: new Date(viewYear, viewMonthIndex - 1, day),
            inMonth: false,
        });
    }
    for (let d = 1; d <= daysInMonth; d++) {
        cells.push({
            date: new Date(viewYear, viewMonthIndex, d),
            inMonth: true,
        });
    }
    while (cells.length % 7 !== 0) {
        const next = cells.length - startPad - daysInMonth + 1;
        cells.push({
            date: new Date(viewYear, viewMonthIndex + 1, next),
            inMonth: false,
        });
    }
    while (cells.length < 42) {
        const last = cells[cells.length - 1].date;
        const nd = new Date(last);
        nd.setDate(nd.getDate() + 1);
        cells.push({ date: nd, inMonth: false });
    }
    return cells.slice(0, 42);
}

function sameYmd(a, b) {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

function overlaps(aStart, aEnd, bStart, bEnd) {
    return aStart < bEnd && aEnd > bStart;
}

/** @param {string} dateYmd
 * @param {{ start_at: string, end_at: string }[]} reserved */
function buildFreeSlots(dateYmd, reserved) {
    const base = parseYmd(dateYmd);
    const slots = [];
    for (let h = DAY_START_HOUR; h < DAY_END_HOUR; h++) {
        const start = new Date(base.getFullYear(), base.getMonth(), base.getDate(), h, 0, 0, 0);
        const end = new Date(start.getTime() + SLOT_MINUTES * 60 * 1000);
        if (end.getDate() !== start.getDate()) break;
        let busy = false;
        for (const r of reserved) {
            const rs = new Date(r.start_at);
            const re = new Date(r.end_at);
            if (overlaps(start.getTime(), end.getTime(), rs.getTime(), re.getTime())) {
                busy = true;
                break;
            }
        }
        slots.push({ start, end, available: !busy });
    }
    return slots;
}

/** Local wall-clock label; matches reservation form. Suffix reflects dropdown for demo until server TZ is wired. */
function formatSlotLabel(start, end, abbr) {
    const t = (d) => `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
    return `${t(start)} - ${t(end)} (${abbr})`;
}

function toTimeParam(d) {
    return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export default function BookingCalendar({ user, spaces, spacesLoadError }) {
    const today = new Date();
    const [selectedYmd, setSelectedYmd] = useState(() => toYmd(today));
    const [viewYear, setViewYear] = useState(today.getFullYear());
    const [viewMonth, setViewMonth] = useState(today.getMonth());
    const [timeZoneId, setTimeZoneId] = useState(TIMEZONE_OPTIONS[0].id);
    const [selectedSpaceId, setSelectedSpaceId] = useState('');

    const tzMeta = TIMEZONE_OPTIONS.find((t) => t.id === timeZoneId) || TIMEZONE_OPTIONS[0];

    const selectedDate = useMemo(() => parseYmd(selectedYmd), [selectedYmd]);
    const cells = useMemo(() => buildMonthCells(viewYear, viewMonth), [viewYear, viewMonth]);

    const [reservedSlots, setReservedSlots] = useState([]);
    const [loadingSlots, setLoadingSlots] = useState(false);

    useEffect(() => {
        if (!selectedSpaceId || !selectedYmd) {
            setReservedSlots([]);
            return;
        }
        setLoadingSlots(true);
        api.get('/availability', { params: { date: selectedYmd, space_id: selectedSpaceId } })
            .then(({ data }) => {
                const row = Array.isArray(data) ? data.find((r) => String(r.space?.id) === String(selectedSpaceId)) : null;
                setReservedSlots(row?.reserved_slots || []);
            })
            .catch(() => setReservedSlots([]))
            .finally(() => setLoadingSlots(false));
    }, [selectedYmd, selectedSpaceId]);

    const slots = useMemo(
        () => buildFreeSlots(selectedYmd, reservedSlots),
        [selectedYmd, reservedSlots]
    );

    const selectedSpace = spaces.find((s) => String(s.id) === String(selectedSpaceId));
    const eligible = selectedSpace ? isUserEligibleForSpace(user, selectedSpace) : false;
    const restrictionLabel = selectedSpace ? getSpaceRestrictionLabel(selectedSpace) : '';

    const goPrevMonth = useCallback(() => {
        setViewMonth((m) => {
            if (m === 0) {
                setViewYear((y) => y - 1);
                return 11;
            }
            return m - 1;
        });
    }, []);

    const goNextMonth = useCallback(() => {
        setViewMonth((m) => {
            if (m === 11) {
                setViewYear((y) => y + 1);
                return 0;
            }
            return m + 1;
        });
    }, []);

    const onPickDate = (d) => {
        setSelectedYmd(toYmd(d));
        if (d.getMonth() !== viewMonth || d.getFullYear() !== viewYear) {
            setViewYear(d.getFullYear());
            setViewMonth(d.getMonth());
        }
    };

    return (
        <div className="bg-xu-page min-w-0 -mx-4 px-4 py-8 sm:mx-0 sm:rounded-xl sm:px-8 border border-slate-200/60 sm:border-0">
            <div className="max-w-5xl mx-auto">
                {spacesLoadError && (
                    <p className="text-sm text-red-700 bg-red-50/90 border border-red-100 rounded-md px-3 py-2 mb-4">
                        Could not load the room list. Refresh the page or try again later.
                    </p>
                )}

                <div className="mb-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
                    <label className="inline-flex items-center gap-2 text-sm text-slate-600 font-normal">
                        <span className="text-xu-primary font-medium">Room</span>
                        <select
                            value={selectedSpaceId}
                            onChange={(e) => setSelectedSpaceId(e.target.value)}
                            className="max-w-[16rem] truncate rounded-md border border-slate-200 bg-white py-1.5 pl-2 pr-8 text-sm text-slate-800 h-9 shadow-sm focus:border-xu-secondary focus:outline-none focus:ring-2 focus:ring-xu-secondary/25"
                        >
                            <option value="">Choose…</option>
                            {spaces.map((s) => {
                                const r = getSpaceRestrictionLabel(s);
                                return (
                                    <option key={s.id} value={s.id}>
                                        {r ? `${s.name} (${r})` : s.name}
                                    </option>
                                );
                            })}
                        </select>
                    </label>
                    {restrictionLabel && (
                        <span className="text-xs font-medium text-amber-800/90 bg-amber-50/80 border border-amber-100 rounded-md px-2 py-1">
                            {restrictionLabel}
                        </span>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-slate-200/90 shadow-[0_2px_12px_rgba(40,57,113,0.08)] overflow-hidden">
                    <div className="flex items-center justify-between gap-6 px-7 pt-5 pb-4 border-b border-slate-100 bg-xu-primary/5">
                        <div className="flex items-center gap-2 text-sm text-slate-600 leading-none">
                            <span className="text-xu-primary shrink-0 font-medium">Time zone</span>
                            <select
                                value={timeZoneId}
                                onChange={(e) => setTimeZoneId(e.target.value)}
                                className="h-9 rounded-md border border-slate-200 bg-white py-0 pl-2 pr-6 text-sm text-slate-800 leading-none shadow-sm focus:border-xu-secondary focus:outline-none focus:ring-2 focus:ring-xu-secondary/25 min-w-[6.5rem]"
                            >
                                {TIMEZONE_OPTIONS.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="text-sm text-slate-600 leading-none whitespace-nowrap">
                            Duration: 1 hour
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:inline-grid lg:grid-cols-[minmax(280px,360px)_minmax(260px,340px)] lg:w-max lg:mx-auto lg:divide-x lg:divide-slate-100">
                        {/* Left column — mini calendar */}
                        <div className="px-7 pt-5 pb-7 lg:pr-7 lg:pl-7">
                            <div className="grid grid-cols-3 items-center mb-3 -mx-0.5">
                                <button
                                    type="button"
                                    onClick={goPrevMonth}
                                    className="p-1.5 text-slate-400 hover:text-xu-secondary rounded-md transition justify-self-start"
                                    aria-label="Previous month"
                                >
                                    <span className="text-lg leading-none font-light">‹</span>
                                </button>
                                <span className="text-[15px] font-medium text-xu-primary font-serif text-center tracking-tight">
                                    {monthYearLabel(new Date(viewYear, viewMonth, 1))}
                                </span>
                                <button
                                    type="button"
                                    onClick={goNextMonth}
                                    className="p-1.5 text-slate-400 hover:text-xu-secondary rounded-md transition justify-self-end"
                                    aria-label="Next month"
                                >
                                    <span className="text-lg leading-none font-light">›</span>
                                </button>
                            </div>

                            <div className="grid grid-cols-7 gap-x-0.5 gap-y-1 text-center">
                                {WEEKDAYS.map((w) => (
                                    <div key={w} className="text-xs font-semibold text-xu-secondary pb-2 pt-0.5">
                                        {w}
                                    </div>
                                ))}
                                {cells.map((cell, idx) => {
                                    const isSelected = sameYmd(cell.date, selectedDate);
                                    const d = cell.date.getDate();
                                    return (
                                        <div key={idx} className="flex items-center justify-center py-0.5">
                                            <button
                                                type="button"
                                                onClick={() => onPickDate(cell.date)}
                                                className={[
                                                    'w-10 h-10 sm:w-11 sm:h-11 flex items-center justify-center rounded-md text-sm transition',
                                                    !cell.inMonth && 'text-slate-300 border border-transparent',
                                                    cell.inMonth &&
                                                        !isSelected &&
                                                        'text-slate-800 border border-slate-200/90 hover:border-xu-secondary/40 hover:bg-xu-page/80',
                                                    isSelected &&
                                                        'font-semibold text-white bg-xu-primary border border-xu-primary shadow-sm ring-2 ring-xu-gold/45 ring-offset-1 ring-offset-white',
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ')}
                                            >
                                                {d}
                                            </button>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Right column — slot list */}
                        <div className="px-7 pt-5 pb-7 lg:pl-8 bg-white">
                            <h2 className="text-center text-base font-semibold text-xu-primary font-serif mb-4 tracking-tight">
                                {selectedDayTitle(selectedDate)}
                            </h2>

                            {!selectedSpaceId && (
                                <p className="text-center text-sm text-slate-500 py-8 leading-snug">
                                    Select a room to see times.
                                </p>
                            )}

                            {selectedSpaceId && loadingSlots && (
                                <p className="text-center text-sm text-slate-500 py-8">Loading availability…</p>
                            )}

                            {selectedSpaceId && !loadingSlots && !eligible && (
                                <p className="text-center text-sm text-red-700/90 py-4 px-1 leading-snug">
                                    {getSpaceIneligibilityMessage(selectedSpace)}
                                </p>
                            )}

                            {selectedSpaceId && !loadingSlots && eligible && (
                                <div
                                    className="flex max-h-[28rem] flex-col gap-2.5 overflow-y-auto overflow-x-hidden pr-1 [scrollbar-width:thin]"
                                    title="Scroll for later times"
                                >
                                    {slots.every((s) => !s.available) && (
                                        <p className="text-center text-sm text-slate-500 py-6">
                                            No free 1-hour slots for this day.
                                        </p>
                                    )}
                                    {slots.map((slot, i) => {
                                        const label = formatSlotLabel(slot.start, slot.end, tzMeta.abbr);
                                        const reserveUrl = `/reserve?space_id=${selectedSpaceId}&date=${selectedYmd}&start_time=${toTimeParam(slot.start)}&end_time=${toTimeParam(slot.end)}`;
                                        if (!slot.available) {
                                            return (
                                                <div
                                                    key={i}
                                                    className="rounded-md border border-slate-200/80 bg-slate-50 py-3.5 px-3 text-center text-sm font-normal text-slate-400 line-through leading-snug"
                                                >
                                                    {label}
                                                </div>
                                            );
                                        }
                                        return (
                                            <Link
                                                key={i}
                                                to={reserveUrl}
                                                className="block w-full rounded-md border border-slate-200/90 bg-white py-3.5 px-3 text-center text-sm font-normal text-slate-800 leading-snug shadow-[0_1px_2px_rgba(40,57,113,0.06)] hover:border-xu-secondary/45 hover:bg-xu-page/50 hover:shadow-sm transition-colors"
                                            >
                                                {label}
                                            </Link>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

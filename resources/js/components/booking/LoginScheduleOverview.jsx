import { useCallback, useEffect, useMemo, useState } from 'react';
import api from '../../api';
import { unwrapData } from '../../utils/apiEnvelope';
import {
    buildManilaHalfHourSlots,
    buildManilaWeekStripContaining,
    formatManilaHalfHourSlotLabel,
    formatManilaSlotGutterTimes,
    manilaSelectedDayTitle,
    manilaShortDayLabel,
    manilaTodayParts,
    manilaYmdFromParts,
    shiftManilaYmd,
} from '../../utils/manilaTime';
import { BOOKING_TIMEZONE } from '../../utils/timeDisplay';

const DAY_START_HOUR = 9;
const DAY_END_HOUR = 18;

function initialSelectedYmd() {
    const t = manilaTodayParts();
    return manilaYmdFromParts(t.year, t.monthIndex0, t.day);
}

/**
 * Read-only day timeline for the login page: approved occupancy vs bookable free slots.
 * No reservation actions; visitors must sign in to book.
 */
export default function LoginScheduleOverview() {
    const [selectedYmd, setSelectedYmd] = useState(initialSelectedYmd);
    const [spaces, setSpaces] = useState([]);
    const [selectedSpaceId, setSelectedSpaceId] = useState('');
    const [reservedSlots, setReservedSlots] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        let cancelled = false;
        api
            .get('/spaces')
            .then(({ data }) => {
                if (cancelled) return;
                const list = unwrapData(data);
                setSpaces(Array.isArray(list) ? list : []);
            })
            .catch(() => {
                if (!cancelled) setSpaces([]);
            });
        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        if (spaces.length && !selectedSpaceId) {
            setSelectedSpaceId(String(spaces[0].id));
        }
    }, [spaces, selectedSpaceId]);

    useEffect(() => {
        if (!selectedSpaceId || !selectedYmd) {
            setReservedSlots([]);
            return;
        }
        let cancelled = false;
        setLoading(true);
        setError('');
        api
            .get('/public/schedule-overview', {
                params: { date: selectedYmd, space_id: selectedSpaceId },
            })
            .then(({ data }) => {
                if (cancelled) return;
                const payload = unwrapData(data);
                const rows = Array.isArray(payload?.spaces) ? payload.spaces : [];
                const row = rows.find((r) => String(r?.space?.id) === String(selectedSpaceId));
                const occ = row?.occupied_slots;
                setReservedSlots(Array.isArray(occ) ? occ : []);
            })
            .catch(() => {
                if (!cancelled) {
                    setReservedSlots([]);
                    setError('Could not load the schedule preview.');
                }
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, [selectedYmd, selectedSpaceId]);

    const todayYmd = useMemo(() => {
        const t = manilaTodayParts();
        return manilaYmdFromParts(t.year, t.monthIndex0, t.day);
    }, []);

    const isPastDay = useCallback((ymd) => String(ymd) < String(todayYmd), [todayYmd]);

    const weekStripYmds = useMemo(() => buildManilaWeekStripContaining(selectedYmd), [selectedYmd]);

    const goPrevWeek = useCallback(() => {
        const next = shiftManilaYmd(selectedYmd, -7);
        setSelectedYmd(next);
    }, [selectedYmd]);

    const goNextWeek = useCallback(() => {
        const next = shiftManilaYmd(selectedYmd, 7);
        setSelectedYmd(next);
    }, [selectedYmd]);

    const selectedSpace = spaces.find((s) => String(s.id) === String(selectedSpaceId));

    const slots = useMemo(
        () => buildManilaHalfHourSlots(selectedYmd, reservedSlots, DAY_START_HOUR, DAY_END_HOUR),
        [selectedYmd, reservedSlots]
    );

    return (
        <div
            className="flex h-full min-h-[28rem] flex-col rounded-xl border border-dashed border-slate-300/90 bg-slate-50/40 shadow-inner"
            aria-label="Public library schedule preview (read-only)"
        >
            <div className="border-b border-slate-200/80 bg-white/90 px-4 py-3 sm:px-5">
                <p className="text-[11px] font-semibold uppercase tracking-wide text-xu-secondary">Calendar overview</p>
                <h2 className="mt-0.5 font-serif text-lg font-semibold text-xu-primary tracking-tight">Space schedules</h2>
                <p className="mt-1 text-xs text-slate-600">
                    View space schedules below. Times shown are <span className="font-medium text-xu-primary">{BOOKING_TIMEZONE}</span>.
                    This board is read-only.
                </p>
                <p className="mt-2 rounded-lg border border-xu-secondary/25 bg-xu-primary/[0.06] px-3 py-2 text-xs font-medium text-xu-primary">
                    Log in to reserve a space.
                </p>
            </div>

            <div className="flex min-h-0 flex-1 flex-col gap-3 p-4 sm:p-5">
                <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
                    <span className="text-xu-primary">Library space</span>
                    <select
                        value={selectedSpaceId}
                        onChange={(e) => setSelectedSpaceId(e.target.value)}
                        className="w-full truncate rounded-lg border border-slate-200 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm focus:border-xu-secondary focus:outline-none focus:ring-2 focus:ring-xu-secondary/25"
                    >
                        {spaces.length === 0 ? <option value="">No spaces available</option> : null}
                        {spaces.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </select>
                </label>

                <div className="flex items-center gap-1 rounded-lg border border-slate-200/90 bg-white px-1 py-1 shadow-sm">
                    <button
                        type="button"
                        onClick={goPrevWeek}
                        className="shrink-0 rounded-md border border-transparent px-2 py-2 text-slate-500 hover:border-slate-200 hover:bg-slate-50 hover:text-xu-primary"
                        aria-label="Previous week"
                    >
                        <span className="text-lg leading-none">‹</span>
                    </button>
                    <div className="flex min-w-0 flex-1 justify-between gap-1 overflow-x-auto px-0.5 py-0.5">
                        {weekStripYmds.map((ymd) => {
                            const selected = ymd === selectedYmd;
                            const past = isPastDay(ymd);
                            return (
                                <button
                                    key={ymd}
                                    type="button"
                                    disabled={past}
                                    onClick={() => !past && setSelectedYmd(ymd)}
                                    title={past ? 'Past dates are hidden for booking context' : `View ${ymd}`}
                                    className={[
                                        'min-w-[3rem] shrink-0 rounded-lg border px-1.5 py-1.5 text-center text-[10px] font-medium transition sm:min-w-[3.25rem]',
                                        past && 'cursor-not-allowed border-slate-100 bg-slate-100 text-slate-400',
                                        !past &&
                                            !selected &&
                                            'border-slate-200 bg-white text-slate-700 hover:border-xu-secondary/40',
                                        !past &&
                                            selected &&
                                            'border-xu-primary bg-xu-primary text-white shadow-md ring-2 ring-xu-gold/40',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                >
                                    <span className="block opacity-90">{manilaShortDayLabel(ymd).split(' ')[0]}</span>
                                    <span className="mt-0.5 block text-xs font-semibold tabular-nums">{ymd.split('-')[2]}</span>
                                </button>
                            );
                        })}
                    </div>
                    <button
                        type="button"
                        onClick={goNextWeek}
                        className="shrink-0 rounded-md border border-transparent px-2 py-2 text-slate-500 hover:border-slate-200 hover:bg-slate-50 hover:text-xu-primary"
                        aria-label="Next week"
                    >
                        <span className="text-lg leading-none">›</span>
                    </button>
                </div>

                <div className="rounded-lg border border-slate-200/80 bg-white px-3 py-2 text-xs text-slate-600">
                    <span className="font-semibold text-xu-primary">{selectedSpace?.name ?? 'Space'}</span>
                    <span className="text-slate-400"> · </span>
                    <span>{manilaSelectedDayTitle(selectedYmd)}</span>
                </div>

                <div className="flex flex-wrap gap-3 text-[11px] text-slate-600">
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-sm">
                        <span className="h-2 w-2 rounded-sm border-2 border-emerald-500/70 bg-emerald-50" />
                        Available
                    </span>
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-sm">
                        <span className="h-2 w-2 rounded-sm bg-slate-300 border border-slate-400/60" />
                        Booked (approved)
                    </span>
                </div>

                {error && (
                    <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">{error}</p>
                )}

                {!selectedSpaceId || spaces.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center rounded-lg border border-slate-200/90 bg-white px-4 py-10 text-center">
                        <p className="text-sm font-medium text-slate-700">No schedule to show yet</p>
                        <p className="mt-1 max-w-xs text-xs text-slate-500">
                            There are no active library spaces listed. Try again later or contact the library.
                        </p>
                    </div>
                ) : loading ? (
                    <div className="flex flex-1 items-center justify-center py-12">
                        <p className="text-sm font-medium text-slate-500">Loading schedule…</p>
                    </div>
                ) : (
                    <div
                        className="max-h-[min(28rem,50vh)] min-h-0 flex-1 overflow-y-auto overflow-x-hidden rounded-lg border border-slate-200/80 bg-white [scrollbar-width:thin]"
                        role="list"
                    >
                        <ul className="m-0 list-none divide-y divide-slate-100 p-0">
                            {slots.map((slot) => {
                                const label = formatManilaHalfHourSlotLabel(
                                    slot.hourStart,
                                    slot.minuteStart,
                                    slot.hourEnd,
                                    slot.minuteEnd
                                );
                                const rowKey = `${selectedSpaceId}-${selectedYmd}-${slot.hourStart}-${slot.minuteStart}`;
                                const gutter = formatManilaSlotGutterTimes(slot);
                                if (!slot.available) {
                                    return (
                                        <li key={rowKey} className="list-none" role="listitem">
                                            <div className="grid grid-cols-[4rem_1fr] gap-0 sm:grid-cols-[4.75rem_1fr]">
                                                <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-slate-50 py-2.5 pr-2 pl-1 text-right">
                                                    <span className="text-[10px] font-bold tabular-nums text-slate-500">
                                                        {gutter.start}
                                                    </span>
                                                    <span className="text-[9px] tabular-nums text-slate-400">
                                                        {gutter.end}
                                                    </span>
                                                </div>
                                                <div className="p-2">
                                                    <div className="flex min-h-[2.75rem] flex-col justify-center rounded-lg border border-slate-300/90 bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,rgba(148,163,184,0.12)_6px,rgba(148,163,184,0.12)_7px)] bg-slate-100/90 px-3 py-2">
                                                        <p className="text-xs font-semibold text-slate-600">{label}</p>
                                                        <p className="text-[10px] text-slate-500">Booked (approved) · not available</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    );
                                }
                                return (
                                    <li key={rowKey} className="list-none" role="listitem">
                                        <div className="grid grid-cols-[4rem_1fr] gap-0 sm:grid-cols-[4.75rem_1fr]">
                                            <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-white py-2.5 pr-2 pl-1 text-right">
                                                <span className="text-[10px] font-bold tabular-nums text-xu-primary">
                                                    {gutter.start}
                                                </span>
                                                <span className="text-[9px] tabular-nums text-slate-400">
                                                    {gutter.end}
                                                </span>
                                            </div>
                                            <div className="p-2">
                                                <div
                                                    className="flex min-h-[2.75rem] cursor-default flex-col justify-center rounded-lg border-2 border-emerald-200/90 bg-emerald-50/50 px-3 py-2 outline-none"
                                                    title="Log in to reserve this slot"
                                                    tabIndex={0}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter' || e.key === ' ') {
                                                            e.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    <p className="text-xs font-semibold text-emerald-900">{label}</p>
                                                    <p className="text-[10px] text-emerald-800/90">
                                                        Available · log in to reserve
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
}

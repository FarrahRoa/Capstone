import { useMemo, useState, useEffect, useCallback } from 'react';
import api from '../../api';
import {
    buildManilaHalfHourSlots,
    buildManilaMonthCells,
    buildManilaWeekStripContaining,
    formatManilaHalfHourSlotLabel,
    formatManilaSlotGutterTimes,
    manilaMonthYearLabel,
    manilaSelectedDayTitle,
    manilaShortDayLabel,
    manilaTodayParts,
    manilaYmdFromParts,
    shiftManilaYmd,
} from '../../utils/manilaTime';
import { unwrapData } from '../../utils/apiEnvelope';
import { BOOKING_TIMEZONE } from '../../utils/timeDisplay';
import { colorForOperationalSpaceId } from '../../utils/spaceColors';
import { operationalSpaceLabel } from '../../utils/operationalSpaceLabel';
import { ui } from '../../theme';

const DAY_START_HOUR = 9;
const DAY_END_HOUR = 18;
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

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

function overviewSpaceRows(spaceIds, spaces) {
    if (!Array.isArray(spaceIds) || spaceIds.length === 0) return [];
    const out = [];
    const seen = new Set();
    for (const id of spaceIds) {
        const s = spaces.find((x) => String(x.id) === String(id));
        const row = s || { id, name: `Space ${id}` };
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

/**
 * Read-only all-spaces day schedule for admin monitoring (no booking actions).
 */
export default function AdminScheduleOverview({ spaces, spacesLoadError, embedded = false }) {
    const [cal, setCal] = useState(initialManilaCalendarState);
    const { selectedYmd, viewYear, viewMonth } = cal;
    const cells = useMemo(() => buildManilaMonthCells(viewYear, viewMonth), [viewYear, viewMonth]);

    const cellYmdBounds = useMemo(() => {
        if (!cells.length) return { min: '', max: '' };
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

    const isPastDay = useCallback((ymd) => String(ymd) < String(todayYmd), [todayYmd]);
    const weekStripYmds = useMemo(() => buildManilaWeekStripContaining(selectedYmd), [selectedYmd]);

    const [overviewByDate, setOverviewByDate] = useState({});
    const [overviewLoading, setOverviewLoading] = useState(false);
    const [dayRows, setDayRows] = useState([]);
    const [dayLoadError, setDayLoadError] = useState(false);
    const [loadingDay, setLoadingDay] = useState(false);
    const [activeSpaceId, setActiveSpaceId] = useState('');

    useEffect(() => {
        if (!cellYmdBounds.min || !cellYmdBounds.max) {
            setOverviewByDate({});
            setOverviewLoading(false);
            return;
        }
        let cancelled = false;
        setOverviewLoading(true);
        api.get('/availability/month-overview', {
            params: { from: cellYmdBounds.min, to: cellYmdBounds.max },
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
    }, [cellYmdBounds.min, cellYmdBounds.max]);

    useEffect(() => {
        if (!selectedYmd) {
            setDayRows([]);
            return;
        }
        let cancelled = false;
        setLoadingDay(true);
        setDayLoadError(false);
        api.get('/availability', { params: { date: selectedYmd, operational: 1 } })
            .then(({ data }) => {
                if (cancelled) return;
                const rows = unwrapData(data);
                setDayRows(Array.isArray(rows) ? rows : []);
            })
            .catch(() => {
                if (!cancelled) {
                    setDayRows([]);
                    setDayLoadError(true);
                }
            })
            .finally(() => {
                if (!cancelled) setLoadingDay(false);
            });
        return () => {
            cancelled = true;
        };
    }, [selectedYmd]);

    const daySpaceIds = useMemo(
        () => dayRows.map((r) => String(r?.space?.id ?? '')).filter(Boolean),
        [dayRows]
    );

    /** Effective tab: user choice when valid; else first space (avoids empty panel before effects run). */
    const displayedSpaceId = useMemo(() => {
        if (!daySpaceIds.length) return '';
        if (activeSpaceId && daySpaceIds.includes(activeSpaceId)) return activeSpaceId;
        return daySpaceIds[0];
    }, [daySpaceIds, activeSpaceId]);

    useEffect(() => {
        if (loadingDay || dayLoadError) return;
        if (activeSpaceId && !daySpaceIds.includes(activeSpaceId)) {
            setActiveSpaceId('');
        }
    }, [daySpaceIds, loadingDay, dayLoadError, activeSpaceId]);

    const activeRow = useMemo(
        () => dayRows.find((r) => String(r?.space?.id) === String(displayedSpaceId)),
        [dayRows, displayedSpaceId]
    );

    const activeSlots = useMemo(() => {
        if (!activeRow?.space?.id) return [];
        const reserved = Array.isArray(activeRow.reserved_slots) ? activeRow.reserved_slots : [];
        return buildManilaHalfHourSlots(selectedYmd, reserved, DAY_START_HOUR, DAY_END_HOUR);
    }, [activeRow, selectedYmd]);

    const spacesWithColors = useMemo(() => {
        return spaces.map((s) => ({ ...s, __color: colorForOperationalSpaceId(s.id, spaces) }));
    }, [spaces]);

    const goPrevMonth = useCallback(() => {
        setCal((c) => {
            if (c.viewMonth === 0) return { ...c, viewYear: c.viewYear - 1, viewMonth: 11 };
            return { ...c, viewMonth: c.viewMonth - 1 };
        });
    }, []);

    const goNextMonth = useCallback(() => {
        setCal((c) => {
            if (c.viewMonth === 11) return { ...c, viewYear: c.viewYear + 1, viewMonth: 0 };
            return { ...c, viewMonth: c.viewMonth + 1 };
        });
    }, []);

    const onPickDate = (cell) => {
        if (isPastDay(cell.ymd)) return;
        const [y, m] = cell.ymd.split('-').map(Number);
        setCal({ selectedYmd: cell.ymd, viewYear: y, viewMonth: m - 1 });
    };

    const onPickWeekDay = (ymd) => {
        if (isPastDay(ymd)) return;
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

    return (
        <div
            id={embedded ? 'book-a-space' : undefined}
            data-testid="admin-schedule-overview"
            className={shellClass}
        >
            <div className="mx-auto min-w-0 max-w-6xl">
                {spacesLoadError && (
                    <p className="text-sm text-red-700 bg-red-50/90 border border-red-100 rounded-md px-3 py-2 m-4 mb-0">
                        Could not load the room list. Refresh the page or try again later.
                    </p>
                )}

                <div className="border-b border-slate-200/90 bg-gradient-to-r from-xu-primary/[0.07] via-white to-xu-page/80 px-4 py-4 sm:px-6 sm:py-4">
                    <div className="min-w-0">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-xu-secondary">Admin schedule overview</p>
                        <h3 className="mt-0.5 font-serif text-xl font-semibold text-xu-primary tracking-tight">All library spaces</h3>
                        <p className="mt-1 text-xs text-slate-600 max-w-2xl">
                            Pick a date to inspect every active space in one view. Reserved vs available slots use the same half-hour grid (:00 / :30) as the public calendar. Times are{' '}
                            <span className="font-medium text-xu-primary">{BOOKING_TIMEZONE}</span>. This view is read-only.
                        </p>
                    </div>
                    {spaces.length > 0 && (
                        <div className="mt-3 flex flex-col gap-2">
                            <div className="flex items-center gap-3">
                                <span className="text-[10px] font-bold uppercase tracking-wide text-slate-500">Overview legend</span>
                                {overviewLoading && <span className="text-[11px] font-medium text-slate-500">Loading overview…</span>}
                            </div>
                            <div className="flex min-w-0 flex-wrap gap-2 overflow-x-auto pb-0.5 [scrollbar-width:thin]">
                                {spacesWithColors.map((s) => (
                                    <span
                                        key={s.id}
                                        className={`inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-700 shadow-sm ring-1 ${s.__color.ring}`}
                                        title={operationalSpaceLabel(s)}
                                    >
                                        <span className={`h-2.5 w-2.5 rounded-full ${s.__color.bg}`} aria-hidden="true" />
                                        <span className="max-w-[14rem] truncate sm:max-w-none">{operationalSpaceLabel(s)}</span>
                                    </span>
                                ))}
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
                            const stripIds = Array.isArray(overviewByDate?.[ymd]) ? overviewByDate[ymd] : [];
                            const stripRows = overviewSpaceRows(stripIds, spaces);
                            const stripOverviewTip =
                                stripRows.length > 0 ? `Spaces with reservations: ${stripRows.map((s) => s.name).join(', ')}` : '';
                            if (isPast) {
                                return (
                                    <div
                                        key={ymd}
                                        role="presentation"
                                        title={stripOverviewTip ? `Past date. ${stripOverviewTip}` : 'Past date'}
                                        className={[
                                            'min-w-[3.25rem] shrink-0 cursor-not-allowed rounded-lg border border-slate-200/90 bg-slate-100/70 px-2 py-2 text-center text-[11px] font-medium text-slate-400 sm:min-w-[3.5rem] sm:px-2.5',
                                            selected && 'border-xu-primary/50 bg-xu-primary/10 text-xu-primary ring-2 ring-xu-gold/30 ring-offset-1 ring-offset-slate-50',
                                        ]
                                            .filter(Boolean)
                                            .join(' ')}
                                    >
                                        <span className="block leading-tight opacity-70">{manilaShortDayLabel(ymd).split(' ')[0]}</span>
                                        <span className="mt-0.5 block text-sm font-semibold tabular-nums leading-none">{ymd.split('-')[2]}</span>
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
                                        'min-w-[3.25rem] shrink-0 rounded-lg border px-2 py-2 text-center text-[11px] font-medium transition sm:min-w-[3.5rem] sm:px-2.5',
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
                                        <div key={w} className="text-[11px] font-bold uppercase tracking-wide text-xu-secondary pb-1.5">
                                            {w.slice(0, 1)}
                                        </div>
                                    ))}
                                    {cells.map((cell, idx) => {
                                        const isSelected = cell.ymd === selectedYmd;
                                        const isTodayCell = cell.ymd === todayYmd;
                                        const isPast = isPastDay(cell.ymd);
                                        const spaceIds = Array.isArray(overviewByDate?.[cell.ymd]) ? overviewByDate[cell.ymd] : [];
                                        const overviewRows = overviewSpaceRows(spaceIds, spaces);
                                        const overviewNameList = overviewRows.map((s) => s.name).join(', ');
                                        const overviewTooltip = overviewRows.length > 0 ? `Spaces with reservations: ${overviewNameList}` : '';
                                        const showNamedOverview = cell.inMonth && overviewRows.length > 0;
                                        const namedPreview = overviewRows.slice(0, 2);
                                        const namedMore = overviewRows.length > 2 ? overviewRows.length - 2 : 0;
                                        return (
                                            <div key={idx} className="flex items-center justify-center py-0.5">
                                                {isPast ? (
                                                    <div
                                                        role="gridcell"
                                                        title={overviewTooltip ? `Past date. ${overviewTooltip}` : 'Past date'}
                                                        className={[
                                                            'flex h-[3.25rem] w-[2.875rem] cursor-not-allowed flex-col items-center justify-center rounded-xl border border-slate-200/80 bg-slate-100/70 text-sm font-semibold tabular-nums leading-none text-slate-400 sm:h-[3.6rem] sm:w-[3.25rem]',
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
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() => onPickDate(cell)}
                                                        title={showNamedOverview ? overviewTooltip : undefined}
                                                        aria-label={showNamedOverview ? `${cell.dayNum}, ${overviewTooltip}` : `${cell.dayNum}`}
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
                                                                    const c = colorForOperationalSpaceId(s.id, spaces);
                                                                    return (
                                                                        <span
                                                                            key={s.id}
                                                                            className={`max-w-[3rem] truncate rounded-md px-1 py-0.5 text-center text-[8px] font-bold leading-tight text-white shadow-md ring-1 ring-black/15 sm:max-w-[3.35rem] sm:text-[9px] ${c.bg}`}
                                                                            title={s.name}
                                                                        >
                                                                            {abbreviateSpaceName(operationalSpaceLabel(s))}
                                                                        </span>
                                                                    );
                                                                })}
                                                                {namedMore > 0 && (
                                                                    <span
                                                                        className={[
                                                                            'text-[8px] font-bold leading-tight sm:text-[9px]',
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
                                <p className="font-serif text-lg font-semibold text-xu-primary">Selected day</p>
                                <p className="text-sm text-slate-600">{manilaSelectedDayTitle(selectedYmd)}</p>
                                <p className="mt-0.5 text-xs tabular-nums text-slate-500">
                                    {selectedYmd} · {BOOKING_TIMEZONE}
                                </p>

                                {!loadingDay && !dayLoadError && dayRows.length > 0 && (
                                    <div className="mt-3">
                                        <p id="admin-space-tabs-label" className="text-[10px] font-bold uppercase tracking-wide text-slate-500 mb-2">
                                            Library space
                                        </p>
                                        <div
                                            role="tablist"
                                            aria-labelledby="admin-space-tabs-label"
                                            className="flex flex-wrap gap-2 max-h-[7.5rem] overflow-y-auto [scrollbar-width:thin] pr-0.5"
                                        >
                                            {dayRows.map((row) => {
                                                const space = row?.space;
                                                if (!space?.id) return null;
                                                const idStr = String(space.id);
                                                const selected = idStr === String(displayedSpaceId);
                                                const c = colorForOperationalSpaceId(space.id, spaces);
                                                const chipLabel = abbreviateSpaceName(operationalSpaceLabel(space));
                                                return (
                                                    <button
                                                        key={space.id}
                                                        type="button"
                                                        role="tab"
                                                        aria-selected={selected}
                                                        title={space.name}
                                                        onClick={() => setActiveSpaceId(idStr)}
                                                        className={[
                                                            'inline-flex max-w-full items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-left text-[11px] font-semibold shadow-sm transition',
                                                            selected
                                                                ? 'border-xu-primary bg-xu-primary/10 text-xu-primary ring-2 ring-xu-gold/45 ring-offset-1 ring-offset-white'
                                                                : 'border-slate-200/90 bg-white text-slate-700 hover:border-xu-secondary/40 hover:bg-xu-page/50',
                                                        ].join(' ')}
                                                    >
                                                        <span className={`h-2 w-2 shrink-0 rounded-full ${c.bg}`} aria-hidden="true" />
                                                        <span className="truncate">{chipLabel}</span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        {activeRow?.space && (
                                            <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                                                <span
                                                    className={`h-2.5 w-2.5 shrink-0 rounded-full ${colorForOperationalSpaceId(activeRow.space.id, spaces).bg}`}
                                                    aria-hidden="true"
                                                />
                                                <p className="text-sm font-semibold text-xu-primary">
                                                    <span className="text-slate-500 font-medium">Viewing: </span>
                                                    {operationalSpaceLabel(activeRow.space)}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="mt-3 flex flex-wrap gap-3 text-[11px] text-slate-600">
                                    <span className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-sm">
                                        <span className="h-2 w-2 rounded-sm border-2 border-xu-secondary/50 bg-white" />
                                        Available
                                    </span>
                                    <span className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-sm">
                                        <span className="h-2 w-2 rounded-sm bg-slate-300 border border-slate-400/60" />
                                        Reserved
                                    </span>
                                </div>
                                <p className="mt-2 border-t border-slate-100 pt-2 text-[11px] text-slate-500">
                                    <span className="font-medium text-xu-primary">Slots:</span> half-hour grid (:00 / :30)
                                </p>
                            </div>

                            {dayLoadError && (
                                <div className="mx-4 mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs font-medium text-amber-950 sm:mx-5">
                                    Could not load availability for this date. Try again or pick another day.
                                </div>
                            )}

                            {loadingDay && (
                                <div className="flex flex-1 items-center justify-center py-16">
                                    <p className="text-sm font-medium text-slate-500">Loading schedule…</p>
                                </div>
                            )}

                            {!loadingDay && !dayLoadError && (
                                <div
                                    className="mt-2 min-h-0 max-h-[min(28rem,50vh,65dvh)] flex-1 overflow-y-auto overflow-x-hidden border-t border-slate-200/80 bg-white [scrollbar-width:thin]"
                                    role="tabpanel"
                                    aria-label={activeRow?.space?.name ? `Slots for ${activeRow.space.name} on ${selectedYmd}` : `Schedule for ${selectedYmd}`}
                                >
                                    {activeRow?.space?.id && (
                                        <div className="px-3 py-3 sm:px-4">
                                            <ul className="m-0 list-none divide-y divide-slate-100 rounded-lg border border-slate-200/90 p-0">
                                                {activeSlots.map((slot) => {
                                                    const space = activeRow.space;
                                                    const label = formatManilaHalfHourSlotLabel(
                                                        slot.hourStart,
                                                        slot.minuteStart,
                                                        slot.hourEnd,
                                                        slot.minuteEnd
                                                    );
                                                    const gutter = formatManilaSlotGutterTimes(slot);
                                                    const rowKey = `${space.id}-${selectedYmd}-${slot.hourStart}-${slot.minuteStart}`;
                                                    if (!slot.available) {
                                                        return (
                                                            <li key={rowKey} className="list-none">
                                                                <div className="grid grid-cols-[4.25rem_1fr] gap-0 sm:grid-cols-[5rem_1fr]">
                                                                    <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-slate-50 py-2 pr-2 pl-1 text-right">
                                                                        <span className="text-[11px] font-bold tabular-nums text-slate-500">{gutter.start}</span>
                                                                        <span className="text-[10px] tabular-nums text-slate-400">{gutter.end}</span>
                                                                    </div>
                                                                    <div className="p-2">
                                                                        <div
                                                                            aria-label={`${space.name} ${label} reserved`}
                                                                            className="flex min-h-[2.5rem] items-center justify-between gap-2 rounded-lg border border-slate-300/90 bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,rgba(148,163,184,0.12)_6px,rgba(148,163,184,0.12)_7px)] bg-slate-100/90 px-3 py-2"
                                                                        >
                                                                            <p className="text-xs font-semibold text-slate-600">{label}</p>
                                                                            <span className="shrink-0 rounded-md border border-slate-400/50 bg-slate-200/80 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-slate-700">
                                                                                Reserved
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
                                                                <div className="flex flex-col items-end justify-center border-r border-slate-100 bg-white py-2 pr-2 pl-1 text-right">
                                                                    <span className="text-[11px] font-bold tabular-nums text-xu-primary">{gutter.start}</span>
                                                                    <span className="text-[10px] tabular-nums text-slate-400">{gutter.end}</span>
                                                                </div>
                                                                <div className="p-2">
                                                                    <div className="flex min-h-[2.5rem] items-center justify-between gap-2 rounded-lg border border-slate-200/90 bg-white px-3 py-2 shadow-sm">
                                                                        <p className="text-xs font-semibold text-slate-800">{label}</p>
                                                                        <span className="shrink-0 rounded-md bg-emerald-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-emerald-800 ring-1 ring-emerald-200/80">
                                                                            Available
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    );
                                                })}
                                            </ul>
                                        </div>
                                    )}
                                    {dayRows.length === 0 && (
                                        <p className="px-4 py-8 text-center text-sm text-slate-500">No active spaces to show.</p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <p className={`border-t border-slate-200/80 px-4 py-3 text-center text-[11px] text-slate-500 sm:px-6 ${ui.sectionLabel}`}>
                    Use “Reservation queue” or “Spaces” in shortcuts for approvals and room configuration.
                </p>
            </div>
        </div>
    );
}

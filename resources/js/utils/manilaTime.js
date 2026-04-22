import { BOOKING_TIMEZONE } from './timeDisplay';

/** Philippines does not observe DST; PHT is always UTC+8. */
export const MANILA_OFFSET = '+08:00';

const WEEKDAY_SHORT_TO_INDEX = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

function pad2(n) {
    return String(n).padStart(2, '0');
}

/**
 * Manila civil calendar YYYY-MM-DD for an instant (browser-independent for that instant).
 */
export function manilaYmdFromInstant(date) {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: BOOKING_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(date);
    const y = parts.find((p) => p.type === 'year')?.value;
    const m = parts.find((p) => p.type === 'month')?.value;
    const d = parts.find((p) => p.type === 'day')?.value;
    return `${y}-${m}-${d}`;
}

/**
 * Manila wall-clock HH:mm (24h) for an instant (matches reservation payloads / Intl day logic).
 */
export function manilaHhmmFromIso(isoOrDate) {
    const d = isoOrDate instanceof Date ? isoOrDate : new Date(isoOrDate);
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: BOOKING_TIMEZONE,
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(d);
    const hh = parts.find((p) => p.type === 'hour')?.value ?? '00';
    const mm = parts.find((p) => p.type === 'minute')?.value ?? '00';
    return `${hh}:${mm}`;
}

/**
 * { year, monthIndex0, day } for "today" in Manila.
 */
export function manilaTodayParts() {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: BOOKING_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const y = Number(parts.find((p) => p.type === 'year')?.value);
    const m = Number(parts.find((p) => p.type === 'month')?.value) - 1;
    const d = Number(parts.find((p) => p.type === 'day')?.value);
    return { year: y, monthIndex0: m, day: d };
}

export function manilaYmdFromParts(year, monthIndex0, day) {
    return `${year}-${pad2(monthIndex0 + 1)}-${pad2(day)}`;
}

/** Gregorian days in month (year, monthIndex 0–11); uses UTC date math only. */
export function daysInMonthGregorian(year, monthIndex0) {
    return new Date(Date.UTC(year, monthIndex0 + 1, 0)).getUTCDate();
}

/**
 * Sunday=0 … Saturday=6 for this Manila civil date (noon anchor avoids DST edge cases; PH has no DST).
 */
export function manilaWeekdaySun0(year, month1to12, day) {
    const iso = `${year}-${pad2(month1to12)}-${pad2(day)}T12:00:00${MANILA_OFFSET}`;
    const inst = new Date(iso);
    const short = new Intl.DateTimeFormat('en-US', { timeZone: BOOKING_TIMEZONE, weekday: 'short' }).format(inst);
    const idx = WEEKDAY_SHORT_TO_INDEX[short];
    return idx !== undefined ? idx : 0;
}

/**
 * Shift a Manila civil YMD by a number of days (UTC calendar math; PH has no DST).
 */
export function shiftManilaYmd(ymd, deltaDays) {
    const [y, m, d] = ymd.split('-').map(Number);
    const ms = Date.UTC(y, m - 1, d + deltaDays);
    const t = new Date(ms);
    return manilaYmdFromParts(t.getUTCFullYear(), t.getUTCMonth(), t.getUTCDate());
}

/**
 * Seven Manila YMD strings (Sun → Sat) for the week containing anchorYmd.
 * @returns {string[]}
 */
export function buildManilaWeekStripContaining(anchorYmd) {
    const [y, m, d] = anchorYmd.split('-').map(Number);
    const dow = manilaWeekdaySun0(y, m, d);
    const monthIndex0 = m - 1;
    const startUtcMs = Date.UTC(y, monthIndex0, d - dow);
    const out = [];
    for (let i = 0; i < 7; i++) {
        const t = new Date(startUtcMs + i * 86400000);
        out.push(manilaYmdFromParts(t.getUTCFullYear(), t.getUTCMonth(), t.getUTCDate()));
    }
    return out;
}

/**
 * Short label e.g. "Mon 14" for a Manila civil YMD.
 */
export function manilaShortDayLabel(ymd) {
    const [y, mo, d] = ymd.split('-').map(Number);
    const inst = new Date(`${y}-${pad2(mo)}-${pad2(d)}T12:00:00${MANILA_OFFSET}`);
    const wd = new Intl.DateTimeFormat('en-GB', { timeZone: BOOKING_TIMEZONE, weekday: 'short' }).format(inst);
    return `${wd} ${d}`;
}

/**
 * Month grid cells for a Manila year/month. Each cell is a Manila civil date string + display day number.
 * @returns {{ ymd: string, dayNum: number, inMonth: boolean }[]}
 */
export function buildManilaMonthCells(viewYear, viewMonthIndex0) {
    const dim = daysInMonthGregorian(viewYear, viewMonthIndex0);
    const startPad = manilaWeekdaySun0(viewYear, viewMonthIndex0 + 1, 1);

    const prevMonthIndex0 = viewMonthIndex0 === 0 ? 11 : viewMonthIndex0 - 1;
    const prevYear = viewMonthIndex0 === 0 ? viewYear - 1 : viewYear;
    const prevDim = daysInMonthGregorian(prevYear, prevMonthIndex0);

    const cells = [];
    for (let i = 0; i < startPad; i++) {
        const day = prevDim - startPad + i + 1;
        cells.push({
            ymd: manilaYmdFromParts(prevYear, prevMonthIndex0, day),
            dayNum: day,
            inMonth: false,
        });
    }
    for (let d = 1; d <= dim; d++) {
        cells.push({
            ymd: manilaYmdFromParts(viewYear, viewMonthIndex0, d),
            dayNum: d,
            inMonth: true,
        });
    }

    let nextY = viewYear;
    let nextM = viewMonthIndex0 + 1;
    if (nextM > 11) {
        nextM = 0;
        nextY += 1;
    }
    let nextDay = 1;

    const pushNext = () => {
        cells.push({
            ymd: manilaYmdFromParts(nextY, nextM, nextDay),
            dayNum: nextDay,
            inMonth: false,
        });
        nextDay += 1;
        const dimNext = daysInMonthGregorian(nextY, nextM);
        if (nextDay > dimNext) {
            nextDay = 1;
            nextM += 1;
            if (nextM > 11) {
                nextM = 0;
                nextY += 1;
            }
        }
    };

    while (cells.length % 7 !== 0) {
        pushNext();
    }
    while (cells.length < 42) {
        pushNext();
    }
    return cells.slice(0, 42);
}

/**
 * @deprecated Legacy HH:15–(HH+1):00 helper; prefer {@link buildManilaHalfHourSlots}.
 */
export function manilaSlotBoundsMs(dateYmd, hourStart) {
    const startMs = Date.parse(`${dateYmd}T${pad2(hourStart)}:15:00${MANILA_OFFSET}`);
    const endMs = Date.parse(`${dateYmd}T${pad2(hourStart + 1)}:00:00${MANILA_OFFSET}`);
    return { startMs, endMs };
}

function overlapsMs(a0, a1, b0, b1) {
    return a0 < b1 && a1 > b0;
}

/**
 * @param {string} dateYmd
 * @param {{ start_at: string, end_at: string }[]} reserved
 * @param {number} dayStartHour inclusive
 * @param {number} dayEndHour exclusive (last slot ends at this hour)
 */
/** @returns {ReturnType<typeof buildManilaHalfHourSlots>} */
export function buildManilaFreeSlots(dateYmd, reserved, dayStartHour, dayEndHour) {
    return buildManilaHalfHourSlots(dateYmd, reserved, dayStartHour, dayEndHour);
}

/**
 * 12-hour label for a Manila wall-clock time (hour 0–23, minute 0–59).
 */
export function formatManilaWallTime12(hour, minute) {
    const inst = new Date(`2000-01-01T${pad2(hour)}:${pad2(minute)}:00${MANILA_OFFSET}`);
    return new Intl.DateTimeFormat('en-US', {
        timeZone: BOOKING_TIMEZONE,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(inst);
}

/**
 * Left gutter on schedule rows (12-hour), aligned to slot start/end minutes.
 *
 * @param {{ hourStart: number, hourEnd: number, minuteStart?: number, minuteEnd?: number }} slot
 * @param {boolean} [useHalfHour=true] when minutes are omitted and this is false, uses legacy :15→:00 gutters
 */
export function formatManilaSlotGutterTimes(slot, useHalfHour = true) {
    const startMinute = slot.minuteStart !== undefined ? slot.minuteStart : (useHalfHour ? 0 : 15);
    const endMinute = slot.minuteEnd !== undefined ? slot.minuteEnd : 0;

    return {
        start: formatManilaWallTime12(slot.hourStart, startMinute),
        end: formatManilaWallTime12(slot.hourEnd, endMinute),
    };
}

/** @deprecated Prefer {@link formatManilaHalfHourSlotLabel} with explicit minutes. */
export function formatManilaSlotLabel(hourStart, hourEnd) {
    const startLabel = formatManilaWallTime12(hourStart, 15);
    const endLabel = formatManilaWallTime12(hourEnd, 0);
    return `${startLabel} – ${endLabel}`;
}

/**
 * Half-hour slot builder (HH:00–HH:30, HH:30–(HH+1):00).
 *
 * @param {string} dateYmd
 * @param {{ start_at: string, end_at: string }[]} reserved
 * @param {number} dayStartHour inclusive
 * @param {number} dayEndHour exclusive (last slot ends at this hour)
 */
export function buildManilaHalfHourSlots(dateYmd, reserved, dayStartHour, dayEndHour) {
    const slots = [];
    const toMs = (h, m) => Date.parse(`${dateYmd}T${pad2(h)}:${pad2(m)}:00${MANILA_OFFSET}`);
    for (let h = dayStartHour; h < dayEndHour; h++) {
        for (const m of [0, 30]) {
            const startMs = toMs(h, m);
            const endMs = toMs(m === 0 ? h : h + 1, m === 0 ? 30 : 0);
            let busy = false;
            for (const r of reserved) {
                const rs = new Date(r.start_at).getTime();
                const re = new Date(r.end_at).getTime();
                if (overlapsMs(startMs, endMs, rs, re)) {
                    busy = true;
                    break;
                }
            }
            const endHour = m === 0 ? h : h + 1;
            const endMinute = m === 0 ? 30 : 0;
            slots.push({
                hourStart: h,
                minuteStart: m,
                hourEnd: endHour,
                minuteEnd: endMinute,
                available: !busy,
            });
        }
    }
    return slots;
}

export function formatManilaHalfHourSlotLabel(hourStart, minuteStart, hourEnd, minuteEnd) {
    return `${formatManilaWallTime12(hourStart, minuteStart)} – ${formatManilaWallTime12(hourEnd, minuteEnd)}`;
}

export function manilaTimeParamFromHour(hour, minute = 0) {
    return `${pad2(hour)}:${pad2(minute)}`;
}

/**
 * Long title for a Manila YMD (weekday + ordinal day + month).
 */
export function manilaSelectedDayTitle(ymd) {
    const [y, m, d] = ymd.split('-').map(Number);
    const inst = new Date(`${y}-${pad2(m)}-${pad2(d)}T12:00:00${MANILA_OFFSET}`);
    const weekday = new Intl.DateTimeFormat('en-GB', { timeZone: BOOKING_TIMEZONE, weekday: 'long' }).format(inst);
    const month = new Intl.DateTimeFormat('en-GB', { timeZone: BOOKING_TIMEZONE, month: 'long' }).format(inst);
    const ord = (n) => {
        const s = ['th', 'st', 'nd', 'rd'];
        const v = n % 100;
        return n + (s[(v - 20) % 10] || s[v] || s[0]);
    };
    return `${weekday} ${ord(d)} ${month}`;
}

export function manilaMonthYearLabel(viewYear, viewMonthIndex0) {
    const inst = new Date(`${viewYear}-${pad2(viewMonthIndex0 + 1)}-01T12:00:00${MANILA_OFFSET}`);
    return new Intl.DateTimeFormat('en-GB', { timeZone: BOOKING_TIMEZONE, month: 'long', year: 'numeric' }).format(inst);
}

/**
 * Reservation API payload: Manila civil wall time with explicit +08:00 (matches Laravel app timezone).
 */
export function manilaReservationDateTimePayload(dateYmd, hhmm) {
    const [a, b] = String(hhmm).split(':');
    const hh = pad2(Number(a) || 0);
    const mm = pad2(Number(b ?? 0) || 0);
    return `${dateYmd}T${hh}:${mm}:00${MANILA_OFFSET}`;
}

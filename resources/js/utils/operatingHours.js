import { joinHalfHourWallClockHhmm, splitHalfHourWallClockHhmm } from './halfHourWallClockInput';
import { MANILA_OFFSET, manilaWeekdaySun0, manilaYmdFromInstant } from './manilaTime';

export const DEFAULT_OPERATING_HOURS_SHAPE = {
    day_start: '06:00',
    day_end: '18:30',
    weekend_day_start: null,
    weekend_day_end: null,
};

function pad2(n) {
    return String(n).padStart(2, '0');
}

/**
 * @param {unknown} payloadHours `hours` object from GET /policies/operating-hours
 * @returns {{ day_start: string, day_end: string, weekend_day_start: string|null, weekend_day_end: string|null }}
 */
export function normalizeOperatingHoursPayload(payloadHours) {
    const d = payloadHours && typeof payloadHours === 'object' ? payloadHours : {};
    const wsRaw = d.weekend_day_start;
    const weRaw = d.weekend_day_end;
    let weekendStart = wsRaw != null && String(wsRaw).trim() !== '' ? String(wsRaw).slice(0, 5) : null;
    let weekendEnd = weRaw != null && String(weRaw).trim() !== '' ? String(weRaw).slice(0, 5) : null;
    if (!weekendStart || !weekendEnd) {
        weekendStart = null;
        weekendEnd = null;
    }
    return {
        day_start: String(d.day_start || DEFAULT_OPERATING_HOURS_SHAPE.day_start).slice(0, 5),
        day_end: String(d.day_end || DEFAULT_OPERATING_HOURS_SHAPE.day_end).slice(0, 5),
        weekend_day_start: weekendStart,
        weekend_day_end: weekendEnd,
    };
}

/**
 * @param {{ day_start: string, day_end: string, weekend_day_start: string|null, weekend_day_end: string|null }} config
 * @param {string} ymd
 * @returns {{ start: string, end: string }}
 */
export function resolveOperatingWindowForYmd(config, ymd) {
    if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
        return { start: config.day_start, end: config.day_end };
    }
    const [y, m, d] = ymd.split('-').map(Number);
    const w = manilaWeekdaySun0(y, m, d);
    const isWeekend = w === 0 || w === 6;
    if (isWeekend && config.weekend_day_start && config.weekend_day_end) {
        return { start: config.weekend_day_start, end: config.weekend_day_end };
    }
    return { start: config.day_start, end: config.day_end };
}

export function hhmmToMinutes(hhmm) {
    const [h, m] = String(hhmm || '00:00').split(':').map((x) => Number(x));
    if (!Number.isFinite(h) || !Number.isFinite(m)) return 0;
    return h * 60 + m;
}

export function minutesToHhmm(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return `${pad2(h)}:${pad2(m)}`;
}

/** Half-hour steps from open through close inclusive (each marker is a valid slot boundary). */
export function halfHourMarkersInclusive(openHhmm, closeHhmm) {
    const a = hhmmToMinutes(openHhmm);
    const b = hhmmToMinutes(closeHhmm);
    if (b <= a) return [];
    const out = [];
    for (let t = a; t <= b; t += 30) {
        out.push(minutesToHhmm(t));
    }
    return out;
}

/** Start times: half-hour marks strictly before close. */
export function allowedStartHhmmList(openHhmm, closeHhmm) {
    const closeM = hhmmToMinutes(closeHhmm);
    return halfHourMarkersInclusive(openHhmm, closeHhmm).filter((t) => hhmmToMinutes(t) < closeM);
}

/** End times after start, through close inclusive. */
export function allowedEndHhmmList(openHhmm, closeHhmm, startHhmm) {
    const startM = hhmmToMinutes(startHhmm);
    const closeM = hhmmToMinutes(closeHhmm);
    return halfHourMarkersInclusive(openHhmm, closeHhmm).filter((t) => {
        const tm = hhmmToMinutes(t);
        return tm > startM && tm <= closeM;
    });
}

/** Same calendar day: valid starts strictly before a chosen end time. */
export function allowedStartHhmmListBeforeEnd(openHhmm, closeHhmm, endHhmm) {
    const endM = hhmmToMinutes(endHhmm);
    return allowedStartHhmmList(openHhmm, closeHhmm).filter((s) => hhmmToMinutes(s) < endM);
}

/**
 * AVR/Lobby range: end-time options on `rangeEndDate` that fall after the composed start instant.
 *
 * @param {(kind: string, fields: Record<string, string>) => { start_at: string, end_at: string }} buildFn
 */
export function allowedEndHhmmListAvrRange(rangeStartDate, rangeStartTime, rangeEndDate, endWindow, buildFn) {
    const { start_at } = buildFn('avr_range', {
        rangeStartDate,
        rangeStartTime,
        rangeEndDate,
        rangeEndTime: endWindow.end,
    });
    const startMs = Date.parse(start_at);
    const markers = halfHourMarkersInclusive(endWindow.start, endWindow.end);
    return markers.filter((endHhmm) => {
        const { end_at } = buildFn('avr_range', {
            rangeStartDate,
            rangeStartTime,
            rangeEndDate,
            rangeEndTime: endHhmm,
        });
        return Date.parse(end_at) > startMs;
    });
}

function ymdToDayStartMs(ymd) {
    const [y, m, d] = ymd.split('-').map(Number);
    return Date.parse(`${y}-${pad2(m)}-${pad2(d)}T00:00:00${MANILA_OFFSET}`);
}

function addOneDayYmd(ymd) {
    const [y, m, d] = ymd.split('-').map(Number);
    const iso = `${y}-${pad2(m)}-${pad2(d)}T12:00:00${MANILA_OFFSET}`;
    return manilaYmdFromInstant(new Date(Date.parse(iso) + 86400000));
}

function compareYmd(a, b) {
    if (a === b) return 0;
    return a < b ? -1 : 1;
}

/**
 * True if [startIso, endIso) is not fully contained in daily operating windows (mirrors backend PolicyDocument).
 *
 * @param {string} startIso
 * @param {string} endIso
 * @param {{ day_start: string, day_end: string, weekend_day_start: string|null, weekend_day_end: string|null }} config
 */
export function reservationViolatesOperatingWindows(startIso, endIso, config) {
    const startMs = Date.parse(startIso);
    const endMs = Date.parse(endIso);
    if (!Number.isFinite(startMs) || !Number.isFinite(endMs) || !(endMs > startMs)) {
        return true;
    }

    let ymd = manilaYmdFromInstant(new Date(startMs));
    const endDayYmd = manilaYmdFromInstant(new Date(endMs));

    while (compareYmd(ymd, endDayYmd) <= 0) {
        const win = resolveOperatingWindowForYmd(config, ymd);
        const openM = hhmmToMinutes(win.start);
        const closeM = hhmmToMinutes(win.end);
        if (closeM <= openM) {
            return true;
        }

        const dayStartMs = ymdToDayStartMs(ymd);
        const nextMidnightMs = ymdToDayStartMs(addOneDayYmd(ymd));

        const overlapStart = Math.max(startMs, dayStartMs);
        const overlapEnd = Math.min(endMs, nextMidnightMs);

        if (overlapStart < overlapEnd) {
            const openBoundary = dayStartMs + openM * 60 * 1000;
            const closeBoundary = dayStartMs + closeM * 60 * 1000;
            if (overlapStart < openBoundary || overlapEnd > closeBoundary) {
                return true;
            }
        }

        ymd = addOneDayYmd(ymd);
    }

    return false;
}

/**
 * @param {'avr_range'|'half_hour_details'|'standard'} bookingKind
 * @param {Record<string, string>} fields
 * @param {{ day_start: string, day_end: string, weekend_day_start: string|null, weekend_day_end: string|null }} config
 * @param {(kind: string, fields: Record<string, string>) => { start_at: string, end_at: string }} buildStartEndPayloadFromWallClock
 */
export function operatingHoursWallClockError(bookingKind, fields, config, buildStartEndPayloadFromWallClock) {
    const { start_at, end_at } = buildStartEndPayloadFromWallClock(bookingKind, fields);
    if (reservationViolatesOperatingWindows(start_at, end_at, config)) {
        return 'The selected time is outside the library\'s operating hours.';
    }
    return '';
}

/**
 * Build hour/minute filter maps for HalfHourWallClockSelect.
 * @param {string[]|null|undefined} allowedHhmmList
 * @returns {Map<string, Set<string>>|null}
 */
export function allowedHalfHourChoiceMap(allowedHhmmList) {
    if (!allowedHhmmList || allowedHhmmList.length === 0) return null;
    const map = new Map();
    for (const raw of allowedHhmmList) {
        const t = String(raw).trim();
        if (!/^\d{2}:\d{2}$/.test(t)) continue;
        const [h, m] = t.split(':');
        if (m !== '00' && m !== '30') continue;
        if (!map.has(h)) map.set(h, new Set());
        map.get(h).add(m);
    }
    return map.size ? map : null;
}

/**
 * If current value is not in allowed list, return first allowed; otherwise return value.
 * @param {string} value
 * @param {string[]} allowed
 */
export function coerceHalfHourToAllowed(value, allowed) {
    if (!allowed.length) return value;
    const { hour, minute } = splitHalfHourWallClockHhmm(value);
    const norm = joinHalfHourWallClockHhmm(hour, minute);
    if (allowed.includes(norm)) return norm;
    return allowed[0];
}

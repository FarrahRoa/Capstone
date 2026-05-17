import { useEffect, useMemo } from 'react';
import { ui } from '../../theme';
import {
    formatReservationHourOption12h,
    formatReservationMinuteOptionLabel,
    joinHalfHourWallClockHhmm,
    RESERVATION_TIME_HOUR_CHOICES,
    RESERVATION_TIME_MINUTE_CHOICES,
    splitHalfHourWallClockHhmm,
} from '../../utils/halfHourWallClockInput';
import { allowedHalfHourChoiceMap, coerceHalfHourToAllowed } from '../../utils/operatingHours';

/**
 * Reservation wall-clock control: hour (24h) + minute, minutes limited to :00 / :30.
 * Replaces native time inputs so the minute control only ever offers :00 and :30 (not 00–59).
 *
 * Each select is associated with a native <label htmlFor> + matching id.
 * Default: sr-only label text (compact). Optional visibleFieldLabels: small visible Hour/Minute
 * labels for stricter audits (staff New Reservation).
 *
 * Optional `disabledReasonTitle` renders a wrapper with a native tooltip when business rules disable the picker.
 */
export default function HalfHourWallClockSelect({
    value,
    onChange,
    disabled,
    idPrefix,
    hourLabel = 'Hour',
    minuteLabel = 'Minute',
    visibleFieldLabels = false,
    /** When set, only these HH:mm half-hour times are selectable (e.g. within operating hours). */
    allowedHhmmList = null,
    /** Browser tooltip explaining why picks are blocked (shown when `disabled` is true). */
    disabledReasonTitle = null,
}) {
    const choiceMap = useMemo(() => allowedHalfHourChoiceMap(allowedHhmmList), [allowedHhmmList]);

    const allowedKey = allowedHhmmList?.length ? allowedHhmmList.join('|') : '';

    useEffect(() => {
        if (!allowedHhmmList || allowedHhmmList.length === 0) return;
        const next = coerceHalfHourToAllowed(value, allowedHhmmList);
        if (next !== value) onChange(next);
    }, [allowedKey, value, onChange, allowedHhmmList]);

    const hourChoices = useMemo(() => {
        if (!choiceMap) return RESERVATION_TIME_HOUR_CHOICES;
        return RESERVATION_TIME_HOUR_CHOICES.filter((hh) => choiceMap.has(hh));
    }, [choiceMap]);

    const { hour, minute } = splitHalfHourWallClockHhmm(value);
    const minuteChoices = useMemo(() => {
        if (!choiceMap) return RESERVATION_TIME_MINUTE_CHOICES;
        const set = choiceMap.get(hour);
        if (!set) return RESERVATION_TIME_MINUTE_CHOICES;
        return RESERVATION_TIME_MINUTE_CHOICES.filter((mm) => set.has(mm));
    }, [choiceMap, hour]);

    const safeMinute = minuteChoices.includes(minute) ? minute : minuteChoices[0] ?? '00';

    const idH = idPrefix ? `${idPrefix}-hour` : undefined;
    const idM = idPrefix ? `${idPrefix}-minute` : undefined;

    const wrapIfPolicy = (node) =>
        disabled && disabledReasonTitle ? (
            <div className="rounded-md opacity-65 cursor-not-allowed" title={disabledReasonTitle} aria-disabled="true">
                {node}
            </div>
        ) : (
            node
        );

    if (visibleFieldLabels) {
        return wrapIfPolicy(
            <div className="grid w-full grid-cols-[minmax(0,1fr)_auto_4.75rem] gap-x-1.5 gap-y-1">
                <label htmlFor={idH} className="text-xs font-medium text-slate-700">
                    Hour
                </label>
                <span
                    className="col-start-2 row-start-1 row-span-2 flex items-center justify-center text-slate-500 text-sm shrink-0 select-none"
                    aria-hidden="true"
                >
                    :
                </span>
                <label htmlFor={idM} className="text-xs font-medium text-slate-700">
                    Minute
                </label>
                <select
                    id={idH}
                    disabled={disabled}
                    className={`min-w-0 w-full ${ui.select}`}
                    value={hour}
                    onChange={(e) => onChange(joinHalfHourWallClockHhmm(e.target.value, safeMinute))}
                >
                    {hourChoices.map((hh) => (
                        <option key={hh} value={hh}>
                            {formatReservationHourOption12h(hh)}
                        </option>
                    ))}
                </select>
                <select
                    id={idM}
                    disabled={disabled}
                    className={`min-w-0 w-full ${ui.select}`}
                    value={safeMinute}
                    onChange={(e) => onChange(joinHalfHourWallClockHhmm(hour, e.target.value))}
                >
                    {minuteChoices.map((mm) => (
                        <option key={mm} value={mm}>
                            {formatReservationMinuteOptionLabel(mm)}
                        </option>
                    ))}
                </select>
            </div>,
        );
    }

    return wrapIfPolicy(
        <div className="flex items-center gap-1.5 w-full">
            <label className="min-w-0 flex-1" htmlFor={idH}>
                <span className="sr-only">{hourLabel}</span>
                <select
                    id={idH}
                    disabled={disabled}
                    className={`w-full ${ui.select}`}
                    value={hour}
                    onChange={(e) => onChange(joinHalfHourWallClockHhmm(e.target.value, safeMinute))}
                >
                    {hourChoices.map((hh) => (
                        <option key={hh} value={hh}>
                            {formatReservationHourOption12h(hh)}
                        </option>
                    ))}
                </select>
            </label>
            <span className="text-slate-500 text-sm shrink-0 select-none" aria-hidden="true">
                :
            </span>
            <label className="min-w-0 w-[4.75rem] shrink-0" htmlFor={idM}>
                <span className="sr-only">{minuteLabel}</span>
                <select
                    id={idM}
                    disabled={disabled}
                    className={`w-full ${ui.select}`}
                    value={safeMinute}
                    onChange={(e) => onChange(joinHalfHourWallClockHhmm(hour, e.target.value))}
                >
                    {minuteChoices.map((mm) => (
                        <option key={mm} value={mm}>
                            {formatReservationMinuteOptionLabel(mm)}
                        </option>
                    ))}
                </select>
            </label>
        </div>,
    );
}

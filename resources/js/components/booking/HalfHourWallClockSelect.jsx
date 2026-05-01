import { ui } from '../../theme';
import {
    joinHalfHourWallClockHhmm,
    RESERVATION_TIME_HOUR_CHOICES,
    RESERVATION_TIME_MINUTE_CHOICES,
    splitHalfHourWallClockHhmm,
} from '../../utils/halfHourWallClockInput';

/**
 * Reservation wall-clock control: hour (24h) + minute, minutes limited to :00 / :30.
 * Replaces native time inputs so the minute control only ever offers :00 and :30 (not 00–59).
 *
 * Each select is associated with a native <label htmlFor> + matching id.
 * Default: sr-only label text (compact). Optional visibleFieldLabels: small visible Hour/Minute
 * labels for stricter audits (staff New Reservation).
 */
export default function HalfHourWallClockSelect({
    value,
    onChange,
    disabled,
    idPrefix,
    hourLabel = 'Hour',
    minuteLabel = 'Minute',
    visibleFieldLabels = false,
}) {
    const { hour, minute } = splitHalfHourWallClockHhmm(value);
    const idH = idPrefix ? `${idPrefix}-hour` : undefined;
    const idM = idPrefix ? `${idPrefix}-minute` : undefined;

    if (visibleFieldLabels) {
        return (
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
                    onChange={(e) => onChange(joinHalfHourWallClockHhmm(e.target.value, minute))}
                >
                    {RESERVATION_TIME_HOUR_CHOICES.map((hh) => (
                        <option key={hh} value={hh}>
                            {hh}
                        </option>
                    ))}
                </select>
                <select
                    id={idM}
                    disabled={disabled}
                    className={`min-w-0 w-full ${ui.select}`}
                    value={minute}
                    onChange={(e) => onChange(joinHalfHourWallClockHhmm(hour, e.target.value))}
                >
                    {RESERVATION_TIME_MINUTE_CHOICES.map((mm) => (
                        <option key={mm} value={mm}>
                            {mm}
                        </option>
                    ))}
                </select>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-1.5 w-full">
            <label className="min-w-0 flex-1" htmlFor={idH}>
                <span className="sr-only">{hourLabel}</span>
                <select
                    id={idH}
                    disabled={disabled}
                    className={`w-full ${ui.select}`}
                    value={hour}
                    onChange={(e) => onChange(joinHalfHourWallClockHhmm(e.target.value, minute))}
                >
                    {RESERVATION_TIME_HOUR_CHOICES.map((hh) => (
                        <option key={hh} value={hh}>
                            {hh}
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
                    value={minute}
                    onChange={(e) => onChange(joinHalfHourWallClockHhmm(hour, e.target.value))}
                >
                    {RESERVATION_TIME_MINUTE_CHOICES.map((mm) => (
                        <option key={mm} value={mm}>
                            {mm}
                        </option>
                    ))}
                </select>
            </label>
        </div>
    );
}

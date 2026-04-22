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
 */
export default function HalfHourWallClockSelect({ value, onChange, disabled, idPrefix }) {
    const { hour, minute } = splitHalfHourWallClockHhmm(value);
    const idH = idPrefix ? `${idPrefix}-hour` : undefined;
    const idM = idPrefix ? `${idPrefix}-minute` : undefined;

    return (
        <div className="flex items-center gap-1.5 w-full" role="group" aria-label="Time, 24-hour, half-hour steps">
            <select
                id={idH}
                disabled={disabled}
                className={`min-w-0 flex-1 ${ui.select}`}
                value={hour}
                onChange={(e) => onChange(joinHalfHourWallClockHhmm(e.target.value, minute))}
            >
                {RESERVATION_TIME_HOUR_CHOICES.map((hh) => (
                    <option key={hh} value={hh}>
                        {hh}
                    </option>
                ))}
            </select>
            <span className="text-slate-500 text-sm shrink-0 select-none" aria-hidden="true">
                :
            </span>
            <select
                id={idM}
                disabled={disabled}
                className={`min-w-0 w-[4.75rem] shrink-0 ${ui.select}`}
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

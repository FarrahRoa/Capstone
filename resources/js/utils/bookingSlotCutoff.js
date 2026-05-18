/** Institutional submission cutoff — Manila wall clock (current time only on server). */
export const BOOKING_CUTOFF_HHMM = '16:30';

export const BOOKING_CUTOFF_MINUTES = 16 * 60 + 30;

/** Submissions reopen at 9:00 AM Manila (matches PolicyDocument::SUBMISSION_CUTOFF_RESUME_*). */
export const SUBMISSION_RESUME_MINUTES = 9 * 60;

export function wallClockToMinutes(hhmm) {
    const [h, m] = String(hhmm || '00:00').split(':').map((x) => Number(x));
    if (!Number.isFinite(h) || !Number.isFinite(m)) return 0;
    return h * 60 + m;
}

export function isWallClockAtOrAfterBookingCutoff(hhmm) {
    return wallClockToMinutes(hhmm) >= BOOKING_CUTOFF_MINUTES;
}

export function isSlotStartAtOrAfterBookingCutoff(hour, minute) {
    return hour * 60 + minute >= BOOKING_CUTOFF_MINUTES;
}

export function formatHhmm12(hhmm) {
    const [h, m] = String(hhmm || '00:00').split(':').map((x) => Number(x));
    if (!Number.isFinite(h) || !Number.isFinite(m)) return hhmm;
    const d = new Date(2000, 0, 1, h, m, 0);
    return new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(d);
}

export function reservationCutoffValidationMessage() {
    return 'Reservations are closed for today. You may reserve again starting 9:00 AM tomorrow.';
}

/**
 * True when the submission window is closed (4:30 PM–9:00 AM Manila). Uses ref clock only.
 *
 * @param {Date} [refDate]
 */
export function isPastReservationSubmissionCutoff(refDate = new Date()) {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Manila',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(refDate);
    const hh = Number(parts.find((p) => p.type === 'hour')?.value ?? 0);
    const mm = Number(parts.find((p) => p.type === 'minute')?.value ?? 0);
    const mins = hh * 60 + mm;
    return mins >= BOOKING_CUTOFF_MINUTES || mins < SUBMISSION_RESUME_MINUTES;
}

/** @deprecated Use {@link reservationCutoffValidationMessage}. */
export function cutoffBlackoutMessage() {
    return reservationCutoffValidationMessage();
}

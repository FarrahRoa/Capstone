/**
 * Shared wall-clock rules for new + edit reservation flows.
 * Run: npm run test:reservation-times
 */
import assert from 'node:assert/strict';
import {
    bookingKindFromSpace,
    buildStartEndPayloadFromWallClock,
    halfHourHhmmFromOptionalQueryParam,
    HALF_HOUR_HHMM_RE,
    initialWallClockFieldsFromReservation,
    validateHalfHourTimesForKind,
    wallClockFieldsFromInstants,
} from '../../resources/js/utils/reservationBookingTimes.js';

assert.equal(bookingKindFromSpace({ slug: 'avr', type: 'avr' }), 'avr_range');
assert.equal(bookingKindFromSpace({ slug: 'lobby', type: 'lobby' }), 'avr_range');
assert.equal(bookingKindFromSpace({ slug: 'x', type: 'confab' }), 'half_hour_details');
assert.equal(bookingKindFromSpace({ slug: 'study-a', type: 'boardroom' }), 'standard');

assert.ok(HALF_HOUR_HHMM_RE.test('09:00'));
assert.ok(HALF_HOUR_HHMM_RE.test('09:30'));
assert.ok(!HALF_HOUR_HHMM_RE.test('09:15'));

assert.equal(halfHourHhmmFromOptionalQueryParam('09:00'), '09:00');
assert.equal(halfHourHhmmFromOptionalQueryParam('9:30'), '09:30');
assert.equal(halfHourHhmmFromOptionalQueryParam('09:15'), null);
assert.equal(halfHourHhmmFromOptionalQueryParam('09:45'), null);
assert.equal(halfHourHhmmFromOptionalQueryParam(null), null);

const startIso = '2026-04-12T09:00:00+08:00';
const endIso = '2026-04-12T11:30:00+08:00';
const res = {
    start_at: startIso,
    end_at: endIso,
    space: { slug: 'study-1', type: 'boardroom' },
};
const init = initialWallClockFieldsFromReservation(res);
assert.equal(init.kind, 'standard');
assert.equal(init.date, '2026-04-12');
assert.equal(init.startTime, '09:00');
assert.equal(init.endTime, '11:30');
assert.equal(validateHalfHourTimesForKind(init.kind, init), null);

const payload = buildStartEndPayloadFromWallClock(init.kind, init);
assert.equal(payload.start_at, '2026-04-12T09:00:00+08:00');
assert.equal(payload.end_at, '2026-04-12T11:30:00+08:00');

const bad = { ...init, endTime: '11:15' };
assert.ok(validateHalfHourTimesForKind(bad.kind, bad));

const confabFields = wallClockFieldsFromInstants('half_hour_details', startIso, endIso);
assert.equal(confabFields.kind, 'half_hour_details');
assert.equal(confabFields.rangeStartTime, '09:00');
assert.equal(confabFields.rangeEndTime, '11:30');
const confabPayload = buildStartEndPayloadFromWallClock('half_hour_details', confabFields);
assert.equal(confabPayload.start_at, '2026-04-12T09:00:00+08:00');
assert.equal(confabPayload.end_at, '2026-04-12T11:30:00+08:00');

const avrFields = wallClockFieldsFromInstants(
    'avr_range',
    '2026-04-12T09:30:00+08:00',
    '2026-04-13T16:00:00+08:00',
);
assert.equal(avrFields.rangeStartDate, '2026-04-12');
assert.equal(avrFields.rangeEndDate, '2026-04-13');
assert.equal(avrFields.rangeStartTime, '09:30');
assert.equal(avrFields.rangeEndTime, '16:00');

console.log('reservationBookingTimes tests: ok');

/**
 * Half-hour wall-clock picker data (hour list + minute :00/:30 only).
 * Run: npm run test:half-hour-input
 */
import assert from 'node:assert/strict';
import {
    joinHalfHourWallClockHhmm,
    RESERVATION_TIME_HOUR_CHOICES,
    RESERVATION_TIME_MINUTE_CHOICES,
    splitHalfHourWallClockHhmm,
} from '../../resources/js/utils/halfHourWallClockInput.js';

assert.deepEqual(RESERVATION_TIME_MINUTE_CHOICES, ['00', '30']);
assert.equal(RESERVATION_TIME_HOUR_CHOICES.length, 24);
assert.equal(RESERVATION_TIME_HOUR_CHOICES[0], '00');
assert.equal(RESERVATION_TIME_HOUR_CHOICES[23], '23');

assert.deepEqual(splitHalfHourWallClockHhmm('09:00'), { hour: '09', minute: '00' });
assert.deepEqual(splitHalfHourWallClockHhmm('09:30'), { hour: '09', minute: '30' });
assert.deepEqual(splitHalfHourWallClockHhmm('9:30'), { hour: '09', minute: '30' });
assert.deepEqual(splitHalfHourWallClockHhmm('09:15'), { hour: '09', minute: '00' });

assert.equal(joinHalfHourWallClockHhmm('14', '00'), '14:00');
assert.equal(joinHalfHourWallClockHhmm('14', '30'), '14:30');
assert.equal(joinHalfHourWallClockHhmm('14', '45'), '14:00');

console.log('halfHourWallClockInput tests: ok');

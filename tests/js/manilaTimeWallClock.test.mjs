/**
 * Node smoke tests for Manila wall-clock 12-hour display (schedule gutters + slot labels).
 * Run: npm run test:manila-clock
 */
import assert from 'node:assert/strict';
import { formatManilaSlotGutterTimes, formatManilaWallTime12 } from '../../resources/js/utils/manilaTime.js';

assert.equal(formatManilaWallTime12(9, 0), '9:00 AM');
assert.equal(formatManilaWallTime12(9, 30), '9:30 AM');
assert.equal(formatManilaWallTime12(12, 0), '12:00 PM');
assert.equal(formatManilaWallTime12(13, 0), '1:00 PM');
assert.equal(formatManilaWallTime12(17, 30), '5:30 PM');
assert.equal(formatManilaWallTime12(19, 0), '7:00 PM');

const standard = { hourStart: 17, hourEnd: 18 };
const gStandard = formatManilaSlotGutterTimes(standard, false);
assert.equal(gStandard.start, formatManilaWallTime12(17, 15));
assert.equal(gStandard.end, formatManilaWallTime12(18, 0));
assert.match(gStandard.start, /AM|PM$/);
assert.match(gStandard.end, /AM|PM$/);
assert.ok(!gStandard.start.includes('17:'), 'gutter must not show raw 24-hour time');
assert.ok(!gStandard.end.includes('18:'), 'gutter must not show raw 24-hour time');

const half = { hourStart: 16, minuteStart: 30, hourEnd: 17, minuteEnd: 0 };
const gHalf = formatManilaSlotGutterTimes(half, true);
assert.equal(gHalf.start, formatManilaWallTime12(16, 30));
assert.equal(gHalf.end, formatManilaWallTime12(17, 0));

console.log('manilaTimeWallClock tests: ok');

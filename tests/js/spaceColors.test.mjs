/**
 * Space color keys: Confab pool + assignable rooms must share one legend/calendar color.
 * Run: npm run test:space-colors
 */
import assert from 'node:assert/strict';
import {
    colorForOperationalSpaceId,
    colorForSpaceId,
    stableOperationalSpaceColorKey,
    stableSpaceColorKey,
} from '../../resources/js/utils/spaceColors.js';

assert.equal(stableSpaceColorKey({ id: 1, slug: 'confab-pool', type: 'confab', name: 'Confab' }), 'confab');
assert.equal(stableSpaceColorKey({ id: 2, slug: 'confab-1', type: 'confab', name: 'Confab' }), 'confab');
assert.equal(
    stableOperationalSpaceColorKey({ id: 2, slug: 'confab-1', type: 'confab', name: 'Confab' }),
    'confab-1'
);
assert.equal(
    stableSpaceColorKey({ id: 3, slug: 'medical-confab-1', type: 'medical_confab', name: 'Medical Confab 1' }),
    'medical_confab'
);

const spaces = [
    { id: 10, slug: 'avr', type: 'avr', name: 'AVR' },
    { id: 20, slug: 'confab-pool', type: 'confab', name: 'Confab' },
    { id: 21, slug: 'confab-1', type: 'confab', name: 'Confab' },
    { id: 30, slug: 'medical-confab-1', type: 'medical_confab', name: 'Medical Confab 1' },
    { id: 31, slug: 'medical-confab-2', type: 'medical_confab', name: 'Medical Confab 2' },
];

const cPool = colorForSpaceId(20, spaces);
const cRoom = colorForSpaceId(21, spaces);
assert.equal(cPool.bg, cRoom.bg);
assert.equal(cPool.ring, cRoom.ring);

const oPool = colorForOperationalSpaceId(20, spaces);
const oRoom = colorForOperationalSpaceId(21, spaces);
assert.notEqual(oPool.bg, oRoom.bg);
assert.notEqual(oPool.ring, oRoom.ring);

const m1 = colorForSpaceId(30, spaces);
const m2 = colorForSpaceId(31, spaces);
assert.equal(m1.bg, m2.bg);
assert.equal(m1.ring, m2.ring);

console.log('spaceColors tests: ok');

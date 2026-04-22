import assert from 'node:assert/strict';
import { spaceGuidelinesDetailRows } from '../../resources/js/utils/spaceGuidelineDisplay.js';

const rows = spaceGuidelinesDetailRows({
    capacity: 10,
    guideline_details: {
        location: 'AVR',
        whiteboard_count: 1,
        projector_count: 0,
        computer_count: 2,
        internet_options: ['LAN Cable', 'School Wifi'],
    },
});
const labels = rows.map((r) => r.label);
assert.ok(labels.includes('Whiteboard'));
assert.ok(labels.includes('Projector'));
assert.equal(rows.find((r) => r.label === 'Whiteboard')?.value, '1');
assert.equal(rows.find((r) => r.label === 'Projector')?.value, '0');
assert.equal(rows.find((r) => r.label === 'Computer')?.value, '2');
assert.equal(rows.find((r) => r.label === 'Internet')?.value, 'LAN Cable, School Wifi');

console.log('spaceGuidelineDisplay tests: ok');

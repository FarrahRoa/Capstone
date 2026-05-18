import assert from 'node:assert/strict';
import {
    EMPLOYEE_LIBRARY_MAX_MINUTES,
    maxBookingMinutesFor,
    spaceAppliesLibraryDurationCap,
    STUDENT_MAX_MINUTES,
} from '../../resources/js/utils/reservationDurationPolicy.js';

const employee = { user_type: 'faculty_staff', role: { slug: 'faculty' } };
const student = { user_type: 'student', role: { slug: 'student' } };
const admin = { user_type: 'faculty_staff', role: { slug: 'admin' }, is_admin: true };

assert.equal(spaceAppliesLibraryDurationCap({ type: 'avr' }), false);
assert.equal(spaceAppliesLibraryDurationCap({ type: 'lobby' }), false);
assert.equal(spaceAppliesLibraryDurationCap({ type: 'confab' }), true);
assert.equal(spaceAppliesLibraryDurationCap({ type: 'lecture' }), true);

assert.equal(maxBookingMinutesFor(employee, { type: 'confab' }), EMPLOYEE_LIBRARY_MAX_MINUTES);
assert.equal(maxBookingMinutesFor(employee, { type: 'avr' }), null);
assert.equal(maxBookingMinutesFor(employee, { type: 'lobby' }), null);
assert.equal(maxBookingMinutesFor(student, { type: 'lecture' }), STUDENT_MAX_MINUTES);
assert.equal(maxBookingMinutesFor(admin, { type: 'lecture' }), null);

console.log('reservationDurationPolicy.test.mjs: ok');

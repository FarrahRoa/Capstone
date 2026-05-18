/**
 * Per-reservation duration caps — must stay aligned with App\Support\ReservationDurationPolicy.
 */

const LIBRARY_SPACE_TYPES = new Set(['confab', 'medical_confab', 'boardroom', 'lecture']);
const EXEMPT_SPACE_TYPES = new Set(['avr', 'lobby']);

export const STUDENT_MAX_MINUTES = 120;
export const EMPLOYEE_LIBRARY_MAX_MINUTES = 180;

/**
 * @param {{ type?: string, slug?: string }|null|undefined} space
 */
export function spaceAppliesLibraryDurationCap(space) {
    if (!space) {
        return false;
    }
    const type = String(space.type || '').toLowerCase();
    if (EXEMPT_SPACE_TYPES.has(type)) {
        return false;
    }
    return LIBRARY_SPACE_TYPES.has(type);
}

/**
 * @param {{ role?: { slug?: string }, user_type?: string, is_admin?: boolean }|null|undefined} user
 * @param {{ type?: string, slug?: string }|null|undefined} space
 * @returns {number|null} Max minutes, or null when no cap.
 */
export function maxBookingMinutesFor(user, space) {
    if (!user || !space) {
        return null;
    }

    const roleSlug = String(user.role?.slug || '').toLowerCase();
    if (roleSlug === 'admin' || user.is_admin === true) {
        return null;
    }

    if (!spaceAppliesLibraryDurationCap(space)) {
        return null;
    }

    const userType = String(user.user_type || '').toLowerCase();
    if (userType === 'student') {
        return STUDENT_MAX_MINUTES;
    }
    if (userType === 'faculty_staff' || userType === 'employee') {
        return EMPLOYEE_LIBRARY_MAX_MINUTES;
    }

    return null;
}

/**
 * @param {number} maxMinutes
 */
export function durationLimitMessage(maxMinutes) {
    const maxHours = maxMinutes / 60;
    return `You have exceeded your maximum booking limit of ${maxHours} hours for your account type.`;
}

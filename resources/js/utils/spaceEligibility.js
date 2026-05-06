export const SPACE_TYPE_MEDICAL_CONFAB = 'medical_confab';
export const SPACE_TYPE_BOARDROOM = 'boardroom';

export function getSpaceRestrictionLabel(space) {
    if (!space) return '';
    if (space.type === SPACE_TYPE_MEDICAL_CONFAB) {
        return 'Restricted: eligible med users only';
    }
    if (space.type === SPACE_TYPE_BOARDROOM) {
        return 'Restricted: Office of the President & OVPHE only';
    }
    return '';
}

function isStaffOrAdmin(user) {
    const slug = String(user?.role?.slug || '').toLowerCase();
    return slug === 'admin' || slug === 'librarian' || slug === 'student_assistant';
}

export function getSpaceIneligibilityMessage(space, user) {
    if (!space) return '';
    if (isStaffOrAdmin(user)) return '';

    const userType = String(user?.user_type || '').toLowerCase();

    if (userType === 'student') {
        if (space.type === 'confab') return '';
        if (space.type === SPACE_TYPE_MEDICAL_CONFAB) {
            return user?.med_confab_eligible
                ? ''
                : 'Your account type or affiliation does not have permission to reserve this specific space.';
        }
        return 'Your account type or affiliation does not have permission to reserve this specific space.';
    }

    if (space.type === SPACE_TYPE_BOARDROOM) {
        const officeId = user?.office_id;
        const officeName = String(user?.office?.name || user?.college_office || '').trim();
        const okByName =
            officeName === 'Office of the President' ||
            officeName === 'Office of the Vice-President Higher Education' ||
            officeName === 'Office of the Vice President for Higher Education (OVPHE)' ||
            officeName === 'OVPHE';
        // Prefer ID if present; otherwise name fallback.
        if (officeId == null && okByName) return '';
        if (officeId != null && okByName) return '';
        return 'Boardroom is restricted to OP/OVPHE only.';
    }

    return '';
}

export function isUserEligibleForSpace(user, space) {
    if (!space) return true;
    return getSpaceIneligibilityMessage(space, user) === '';
}

/** Matches {@link BookingCalendar} slot grid: all library spaces use :00 / :30 boundaries. */
export function spaceUsesHalfHourSlots(space) {
    return Boolean(space);
}

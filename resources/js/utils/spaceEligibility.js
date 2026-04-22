export const SPACE_TYPE_MEDICAL_CONFAB = 'medical_confab';
export const SPACE_TYPE_BOARDROOM = 'boardroom';

export function getSpaceRestrictionLabel(space) {
    if (!space) return '';
    if (space.type === SPACE_TYPE_MEDICAL_CONFAB) {
        return 'Restricted: eligible med users only';
    }
    if (space.type === SPACE_TYPE_BOARDROOM) {
        return 'Restricted: Office of the President & OVP Higher Education only';
    }
    return '';
}

export function getSpaceIneligibilityMessage(space) {
    if (!space) return '';
    if (space.type === SPACE_TYPE_MEDICAL_CONFAB) {
        return 'Only eligible med users can reserve Med Confab.';
    }
    if (space.type === SPACE_TYPE_BOARDROOM) {
        return 'Only authorized Office of the President and Office of the Vice-President Higher Education users can reserve Boardroom.';
    }
    return '';
}

export function isUserEligibleForSpace(user, space) {
    if (!space) return true;
    if (space.type === SPACE_TYPE_MEDICAL_CONFAB) {
        return Boolean(user?.med_confab_eligible);
    }
    if (space.type === SPACE_TYPE_BOARDROOM) {
        const office = String(user?.college_office || '').trim();
        return office === 'Office of the President' || office === 'Office of the Vice-President Higher Education';
    }
    return true;
}

/** Matches {@link BookingCalendar} slot grid: all library spaces use :00 / :30 boundaries. */
export function spaceUsesHalfHourSlots(space) {
    return Boolean(space);
}

import { SPACE_TYPE_CONFAB, SPACE_TYPE_MEDICAL_CONFAB } from './userFacingSpaceName';

export const STUDENT_CONFAB_ONLY_MESSAGE = 'Students may only reserve Confab spaces.';

/** Matches {@link User::hasStudentRole()} — role slug `student` only. */
export function isStudentRoleUser(user) {
    return String(user?.role?.slug || '').toLowerCase() === 'student';
}

/** Confab assignment pool only (bookable Confab slot for students). */
export function studentRoleMayReserveSpace(space) {
    return Boolean(space?.type === SPACE_TYPE_CONFAB && space?.is_confab_pool);
}

/** Numbered Confab rooms shown in the student dashboard image preview (Confab 1–6). */
export function isNumberedConfabShowcaseSpace(space) {
    if (!space || space.type !== SPACE_TYPE_CONFAB || space.is_confab_pool) {
        return false;
    }
    const slug = String(space.slug || '').toLowerCase();
    if (/^confab-[1-6]$/.test(slug)) {
        return true;
    }
    const name = String(space.name || '').trim();
    return /^Confab [1-6]$/i.test(name);
}

/** Medical Confab rooms for the student image preview when the account is med-confab eligible. */
export function isMedicalConfabShowcaseSpace(space) {
    return space?.type === SPACE_TYPE_MEDICAL_CONFAB;
}

/**
 * Spaces eligible for the Library spaces carousel on the student Home Dashboard only.
 *
 * @param {object|null|undefined} user
 * @param {object|null|undefined} space
 */
export function studentShowcaseMayDisplaySpace(user, space) {
    if (!isStudentRoleUser(user) || !space) {
        return false;
    }
    if (isNumberedConfabShowcaseSpace(space)) {
        return true;
    }
    if (isMedicalConfabShowcaseSpace(space)) {
        return Boolean(user.med_confab_eligible);
    }
    return false;
}

/**
 * @param {Array<object>|null|undefined} spaces
 * @returns {Array<object>}
 */
export function filterSpacesForStudentRole(spaces) {
    if (!Array.isArray(spaces)) return [];
    if (!spaces.length) return [];
    return spaces.filter((s) => studentRoleMayReserveSpace(s));
}

/**
 * Image preview carousel list for student dashboard (Confab 1–6 + medical confabs when eligible).
 *
 * @param {object|null|undefined} user
 * @param {Array<object>|null|undefined} spaces full active space list from the API
 * @returns {Array<object>}
 */
export function filterSpacesForStudentShowcase(user, spaces) {
    if (!isStudentRoleUser(user) || !Array.isArray(spaces)) {
        return [];
    }
    return spaces.filter((s) => studentShowcaseMayDisplaySpace(user, s));
}

/**
 * @param {object|null|undefined} user
 * @param {object|null|undefined} space
 */
export function getStudentRoleSpaceBlockMessage(user, space) {
    if (!isStudentRoleUser(user) || !space) return '';
    if (studentRoleMayReserveSpace(space)) return '';
    if (space.type === SPACE_TYPE_CONFAB && !space.is_confab_pool) {
        return 'Reserve the general Confab slot; a specific confab room is assigned when staff approves your request.';
    }
    return STUDENT_CONFAB_ONLY_MESSAGE;
}

/**
 * Booking calendar / reservation lists (Confab pool only for students).
 *
 * @param {object|null|undefined} user
 * @param {Array<object>|null|undefined} spaces
 */
export function applyStudentRoleSpaceFilter(user, spaces) {
    if (!isStudentRoleUser(user)) return Array.isArray(spaces) ? spaces : [];
    return filterSpacesForStudentRole(spaces);
}

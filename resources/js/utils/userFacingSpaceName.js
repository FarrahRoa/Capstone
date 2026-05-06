/** Matches {@see \App\Models\Space::TYPE_CONFAB} */
export const SPACE_TYPE_CONFAB = 'confab';

/** Matches {@see \App\Models\Space::TYPE_MEDICAL_CONFAB} */
export const SPACE_TYPE_MEDICAL_CONFAB = 'medical_confab';

/**
 * End-user label for schedule UI (mirrors Space::userFacingName() on the API).
 */
export function userFacingSpaceName(space) {
    if (!space) return '';
    if (space.type === SPACE_TYPE_CONFAB && space.is_confab_pool) return 'Confab';
    return space.name || '';
}

/**
 * Legend / read-only labels: generic Confab family names only (no room numbers on public/student calendar).
 */
export function scheduleBoardLabelFromSpace(space) {
    return userFacingSpaceName(space);
}

/**
 * Legend rows for the schedule board.
 *
 * @param {Array<object>} spaces active spaces including physical Confab rows
 */
export function dedupeConfabFamilyForLegend(spaces) {
    if (!Array.isArray(spaces)) return [];
    return spaces.filter((s) => s && !s.is_confab_pool);
}

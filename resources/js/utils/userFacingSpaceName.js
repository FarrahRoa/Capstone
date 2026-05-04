/** Matches {@see \App\Models\Space::TYPE_CONFAB} */
export const SPACE_TYPE_CONFAB = 'confab';

/**
 * End-user label for schedule UI (mirrors Space::userFacingName() on the API).
 */
export function userFacingSpaceName(space) {
    if (!space) return '';
    if (space.type === SPACE_TYPE_CONFAB) return 'Confab';
    return space.name || '';
}

/**
 * Distinct labels for the public schedule board (numbered Confab rooms, pool label, etc.).
 */
export function scheduleBoardLabelFromSpace(space) {
    if (!space) return '';
    if (space.type === SPACE_TYPE_CONFAB && !space.is_confab_pool) {
        const rn = space.record_name != null ? String(space.record_name).trim() : '';
        return rn || space.name || 'Confab';
    }
    return userFacingSpaceName(space);
}

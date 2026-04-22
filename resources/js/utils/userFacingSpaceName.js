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

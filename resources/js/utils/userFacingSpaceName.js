/** Matches {@see \App\Models\Space::TYPE_CONFAB} */
export const SPACE_TYPE_CONFAB = 'confab';

/** Matches {@see \App\Models\Space::TYPE_MEDICAL_CONFAB} */
export const SPACE_TYPE_MEDICAL_CONFAB = 'medical_confab';

/**
 * End-user label for schedule UI (mirrors Space::userFacingName() on the API).
 */
export function userFacingSpaceName(space) {
    if (!space) return '';
    if (space.type === SPACE_TYPE_MEDICAL_CONFAB) return 'Medical Confab';
    if (space.type === SPACE_TYPE_CONFAB) return 'Confab';
    return space.name || '';
}

/**
 * Legend / read-only labels: generic Confab family names only (no room numbers on public/student calendar).
 */
export function scheduleBoardLabelFromSpace(space) {
    return userFacingSpaceName(space);
}

/**
 * One legend row per logical Confab/Med Confab family for masked calendars (admins pass through full list elsewhere).
 *
 * @param {Array<object>} spaces active spaces including physical Confab rows
 */
export function dedupeConfabFamilyForLegend(spaces) {
    if (!Array.isArray(spaces)) return [];
    let confabRow = null;
    let medicalRow = null;
    const out = [];
    for (const s of spaces) {
        if (!s || s.is_confab_pool) continue;
        if (s.type === SPACE_TYPE_CONFAB) {
            if (!confabRow) {
                confabRow = s;
                out.push(s);
            }
            continue;
        }
        if (s.type === SPACE_TYPE_MEDICAL_CONFAB) {
            if (!medicalRow) {
                medicalRow = s;
                out.push(s);
            }
            continue;
        }
        out.push(s);
    }
    return out;
}

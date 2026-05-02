/** Human-readable labels for {@link Space} `type` (matches admin Space management). */
const TYPE_LABELS = {
    avr: 'AVR',
    lobby: 'Lobby',
    boardroom: 'Boardroom',
    medical_confab: 'Medical Confab',
    confab: 'Confab',
    lecture: 'Lecture Space',
};

/**
 * @param {string|undefined|null} type
 * @returns {string}
 */
export function spaceTypeLabel(type) {
    if (type == null || type === '') return '';
    return TYPE_LABELS[type] || String(type);
}

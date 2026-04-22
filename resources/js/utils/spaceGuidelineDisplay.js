/** @type {{ key: string, label: string }[]} */
export const SPACE_GUIDELINE_COUNT_FIELDS = [
    { key: 'whiteboard_count', label: 'Whiteboard' },
    { key: 'projector_count', label: 'Projector' },
    { key: 'computer_count', label: 'Computer' },
    { key: 'dvd_player_count', label: 'DVD player' },
    { key: 'sound_system_count', label: 'Sound system' },
];

/**
 * @param {{ capacity?: number|null, guideline_details?: Record<string, unknown>|null }|null|undefined} space
 * @returns {{ label: string, value: string }[]}
 */
export function spaceGuidelinesDetailRows(space) {
    if (!space) {
        return [];
    }
    const d = space.guideline_details && typeof space.guideline_details === 'object' ? space.guideline_details : {};
    /** @type {{ label: string, value: string }[]} */
    const rows = [];
    if (d.location && String(d.location).trim() !== '') {
        rows.push({ label: 'Location', value: String(d.location).trim() });
    }
    const cap = space.capacity != null && space.capacity !== '' ? Number(space.capacity) : NaN;
    if (Number.isFinite(cap) && cap > 0) {
        let v = String(cap);
        if (d.seating_capacity_note && String(d.seating_capacity_note).trim() !== '') {
            v += ` — ${String(d.seating_capacity_note).trim()}`;
        }
        rows.push({ label: 'Seating capacity', value: v });
    } else if (d.seating_capacity_note && String(d.seating_capacity_note).trim() !== '') {
        rows.push({ label: 'Seating capacity', value: String(d.seating_capacity_note).trim() });
    }

    SPACE_GUIDELINE_COUNT_FIELDS.forEach(({ key, label }) => {
        if (!Object.prototype.hasOwnProperty.call(d, key)) {
            return;
        }
        const n = Number(d[key]);
        if (!Number.isFinite(n)) {
            return;
        }
        rows.push({ label, value: String(Math.max(0, Math.trunc(n))) });
    });

    const io = d.internet_options;
    if (Array.isArray(io) && io.length > 0) {
        const parts = io.map((x) => String(x).trim()).filter(Boolean);
        if (parts.length > 0) {
            rows.push({ label: 'Internet', value: parts.join(', ') });
        }
    }

    if (d.others && String(d.others).trim() !== '') {
        rows.push({ label: 'Other notes', value: String(d.others).trim() });
    }
    return rows;
}

/**
 * @param {{ capacity?: number|null, guideline_details?: Record<string, unknown>|null }|null|undefined} space
 */
export function spaceGuidelinesHasDetails(space) {
    return spaceGuidelinesDetailRows(space).length > 0;
}

import { spaceTypeLabel } from './spaceTypeLabel';

/**
 * Short secondary line for the space showcase: type label and optional location from guidelines.
 *
 * @param {{ type?: string, guideline_details?: Record<string, unknown>|null }|null|undefined} space
 * @returns {string}
 */
export function spaceShowcaseSubtitle(space) {
    if (!space) return '';
    const typePart = spaceTypeLabel(space.type);
    const d = space.guideline_details && typeof space.guideline_details === 'object' ? space.guideline_details : {};
    const loc = d.location != null && String(d.location).trim() !== '' ? String(d.location).trim() : '';
    if (typePart && loc) return `${typePart} · ${loc}`;
    if (loc) return loc;
    return typePart;
}

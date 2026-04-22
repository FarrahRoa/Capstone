/**
 * Admin/operational schedule label.
 * - Confab rooms should be specific (Confab 1..N)
 * - Confab pool should be clearly labeled
 * - Everything else uses its provided name
 */
export function operationalSpaceLabel(space) {
    if (!space) return '';
    const name = String(space.name || '').trim();
    const slug = String(space.slug || '').trim().toLowerCase();
    const type = String(space.type || '').trim().toLowerCase();

    if (type === 'confab') {
        if (space.is_confab_pool || slug === 'confab-pool') return 'Confab (pool)';
        const m = slug.match(/^confab-(\d+)$/);
        if (m) return `Confab ${m[1]}`;
        // If the backend already sent a specific name, keep it.
        if (/^confab\s+\d+$/i.test(name)) return name;
        return 'Confab';
    }

    if (type === 'medical_confab') {
        const mm = slug.match(/^medical-confab-(\d+)$/);
        if (mm) return `Medical Confab ${mm[1]}`;
        return name || 'Medical Confab';
    }

    return name;
}


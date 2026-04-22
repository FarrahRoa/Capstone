const PALETTE = [
    // Distinct hues for legend dots/chips on a light UI.
    // Keep saturation moderate so it doesn't fight xu branding.
    { bg: 'bg-sky-500', ring: 'ring-sky-500/30' },
    { bg: 'bg-indigo-500', ring: 'ring-indigo-500/30' },
    { bg: 'bg-violet-500', ring: 'ring-violet-500/30' },
    { bg: 'bg-fuchsia-500', ring: 'ring-fuchsia-500/30' },
    { bg: 'bg-rose-500', ring: 'ring-rose-500/30' },
    { bg: 'bg-red-500', ring: 'ring-red-500/30' },
    { bg: 'bg-orange-500', ring: 'ring-orange-500/30' },
    { bg: 'bg-amber-500', ring: 'ring-amber-500/30' },
    { bg: 'bg-yellow-500', ring: 'ring-yellow-500/30' },
    { bg: 'bg-lime-600', ring: 'ring-lime-600/30' },
    { bg: 'bg-green-600', ring: 'ring-green-600/30' },
    { bg: 'bg-emerald-500', ring: 'ring-emerald-500/30' },
    { bg: 'bg-teal-600', ring: 'ring-teal-600/30' },
    { bg: 'bg-cyan-600', ring: 'ring-cyan-600/30' },
    { bg: 'bg-blue-600', ring: 'ring-blue-600/30' },
    { bg: 'bg-slate-600', ring: 'ring-slate-600/30' },
    { bg: 'bg-pink-500', ring: 'ring-pink-500/30' },
    { bg: 'bg-purple-600', ring: 'ring-purple-600/30' },
    { bg: 'bg-stone-600', ring: 'ring-stone-600/30' },
    { bg: 'bg-emerald-700', ring: 'ring-emerald-700/30' },
];

function hashStringToInt(str) {
    // Stable, small hash (djb2-ish).
    let h = 5381;
    const s = String(str ?? '');
    for (let i = 0; i < s.length; i++) {
        h = ((h << 5) + h) ^ s.charCodeAt(i);
    }
    return Math.abs(h);
}

function stableSpaceKey(space) {
    return String(space?.slug || space?.name || space?.id || '').trim().toLowerCase();
}

/**
 * Key used for palette index: one color per logical space family so user-facing labels stay consistent.
 * (All standard Confab rooms + pool share "Confab"; slug differs per row.)
 */
export function stableSpaceColorKey(space) {
    if (!space || typeof space !== 'object') {
        return stableSpaceKey(space);
    }
    const t = space.type;
    if (t === 'confab') {
        return 'confab';
    }
    if (t === 'medical_confab') {
        return 'medical_confab';
    }
    return stableSpaceKey(space);
}

/**
 * Operational/admin key: one color per concrete space record (Confab rooms do NOT collapse).
 * This is used for admin schedule monitoring where Confab 1..N must be visually distinct.
 */
export function stableOperationalSpaceColorKey(space) {
    return stableSpaceKey(space);
}

function colorByIndex(idx) {
    const safe = Number.isFinite(idx) && idx >= 0 ? idx : 0;
    return PALETTE[safe % PALETTE.length];
}

/**
 * Stable mapping for a space to a color token pair.
 * Prefers slug/name (more stable across environments) but falls back to id.
 */
export function colorForSpace(space) {
    // Fallback when we don't have the full spaces list: deterministic hash.
    const key = stableSpaceColorKey(space);
    const idx = hashStringToInt(key) % PALETTE.length;
    return colorByIndex(idx);
}

export function colorForSpaceId(spaceId, spaces = []) {
    const s = spaces.find((x) => String(x.id) === String(spaceId));
    if (!s || !Array.isArray(spaces) || spaces.length === 0) {
        return colorForSpace(s ?? { id: spaceId });
    }

    // Preferred: index-based assignment over unique logical keys (Confab pool + rooms share one key).
    const keys = [...new Set(spaces.map((sp) => stableSpaceColorKey(sp)).filter(Boolean))].sort((a, b) =>
        a.localeCompare(b)
    );

    const key = stableSpaceColorKey(s);
    const idx = Math.max(0, keys.indexOf(key));
    return colorByIndex(idx);
}

/**
 * Admin schedule overview mapping: unique colors per space record (Confab 1..N distinct).
 */
export function colorForOperationalSpaceId(spaceId, spaces = []) {
    const s = spaces.find((x) => String(x.id) === String(spaceId));
    if (!s || !Array.isArray(spaces) || spaces.length === 0) {
        const key = stableOperationalSpaceColorKey(s ?? { id: spaceId });
        const idx = hashStringToInt(key) % PALETTE.length;
        return colorByIndex(idx);
    }

    const keys = [...new Set(spaces.map((sp) => stableOperationalSpaceColorKey(sp)).filter(Boolean))].sort((a, b) =>
        a.localeCompare(b)
    );
    const key = stableOperationalSpaceColorKey(s);
    const idx = Math.max(0, keys.indexOf(key));
    return colorByIndex(idx);
}


/**
 * Format `affiliation_next_change_on` (Y-m-d) for account settings helper text.
 */
export function formatAffiliationNextChangeLabel(ymd) {
    if (!ymd || typeof ymd !== 'string') {
        return '';
    }
    const [y, m, d] = ymd.split('-').map(Number);
    if (!y || !m || !d) {
        return ymd;
    }
    const date = new Date(y, m - 1, d);
    return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

/**
 * Phase 10 API envelope helpers.
 * Paginated responses keep Laravel's shape ({ data: rows, total, current_page, ... }).
 * Non-paginated success bodies use { data: payload }.
 */

export function isPaginatorPayload(body) {
    return (
        body != null &&
        typeof body === 'object' &&
        'data' in body &&
        ('total' in body || 'current_page' in body || 'per_page' in body)
    );
}

/**
 * Rows array for a paginated list (Laravel LengthAwarePaginator JSON).
 */
export function paginatorRows(body) {
    if (!isPaginatorPayload(body)) return [];
    return Array.isArray(body.data) ? body.data : [];
}

/**
 * Unwrap { data: T } when T is not a paginator. Returns inner payload or the body if unwrapped.
 */
export function unwrapData(body) {
    if (body == null || typeof body !== 'object') return body;
    if (isPaginatorPayload(body)) return body;
    if ('data' in body && body.data !== undefined) return body.data;
    return body;
}

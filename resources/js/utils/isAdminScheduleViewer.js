/**
 * Who sees the read-only all-spaces schedule on Dashboard + /calendar (tabs, no room-first booking).
 *
 * Prefer permission `reservation.view_all` for librarian/admin-style operators.
 * Student assistants are excluded: they keep the normal booking calendar and use the reservation queue for oversight.
 *
 * Also allow explicit Admin role slug when permissions are not yet hydrated.
 */
export function isAdminScheduleViewer(user, hasPermission) {
    if (String(user?.role?.slug || '').toLowerCase() === 'student_assistant') {
        return false;
    }
    if (typeof hasPermission === 'function' && hasPermission('reservation.view_all')) {
        return true;
    }
    return String(user?.role?.slug || '').toLowerCase() === 'admin';
}

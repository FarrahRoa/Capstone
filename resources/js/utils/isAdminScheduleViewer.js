/**
 * Who sees the read-only all-spaces schedule on Dashboard + /calendar (tabs, no room-first booking).
 *
 * Prefer permission `reservation.view_all` (matches admin/librarian/student_assistant in config/permissions.php)
 * so we do not depend on `user.role.slug` being present in every API/client shape.
 *
 * Also allow explicit Admin role slug when permissions are not yet hydrated.
 */
export function isAdminScheduleViewer(user, hasPermission) {
    if (typeof hasPermission === 'function' && hasPermission('reservation.view_all')) {
        return true;
    }
    return String(user?.role?.slug || '').toLowerCase() === 'admin';
}

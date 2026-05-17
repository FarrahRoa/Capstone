/** Any one of these grants access to the /admin route shell (child routes still enforce specifics). */
export const ADMIN_ROUTE_PERMISSIONS = [
    'reservation.view_all',
    'reports.view',
    'spaces.manage',
    'users.manage',
    'policies.manage',
    'system.cloud_sync',
];

/**
 * @param {(permission: string) => boolean} hasPermission
 */
export function hasAnyAdminTool(hasPermission) {
    return ADMIN_ROUTE_PERMISSIONS.some((p) => hasPermission(p));
}

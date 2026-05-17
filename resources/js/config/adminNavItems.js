/**
 * Admin hamburger navigation — every `to` MUST stay under `/admin/*`
 * so React Router keeps AdminLayout mounted (never user Layout.jsx).
 *
 * @typedef {{ label: string; to: string; permission: string | null; matchPaths?: string[] }} AdminNavItem
 */

/** @type {AdminNavItem[]} */
export const ADMIN_NAV_ITEMS = [
    { label: 'Dashboard', to: '/admin/dashboard', permission: null },
    { label: 'Calendar', to: '/admin/calendar', permission: 'calendar.view' },
    { label: 'Reservation Queue', to: '/admin/reservations', permission: 'reservation.view_all' },
    { label: 'Reports', to: '/admin/reports', permission: 'reports.view' },
    { label: 'Spaces', to: '/admin/spaces', permission: 'spaces.manage' },
    { label: 'User Management', to: '/admin/users', permission: 'users.manage' },
    { label: 'Guidelines', to: '/admin/policies', permission: 'policies.manage' },
    { label: 'Operating Hours', to: '/admin/operating-hours', permission: 'policies.manage' },
    { label: 'Dean Emails', to: '/admin/dean-emails', permission: 'policies.manage' },
    { label: 'Colleges & Offices', to: '/admin/organizations', permission: 'users.manage' },
    { label: 'Cloud sync', to: '/admin/cloud-sync', permission: 'system.cloud_sync' },
];

/**
 * @param {(permission: string) => boolean} hasPermission
 * @returns {AdminNavItem[]}
 */
export function getVisibleAdminNavItems(hasPermission) {
    return ADMIN_NAV_ITEMS.filter((item) => {
        if (item.permission === null) {
            return true;
        }
        return hasPermission(item.permission);
    });
}

/**
 * @param {string} pathname
 * @param {AdminNavItem} item
 */
export function isAdminNavItemActive(pathname, item) {
    const paths = item.matchPaths ?? [item.to];
    return paths.some((p) => pathname === p || pathname.startsWith(`${p}/`));
}

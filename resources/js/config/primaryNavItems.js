/**
 * Single source of truth for primary app navigation (desktop top bar + admin hamburger drawer).
 * @typedef {{ label: string; to: string; permission: string; matchPaths?: string[] }} PrimaryNavItem
 */

/** @type {PrimaryNavItem[]} */
export const PRIMARY_NAV_ITEMS = [
    { label: 'Calendar', to: '/calendar', permission: 'calendar.view' },
    { label: 'New Reservation', to: '/reserve', permission: 'reservation.create' },
    { label: 'My Reservations', to: '/my-reservations', permission: 'reservation.view_own' },
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
 * @returns {PrimaryNavItem[]}
 */
export function getVisiblePrimaryNavItems(hasPermission) {
    return PRIMARY_NAV_ITEMS.filter((item) => hasPermission(item.permission));
}

/**
 * @param {string} pathname
 * @param {PrimaryNavItem} item
 */
export function isPrimaryNavItemActive(pathname, item) {
    const paths = item.matchPaths ?? [item.to];
    return paths.some((p) => pathname === p || pathname.startsWith(`${p}/`));
}

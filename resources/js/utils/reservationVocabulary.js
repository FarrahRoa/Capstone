export const reservationStatusMeta = {
    email_verification_pending: {
        label: 'Pending verification',
        badgeClass: 'bg-amber-50 text-amber-900 border border-amber-200',
    },
    pending_approval: {
        label: 'Pending approval',
        badgeClass: 'bg-xu-primary/10 text-xu-primary border border-xu-primary/20',
    },
    approved: {
        label: 'Approved',
        badgeClass: 'bg-xu-secondary/10 text-xu-secondary border border-xu-secondary/25',
    },
    rejected: {
        label: 'Rejected',
        badgeClass: 'bg-red-50 text-red-800 border border-red-200',
    },
    cancelled: {
        label: 'Cancelled',
        badgeClass: 'bg-slate-100 text-slate-700 border border-slate-200',
    },
};

export const reservationActionLabels = {
    create: 'Created',
    approve: 'Approved',
    reject: 'Rejected',
    cancel: 'Cancelled',
    override: 'Override approved',
};

export function getReservationStatusLabel(status) {
    return reservationStatusMeta[status]?.label || status;
}

export function getReservationStatusBadgeClass(status) {
    return reservationStatusMeta[status]?.badgeClass || 'bg-slate-100 text-slate-700 border border-slate-200';
}

export function getReservationActionLabel(action) {
    return reservationActionLabels[action] || action;
}

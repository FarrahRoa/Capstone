/**
 * Shared UI class strings for XU institutional theme.
 * Colors come from Tailwind @theme in resources/css/app.css (xu-*).
 */
export const ui = {
    pageBg: 'min-h-screen bg-xu-page',
    pageCenter: 'min-h-screen flex items-center justify-center bg-xu-page px-4',
    input:
        'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-900 placeholder:text-slate-400 focus:border-xu-secondary focus:ring-2 focus:ring-xu-secondary/35 outline-none transition-shadow',
    select:
        'rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:border-xu-secondary focus:ring-2 focus:ring-xu-secondary/35 outline-none',
    btnPrimary:
        'bg-xu-primary text-white py-2 px-4 rounded-lg font-medium shadow-sm hover:bg-xu-secondary disabled:opacity-50 transition-colors',
    btnPrimaryFull: 'w-full bg-xu-primary text-white py-2 rounded-lg font-medium shadow-sm hover:bg-xu-secondary disabled:opacity-50 transition-colors',
    card: 'bg-white rounded-xl border border-slate-200/90 shadow-lg',
    cardFlat: 'bg-white rounded-xl border border-slate-200/90 shadow-sm',
    pageTitle: 'text-2xl font-bold text-xu-primary font-serif tracking-tight',
    sectionLabel: 'text-xs font-semibold text-xu-secondary uppercase tracking-wide',
    linkAccent: 'text-xu-secondary font-medium hover:text-xu-primary hover:underline',
};

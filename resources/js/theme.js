/**
 * Shared UI class strings for XU institutional theme.
 * Colors come from Tailwind @theme in resources/css/app.css (xu-*).
 */
export const ui = {
    pageBg: 'min-h-screen bg-xu-page',
    pageCenter: 'min-h-screen min-w-0 flex items-center justify-center bg-xu-page px-4',
    input:
        'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-[15px] leading-snug text-slate-900 placeholder:text-slate-400 focus:border-xu-secondary focus:ring-2 focus:ring-xu-secondary/35 outline-none transition-shadow',
    select:
        'rounded-lg border border-slate-200 bg-white px-3 py-2 text-[15px] leading-snug text-slate-900 focus:border-xu-secondary focus:ring-2 focus:ring-xu-secondary/35 outline-none',
    btnPrimary:
        'inline-flex min-h-[44px] touch-manipulation items-center justify-center rounded-lg bg-xu-primary px-4 py-2 text-[15px] font-medium text-white shadow-sm transition-colors hover:bg-xu-secondary disabled:opacity-50 sm:min-h-0',
    btnPrimaryFull:
        'w-full min-h-[44px] touch-manipulation rounded-lg bg-xu-primary py-2.5 text-[15px] font-medium text-white shadow-sm transition-colors hover:bg-xu-secondary disabled:opacity-50 sm:min-h-0 sm:py-2',
    btnSecondaryFull:
        'w-full min-h-[44px] touch-manipulation rounded-lg border border-slate-200 bg-white py-2.5 text-[15px] font-medium text-xu-primary shadow-sm transition-colors hover:border-xu-secondary/50 hover:bg-xu-page/40 disabled:opacity-50 sm:min-h-0 sm:py-2',
    card: 'bg-white rounded-xl border border-slate-200/90 shadow-lg',
    cardFlat: 'bg-white rounded-xl border border-slate-200/90 shadow-sm',
    pageTitle: 'text-2xl sm:text-3xl font-bold text-xu-primary font-serif tracking-tight',
    sectionLabel: 'text-sm font-semibold text-xu-secondary uppercase tracking-wide',
    linkAccent: 'text-xu-secondary font-medium hover:text-xu-primary hover:underline',
    helperText: 'text-sm text-slate-600 leading-relaxed',
    finePrint: 'text-xs text-slate-600 leading-relaxed',
};

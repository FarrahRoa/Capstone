import {
    formatManilaHalfHourSlotLabel,
    manilaTimeParamFromHour,
} from '../../utils/manilaTime';
import { getReservationStatusLabel } from '../../utils/reservationVocabulary';
import { userFacingSpaceName } from '../../utils/userFacingSpaceName';
import AvailableBookingSlotCard from './AvailableBookingSlotCard';

function reservationForSlot(slot, reservedSlots, dateYmd) {
    const list = Array.isArray(reservedSlots) ? reservedSlots : [];
    if (!slot) return null;

    let slotStart;
    let slotEnd;
    if (slot.start_at && slot.end_at) {
        slotStart = new Date(slot.start_at).getTime();
        slotEnd = new Date(slot.end_at).getTime();
    } else if (
        dateYmd &&
        Number.isFinite(slot.hourStart) &&
        Number.isFinite(slot.minuteStart) &&
        Number.isFinite(slot.hourEnd) &&
        Number.isFinite(slot.minuteEnd)
    ) {
        const pad = (n) => String(n).padStart(2, '0');
        slotStart = Date.parse(`${dateYmd}T${pad(slot.hourStart)}:${pad(slot.minuteStart)}:00+08:00`);
        slotEnd = Date.parse(`${dateYmd}T${pad(slot.hourEnd)}:${pad(slot.minuteEnd)}:00+08:00`);
    } else {
        return null;
    }
    for (const r of list) {
        const rs = new Date(r.start_at).getTime();
        const re = new Date(r.end_at).getTime();
        if (slotStart < re && slotEnd > rs) {
            return r;
        }
    }
    return null;
}

function slotOccupiedStatusLabel(reservation) {
    if (!reservation?.status) return 'Occupied';
    return getReservationStatusLabel(reservation.status);
}

const slotCardRow =
    'flex min-h-[5.25rem] w-full min-w-0 flex-row items-stretch justify-between gap-3 rounded-xl border px-4 py-3';

/**
 * User Dashboard (Student & Employee): scrollable time-slot column only.
 */
export default function UserDashboardSlotsPanel({
    visibleSlots,
    selectedSpaceId,
    selectedYmd,
    selectedSpace,
    reservedSlots,
    schedulingRulesBlockBookings,
    activeLeadPolicyBlockMessage,
}) {
    const spaceLabel = selectedSpace ? userFacingSpaceName(selectedSpace) : 'room';

    return (
        <section className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
            <ul
                className="m-0 min-w-0 flex-1 list-none space-y-3 overflow-y-auto overflow-x-hidden px-4 py-4 sm:px-5 [scrollbar-width:thin]"
                role="list"
                aria-label={`Time slots for ${spaceLabel} on ${selectedYmd}`}
            >
                {!Array.isArray(visibleSlots) || visibleSlots.length === 0 ? (
                    <li className="list-none px-2 py-8 text-center text-sm text-slate-500">
                        Schedule currently unavailable. Please contact administration.
                    </li>
                ) : null}
                {(Array.isArray(visibleSlots) ? visibleSlots : []).map((slot) => {
                    const label = formatManilaHalfHourSlotLabel(
                        slot.hourStart,
                        slot.minuteStart,
                        slot.hourEnd,
                        slot.minuteEnd
                    );
                    const reserveUrl = `/reserve?space_id=${selectedSpaceId}&date=${selectedYmd}&start_time=${manilaTimeParamFromHour(
                        slot.hourStart,
                        slot.minuteStart
                    )}&end_time=${manilaTimeParamFromHour(slot.hourEnd, slot.minuteEnd)}`;
                    const rowKey = `${selectedSpaceId}-${selectedYmd}-${slot.hourStart}-${slot.minuteStart}`;
                    const r = slot.available ? null : reservationForSlot(slot, reservedSlots, selectedYmd);

                    if (slot.bookingCutoffBlocked) {
                        return (
                            <li key={rowKey} className="list-none">
                                <article
                                    title="Reservations cannot start at or after 4:30 PM"
                                    className={`${slotCardRow} cursor-not-allowed border-slate-300/90 bg-slate-100/90 shadow-inner`}
                                >
                                    <section className="flex min-w-0 flex-1 flex-col justify-center">
                                        <p className="text-sm font-semibold text-slate-600">{label}</p>
                                        <p className="mt-1 text-xs font-medium text-slate-500">
                                            Not available · after 4:30 PM cutoff
                                        </p>
                                    </section>
                                    <span className="shrink-0 self-end rounded-md border border-slate-400/60 bg-slate-200/80 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                        Closed
                                    </span>
                                </article>
                            </li>
                        );
                    }

                    if (!slot.available) {
                        return (
                            <li key={rowKey} className="list-none">
                                <article className={`${slotCardRow} border-slate-300/90 bg-slate-100/90 shadow-inner`}>
                                    <section className="flex min-w-0 flex-1 flex-col justify-center">
                                        <p className="text-sm font-semibold text-slate-800">{label}</p>
                                        <p className="mt-1 text-xs font-medium text-slate-600">
                                            {slotOccupiedStatusLabel(r)}
                                        </p>
                                    </section>
                                    <span className="shrink-0 self-end rounded-md border border-slate-400/50 bg-slate-200/80 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-700">
                                        Occupied
                                    </span>
                                </article>
                            </li>
                        );
                    }

                    return (
                        <li key={rowKey} className="list-none min-w-0">
                            <AvailableBookingSlotCard
                                label={label}
                                reserveUrl={reserveUrl}
                                spaceLabel={spaceLabel}
                                bookDisabled={schedulingRulesBlockBookings}
                                disabledTitle={activeLeadPolicyBlockMessage}
                                layout="row"
                            />
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

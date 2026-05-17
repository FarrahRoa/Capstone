import { useCallback, useEffect, useState } from 'react';
import api from '../api';
import { unwrapData } from './apiEnvelope';

const SYNC_MS = 30000;

/**
 * Manila booking policy clock synced from the server (not the client OS clock).
 */
export function useBookingPolicyClock() {
    const [clock, setClock] = useState(() => new Date());

    const sync = useCallback(() => {
        api.get('/policies/booking-clock')
            .then(({ data }) => {
                const payload = unwrapData(data);
                const iso = payload?.now_iso;
                if (!iso) return;
                const parsed = new Date(iso);
                if (!Number.isNaN(parsed.getTime())) {
                    setClock(parsed);
                }
            })
            .catch(() => {
                /* Keep last known server time; avoid trusting client clock on failure. */
            });
    }, []);

    useEffect(() => {
        sync();
        const id = window.setInterval(sync, SYNC_MS);
        return () => window.clearInterval(id);
    }, [sync]);

    return clock;
}

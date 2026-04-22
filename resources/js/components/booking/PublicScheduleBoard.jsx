import { useEffect, useState } from 'react';
import api from '../../api';
import { unwrapData } from '../../utils/apiEnvelope';
import BookingCalendar from './BookingCalendar';

export default function PublicScheduleBoard() {
    const [spaces, setSpaces] = useState([]);
    const [spacesLoadError, setSpacesLoadError] = useState(false);

    useEffect(() => {
        let cancelled = false;
        api.get('/spaces')
            .then(({ data }) => {
                if (cancelled) return;
                const list = unwrapData(data);
                setSpaces(Array.isArray(list) ? list : []);
                setSpacesLoadError(false);
            })
            .catch(() => {
                if (cancelled) return;
                setSpaces([]);
                setSpacesLoadError(true);
            });

        return () => {
            cancelled = true;
        };
    }, []);

    return (
        <BookingCalendar
            user={null}
            spaces={spaces}
            spacesLoadError={spacesLoadError}
            embedded
            readOnly
        />
    );
}


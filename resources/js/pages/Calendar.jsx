import { useState, useEffect } from 'react';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import BookingCalendar from '../components/booking/BookingCalendar';

export default function Calendar() {
    const { user } = useAuth();
    const [spaces, setSpaces] = useState([]);
    const [spacesLoadError, setSpacesLoadError] = useState(false);

    useEffect(() => {
        api.get('/spaces')
            .then(({ data }) => {
                setSpaces(data);
                setSpacesLoadError(false);
            })
            .catch(() => {
                setSpaces([]);
                setSpacesLoadError(true);
            });
    }, []);

    return (
        <div>
            <h1 className="sr-only">Calendar – room availability</h1>
            <BookingCalendar user={user} spaces={spaces} spacesLoadError={spacesLoadError} />
        </div>
    );
}

import { useState, useEffect } from 'react';
import api from '../api';
import { unwrapData } from '../utils/apiEnvelope';
import { useAuth } from '../contexts/AuthContext';
import BookingCalendar from '../components/booking/BookingCalendar';
import AdminScheduleOverview from '../components/booking/AdminScheduleOverview';
import { isAdminScheduleViewer } from '../utils/isAdminScheduleViewer';
import { applyStudentRoleSpaceFilter } from '../utils/studentSpaceAccess';

export default function Calendar() {
    const { user, hasPermission } = useAuth();
    const adminSchedule = isAdminScheduleViewer(user, hasPermission);
    const [spaces, setSpaces] = useState([]);
    const [spacesLoadError, setSpacesLoadError] = useState(false);

    useEffect(() => {
        api.get('/spaces', { params: adminSchedule ? { operational: 1 } : {} })
            .then(({ data }) => {
                const list = unwrapData(data);
                const raw = Array.isArray(list) ? list : [];
                setSpaces(applyStudentRoleSpaceFilter(user, raw));
                setSpacesLoadError(false);
            })
            .catch(() => {
                setSpaces([]);
                setSpacesLoadError(true);
            });
    }, [adminSchedule, user]);

    return (
        <div>
            <h1 className="sr-only">{adminSchedule ? 'Calendar – all spaces schedule overview' : 'Calendar – room availability'}</h1>
            {adminSchedule ? (
                <AdminScheduleOverview spaces={spaces} spacesLoadError={spacesLoadError} />
            ) : (
                <BookingCalendar user={user} spaces={spaces} spacesLoadError={spacesLoadError} />
            )}
        </div>
    );
}

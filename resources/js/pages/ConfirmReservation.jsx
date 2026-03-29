import { useState, useEffect } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import api from '../api';
import { ui } from '../theme';

export default function ConfirmReservation() {
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token');
    const [message, setMessage] = useState('');
    const [success, setSuccess] = useState(null);

    useEffect(() => {
        if (!token) {
            setSuccess(false);
            setMessage('Invalid confirmation link.');
            return;
        }
        api.post('/reservations/confirm-email', { token })
            .then(() => {
                setSuccess(true);
                setMessage('Reservation confirmed. It is now pending admin approval.');
            })
            .catch((err) => {
                setSuccess(false);
                setMessage(err.response?.data?.message || 'Confirmation failed.');
            });
    }, [token]);

    if (success === null && token)
        return (
            <div className={ui.pageCenter}>
                <p className="text-xu-primary font-medium">Confirming…</p>
            </div>
        );

    return (
        <div className={ui.pageCenter}>
            <div className={`w-full max-w-md p-8 text-center ${ui.card}`}>
                <h1 className={`${ui.pageTitle} mb-4`}>Email confirmation</h1>
                <p className={success ? 'text-green-800' : 'text-red-700'}>{message}</p>
                <Link to="/calendar" className={`inline-block mt-6 ${ui.linkAccent}`}>
                    Back to calendar
                </Link>
            </div>
        </div>
    );
}

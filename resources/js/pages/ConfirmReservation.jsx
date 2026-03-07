import { useState, useEffect } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import api from '../api';

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

    if (success === null && token) return <div className="min-h-screen flex items-center justify-center"><p className="text-slate-600">Confirming...</p></div>;

    return (
        <div className="min-h-screen flex items-center justify-center bg-slate-100 px-4">
            <div className="w-full max-w-md bg-white rounded-xl shadow-lg p-8 text-center">
                <h1 className="text-2xl font-bold text-slate-800 mb-4">Email confirmation</h1>
                <p className={success ? 'text-green-600' : 'text-red-600'}>{message}</p>
                <Link to="/" className="inline-block mt-6 text-slate-800 font-medium hover:underline">Back to calendar</Link>
            </div>
        </div>
    );
}

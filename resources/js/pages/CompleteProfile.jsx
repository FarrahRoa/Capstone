import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { ui } from '../theme';
import { FACULTY_OFFICES, STUDENT_COLLEGES } from '../constants/affiliationOptions';

export default function CompleteProfile() {
    const { user, refreshUser } = useAuth();
    const navigate = useNavigate();
    const [name, setName] = useState(user?.name || '');
    const [collegeOffice, setCollegeOffice] = useState(user?.college_office || '');
    const [mobileNumber, setMobileNumber] = useState(user?.mobile_number || '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const typeLabel = user?.user_type === 'student'
        ? 'Student'
        : user?.user_type === 'faculty_staff'
            ? 'Employee/Staff'
            : 'User';

    const options = useMemo(() => {
        if (user?.user_type === 'student') return STUDENT_COLLEGES;
        if (user?.user_type === 'faculty_staff') return FACULTY_OFFICES;
        return [];
    }, [user?.user_type]);

    const unitLabel = user?.user_type === 'student' ? 'College' : 'Department/Office';

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSaving(true);
        try {
            await api.post('/me/profile', {
                name,
                college_office: collegeOffice,
                mobile_number: mobileNumber,
            });
            await refreshUser();
            sessionStorage.removeItem('xu_profile_completion_after_otp');
            navigate('/', { replace: true });
        } catch (err) {
            setError(
                err.response?.data?.errors?.college_office?.[0]
                || err.response?.data?.errors?.mobile_number?.[0]
                || err.response?.data?.errors?.name?.[0]
                || err.response?.data?.message
                || 'Could not save profile.'
            );
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className={ui.pageCenter}>
            <div className={`w-full max-w-lg p-8 ${ui.card}`}>
                <h1 className={`${ui.pageTitle} mb-2`}>Complete your profile</h1>
                <p className="text-slate-600 mb-6 text-sm">
                    Before you continue, please confirm your details. Account type: <span className="font-medium">{typeLabel}</span>
                </p>

                {error && (
                    <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg mb-4">
                        {error}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Full name</label>
                        <input
                            type="text"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            required
                            className={ui.input}
                            placeholder="e.g. Juan Dela Cruz"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Mobile number</label>
                        <input
                            type="tel"
                            value={mobileNumber}
                            onChange={(e) => setMobileNumber(e.target.value)}
                            required
                            className={ui.input}
                            placeholder="e.g. 09171234567"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">{unitLabel}</label>
                        <select
                            value={collegeOffice}
                            onChange={(e) => setCollegeOffice(e.target.value)}
                            required
                            className={ui.select}
                            disabled={options.length === 0}
                        >
                            <option value="" disabled>
                                Select one…
                            </option>
                            {options.map((opt) => (
                                <option key={opt} value={opt}>{opt}</option>
                            ))}
                        </select>
                        {options.length === 0 && (
                            <p className="text-xs text-slate-600 mt-1">
                                Your account type could not be determined. Please log out and log in again.
                            </p>
                        )}
                    </div>

                    <button type="submit" disabled={saving || options.length === 0} className={ui.btnPrimaryFull}>
                        {saving ? 'Saving…' : 'Save and continue'}
                    </button>
                </form>
            </div>
        </div>
    );
}


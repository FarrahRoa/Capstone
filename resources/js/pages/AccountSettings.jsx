import { useEffect, useMemo, useState } from 'react';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { unwrapData } from '../utils/apiEnvelope';
import { formatAffiliationNextChangeLabel } from '../utils/affiliationChangePolicy';
import { ui } from '../theme';

function isAdminPortalUser(user) {
    const slug = user?.role?.slug;
    return slug === 'admin' || slug === 'librarian' || slug === 'student_assistant';
}

function isAffiliationManagedUser(user) {
    return user?.user_type === 'student' || user?.user_type === 'faculty_staff';
}

export default function AccountSettings() {
    const { user, refreshUser } = useAuth();
    const adminPortal = isAdminPortalUser(user);
    const affiliationManaged = isAffiliationManagedUser(user);
    const [name, setName] = useState(user?.name || '');
    const [email, setEmail] = useState(user?.email || '');
    const [mobileNumber, setMobileNumber] = useState(user?.mobile_number || '');
    const [collegeId, setCollegeId] = useState(user?.college_id ? String(user.college_id) : '');
    const [officeId, setOfficeId] = useState(user?.office_id ? String(user.office_id) : '');
    const [colleges, setColleges] = useState([]);
    const [offices, setOffices] = useState([]);
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [saved, setSaved] = useState(false);

    useEffect(() => {
        setName(user?.name || '');
        setEmail(user?.email || '');
        setMobileNumber(user?.mobile_number || '');
        setCollegeId(user?.college_id ? String(user.college_id) : '');
        setOfficeId(user?.office_id ? String(user.office_id) : '');
        setCurrentPassword('');
        setNewPassword('');
        setConfirmPassword('');
    }, [user?.name, user?.email, user?.mobile_number, user?.college_id, user?.office_id]);

    useEffect(() => {
        if (!affiliationManaged) {
            return;
        }
        api.get('/affiliations')
            .then(({ data }) => {
                const payload = unwrapData(data);
                setColleges(Array.isArray(payload?.colleges) ? payload.colleges : []);
                setOffices(Array.isArray(payload?.offices) ? payload.offices : []);
            })
            .catch(() => {
                setColleges([]);
                setOffices([]);
            });
    }, [affiliationManaged]);

    const emailChanged = useMemo(() => {
        const a = (email || '').trim().toLowerCase();
        const b = (user?.email || '').trim().toLowerCase();
        return a !== b;
    }, [email, user?.email]);

    const wantsPasswordChange = useMemo(() => (newPassword || '').trim().length > 0, [newPassword]);

    const needsCurrentPassword = adminPortal && (emailChanged || wantsPasswordChange);

    const affiliationEligible = user?.affiliation_change_eligible !== false;
    const affiliationOptions = user?.user_type === 'student' ? colleges : offices;
    const affiliationValue = user?.user_type === 'student' ? collegeId : officeId;
    const affiliationLabel = user?.user_type === 'student' ? 'College' : 'Department/Office';

    const affiliationNextChangeLabel = useMemo(
        () => formatAffiliationNextChangeLabel(user?.affiliation_next_change_on),
        [user?.affiliation_next_change_on]
    );

    const affiliationChanged = useMemo(() => {
        if (!affiliationManaged || !affiliationEligible) {
            return false;
        }
        if (user?.user_type === 'student') {
            return String(user?.college_id || '') !== String(collegeId || '');
        }
        if (user?.user_type === 'faculty_staff') {
            return String(user?.office_id || '') !== String(officeId || '');
        }
        return false;
    }, [affiliationManaged, affiliationEligible, user, collegeId, officeId]);

    const typeLabel = useMemo(() => {
        if (adminPortal) {
            return user?.role?.name || 'Staff';
        }
        if (user?.user_type === 'student') return 'Student';
        if (user?.user_type === 'faculty_staff') return 'Employee/Staff';
        return user?.role?.name || 'User';
    }, [adminPortal, user?.role?.name, user?.user_type]);

    const helpText = adminPortal
        ? 'Update your name, email, or password. Your role is read-only. Changing your email or setting a new password requires your current password.'
        : affiliationManaged
          ? 'Update your name, mobile number, and college or office (once per month). Email and role are read-only.'
          : 'Update your name and mobile number. Email, role, and affiliation are read-only.';

    const firstErrorLine = (err) => {
        const errors = err.response?.data?.errors;
        if (errors && typeof errors === 'object') {
            for (const key of Object.keys(errors)) {
                const msg = errors[key]?.[0];
                if (msg) return msg;
            }
        }
        return err.response?.data?.message || 'Could not update account.';
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSaved(false);
        setSaving(true);
        try {
            const payload = adminPortal
                ? {
                      name,
                      email: email.trim(),
                      ...(needsCurrentPassword ? { current_password: currentPassword } : {}),
                      ...(wantsPasswordChange
                          ? {
                                password: newPassword.trim(),
                                password_confirmation: confirmPassword.trim(),
                            }
                          : {}),
                  }
                : {
                      name,
                      mobile_number: mobileNumber,
                      ...(affiliationManaged && affiliationEligible && affiliationChanged
                          ? user?.user_type === 'student'
                              ? { college_id: collegeId ? Number(collegeId) : null }
                              : { office_id: officeId ? Number(officeId) : null }
                          : {}),
                  };
            await api.patch('/me/account', payload);
            await refreshUser();
            setSaved(true);
            setCurrentPassword('');
            setNewPassword('');
            setConfirmPassword('');
        } catch (err) {
            setError(firstErrorLine(err));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className={ui.pageCenter}>
            <div className={`w-full min-w-0 max-w-lg p-5 sm:p-8 ${ui.card}`}>
                <h1 className={`${ui.pageTitle} mb-2`}>Account Settings</h1>
                <p className="text-slate-600 mb-6 text-sm">{helpText}</p>

                {saved && (
                    <div className="text-green-800 text-sm bg-green-50 border border-green-200 p-3 rounded-lg mb-4">
                        Saved.
                    </div>
                )}
                {error && (
                    <div className="text-red-700 text-sm bg-red-50 border border-red-200 p-3 rounded-lg mb-4">
                        {error}
                    </div>
                )}

                <div className="grid grid-cols-1 gap-3 mb-6">
                    {!adminPortal && (
                        <div>
                            <p className={ui.sectionLabel}>Email</p>
                            <p className="mt-1 text-sm text-slate-800">{user?.email}</p>
                        </div>
                    )}
                    <div>
                        <p className={ui.sectionLabel}>Role</p>
                        <p className="mt-1 text-sm text-slate-800">{typeLabel}</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Full name</label>
                        <input
                            type="text"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            required
                            className={ui.input}
                        />
                    </div>

                    {adminPortal && (
                        <>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Email</label>
                                <input
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    required
                                    autoComplete="email"
                                    className={ui.input}
                                />
                            </div>
                            {needsCurrentPassword && (
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">Current password</label>
                                    <input
                                        type="password"
                                        value={currentPassword}
                                        onChange={(e) => setCurrentPassword(e.target.value)}
                                        required
                                        autoComplete="current-password"
                                        className={ui.input}
                                    />
                                    <p className="mt-1 text-xs text-slate-500">
                                        {emailChanged && wantsPasswordChange
                                            ? 'Required to confirm your email change and new password.'
                                            : emailChanged
                                              ? 'Required to confirm this email change.'
                                              : 'Required to set a new password.'}
                                    </p>
                                </div>
                            )}
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">New password</label>
                                <input
                                    type="password"
                                    value={newPassword}
                                    onChange={(e) => setNewPassword(e.target.value)}
                                    autoComplete="new-password"
                                    className={ui.input}
                                    placeholder="Leave blank to keep current password"
                                />
                            </div>
                            {wantsPasswordChange && (
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">Confirm new password</label>
                                    <input
                                        type="password"
                                        value={confirmPassword}
                                        onChange={(e) => setConfirmPassword(e.target.value)}
                                        required
                                        autoComplete="new-password"
                                        className={ui.input}
                                    />
                                </div>
                            )}
                        </>
                    )}

                    {!adminPortal && (
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
                    )}

                    {affiliationManaged && (
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">{affiliationLabel}</label>
                            <select
                                value={affiliationValue}
                                onChange={(e) => {
                                    if (user?.user_type === 'student') {
                                        setCollegeId(e.target.value);
                                    } else {
                                        setOfficeId(e.target.value);
                                    }
                                }}
                                disabled={!affiliationEligible}
                                required
                                className={`${ui.input} ${!affiliationEligible ? 'cursor-not-allowed bg-slate-100 text-slate-600' : ''}`}
                            >
                                <option value="">Select {affiliationLabel.toLowerCase()}</option>
                                {affiliationOptions.map((row) => (
                                    <option key={row.id} value={String(row.id)}>
                                        {row.name}
                                    </option>
                                ))}
                            </select>
                            {!affiliationEligible && affiliationNextChangeLabel ? (
                                <p className="mt-2 text-xs text-slate-600" role="status">
                                    You can change this again on {affiliationNextChangeLabel}.
                                </p>
                            ) : (
                                <p className="mt-1 text-xs text-slate-500">
                                    You may update your {affiliationLabel.toLowerCase()} once every month.
                                </p>
                            )}
                        </div>
                    )}

                    <button type="submit" disabled={saving} className={ui.btnPrimaryFull}>
                        {saving ? 'Saving…' : 'Save changes'}
                    </button>
                </form>
            </div>
        </div>
    );
}

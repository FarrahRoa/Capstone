import { useEffect, useMemo, useState } from 'react';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { ui } from '../theme';

function isAdminPortalUser(user) {
    const slug = user?.role?.slug;
    return slug === 'admin' || slug === 'librarian' || slug === 'student_assistant';
}

export default function AccountSettings() {
    const { user, refreshUser } = useAuth();
    const adminPortal = isAdminPortalUser(user);
    const [name, setName] = useState(user?.name || '');
    const [email, setEmail] = useState(user?.email || '');
    const [mobileNumber, setMobileNumber] = useState(user?.mobile_number || '');
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
        setCurrentPassword('');
        setNewPassword('');
        setConfirmPassword('');
    }, [user?.name, user?.email, user?.mobile_number]);

    const emailChanged = useMemo(() => {
        const a = (email || '').trim().toLowerCase();
        const b = (user?.email || '').trim().toLowerCase();
        return a !== b;
    }, [email, user?.email]);

    const wantsPasswordChange = useMemo(() => (newPassword || '').trim().length > 0, [newPassword]);

    const needsCurrentPassword = adminPortal && (emailChanged || wantsPasswordChange);

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
                    {!adminPortal && user?.college_office && (
                        <div>
                            <p className={ui.sectionLabel}>{user?.user_type === 'student' ? 'College' : 'Department/Office'}</p>
                            <p className="mt-1 text-sm text-slate-800">{user.college_office}</p>
                        </div>
                    )}
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

                    <button type="submit" disabled={saving} className={ui.btnPrimaryFull}>
                        {saving ? 'Saving…' : 'Save changes'}
                    </button>
                </form>
            </div>
        </div>
    );
}

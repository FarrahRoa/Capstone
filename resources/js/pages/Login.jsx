import { Suspense, lazy, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api';
import DeferredMount from '../components/DeferredMount';
import { useAuth } from '../contexts/AuthContext';
import { ui } from '../theme';
import xuLogotypeUrl from '../../../2023 XU Logotype Revision V2 Stacked_Full Color.png';

const PublicScheduleBoard = lazy(() => import('../components/booking/PublicScheduleBoard'));

export default function Login() {
    const [error, setError] = useState('');
    /** @type {null | 'sign_in' | 'sign_up'} */
    const [submittingAction, setSubmittingAction] = useState(null);
    const [accountType, setAccountType] = useState('');
    const [step, setStep] = useState('pick');
    const [email, setEmail] = useState('');
    const navigate = useNavigate();
    const { login } = useAuth();
    const domainHint = useMemo(() => {
        if (accountType === 'student') return '@my.xu.edu.ph';
        if (accountType === 'employee') return '@xu.edu.ph';
        return '';
    }, [accountType]);

    const pickAccount = (type) => {
        setError('');
        setAccountType(type);
        setStep('email');
    };

    const goBackToAccount = () => {
        setError('');
        setSubmittingAction(null);
        setAccountType('');
        setEmail('');
        setStep('pick');
    };

    const submit = async (action) => {
        setError('');
        setSubmittingAction(action);
        try {
            const { data } = await api.post('/login', {
                email,
                account_type: accountType,
                action,
            });

            if (data?.requires_otp === false && data?.token && data?.user) {
                login(data.token, data.user);
                sessionStorage.removeItem('xu_profile_completion_after_otp');
                navigate(data.user?.requires_profile_completion ? '/complete-profile' : '/', { replace: true });
                return;
            }

            navigate('/otp', { replace: true, state: { email, intent: 'user' } });
        } catch (err) {
            const msg =
                err.response?.data?.errors?.email?.[0]
                || err.response?.data?.message
                || 'Could not continue. Please try again.';
            setError(msg);
        } finally {
            setSubmittingAction(null);
        }
    };

    return (
        <div className={`${ui.pageBg} min-h-screen min-w-0 overflow-x-hidden bg-gradient-to-b from-white/70 via-xu-page to-xu-page`}>
            <div className="mx-auto grid w-full min-w-0 max-w-[92rem] grid-cols-1 gap-8 px-4 py-10 sm:gap-10 sm:px-6 sm:py-12 lg:grid-cols-12 lg:items-start lg:gap-x-8 lg:gap-y-8 lg:px-6 lg:py-12 xl:gap-x-12 xl:gap-y-10 xl:px-10 xl:py-16 2xl:gap-x-14">
                <section className="flex w-full justify-center lg:col-span-5 lg:justify-center xl:col-span-4">
                    <div
                        className={`flex w-full max-w-md flex-col overflow-hidden lg:sticky lg:top-20 lg:self-start xl:top-24 ${ui.card} shadow-xl shadow-slate-300/25 ring-1 ring-slate-200/70`}
                    >
                        <div
                            className="h-1 w-full shrink-0 bg-gradient-to-r from-xu-primary via-xu-secondary to-xu-gold"
                            aria-hidden="true"
                        />
                        <div className="p-8 sm:p-9">
                        <div className="mb-6 flex w-full justify-center sm:mb-8">
                            <img
                                src={xuLogotypeUrl}
                                alt="Xavier University"
                                decoding="async"
                                className="mx-auto h-auto w-full max-w-[min(100%,16rem)] object-contain sm:max-w-[18rem]"
                            />
                        </div>
                        <h1 className="mb-4 text-center sm:mb-5 sm:text-left">
                            <span className="block text-[0.8125rem] font-semibold leading-snug text-xu-secondary">
                                Xavier University Library
                            </span>
                            <span className="mt-1.5 block font-serif text-3xl font-bold tracking-tight text-xu-primary">
                                Sign In
                            </span>
                        </h1>

                        {step === 'pick' && (
                                <>
                                    <p className="mb-7 text-center text-sm leading-relaxed text-slate-600 sm:mb-8 sm:text-left">
                                        Choose your account type, then continue with OTP sent to your XU email.
                                    </p>
                                    {error && (
                                        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                            {error}
                                        </div>
                                    )}
                                    <div className="flex flex-col gap-4">
                                        <button
                                            type="button"
                                            onClick={() => pickAccount('student')}
                                            className="group w-full rounded-xl border-2 border-slate-200/90 bg-white px-5 py-4 text-left shadow-sm transition-all duration-200 hover:border-xu-secondary/50 hover:bg-xu-page/50 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-xu-secondary/40 focus-visible:ring-offset-2 active:scale-[0.995]"
                                        >
                                            <span className="flex items-start justify-between gap-3">
                                                <span className="min-w-0">
                                                    <span className="block text-sm font-semibold text-xu-primary">Student</span>
                                                    <span className="mt-1.5 block text-xs leading-relaxed text-slate-600">
                                                        Use your <span className="font-medium text-slate-800">@my.xu.edu.ph</span> email.
                                                    </span>
                                                </span>
                                                <span
                                                    className="mt-0.5 shrink-0 text-lg leading-none text-xu-secondary/45 transition group-hover:text-xu-secondary"
                                                    aria-hidden="true"
                                                >
                                                    →
                                                </span>
                                            </span>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => pickAccount('employee')}
                                            className="group w-full rounded-xl border-2 border-slate-200/90 bg-white px-5 py-4 text-left shadow-sm transition-all duration-200 hover:border-xu-secondary/50 hover:bg-xu-page/50 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-xu-secondary/40 focus-visible:ring-offset-2 active:scale-[0.995]"
                                        >
                                            <span className="flex items-start justify-between gap-3">
                                                <span className="min-w-0">
                                                    <span className="block text-sm font-semibold text-xu-primary">Employee/Staff</span>
                                                    <span className="mt-1.5 block text-xs leading-relaxed text-slate-600">
                                                        Use your <span className="font-medium text-slate-800">@xu.edu.ph</span> email.
                                                    </span>
                                                </span>
                                                <span
                                                    className="mt-0.5 shrink-0 text-lg leading-none text-xu-secondary/45 transition group-hover:text-xu-secondary"
                                                    aria-hidden="true"
                                                >
                                                    →
                                                </span>
                                            </span>
                                        </button>
                                    </div>
                                </>
                        )}

                        {step === 'email' && accountType && (
                                <>
                                    <p className="mb-5 text-center text-sm leading-relaxed text-slate-600 sm:text-left">
                                        Enter your XU email. We’ll send a one-time code to sign you in.
                                    </p>
                                    <button type="button" onClick={goBackToAccount} className={`${ui.linkAccent} mb-5 block text-sm sm:mb-6`}>
                                        ← Change account type
                                    </button>
                                    {error && (
                                        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                            {error}
                                        </div>
                                    )}
                                    <div className="space-y-5">
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">XU email</label>
                                            <input
                                                type="email"
                                                value={email}
                                                onChange={(e) => setEmail(e.target.value)}
                                                className={ui.input}
                                                placeholder={domainHint ? `you${domainHint}` : 'you@my.xu.edu.ph'}
                                                autoComplete="email"
                                                required
                                            />
                                            {domainHint && (
                                                <p className="mt-1 text-xs text-slate-600">
                                                    Must end with <span className="font-medium">{domainHint}</span>
                                                </p>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-1 gap-3 min-[380px]:grid-cols-2">
                                            <button
                                                type="button"
                                                onClick={() => submit('sign_in')}
                                                disabled={submittingAction !== null}
                                                className={ui.btnPrimaryFull}
                                            >
                                                {submittingAction === 'sign_in' ? 'Signing in…' : 'Sign in'}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => submit('sign_up')}
                                                disabled={submittingAction !== null}
                                                className={ui.btnSecondaryFull}
                                            >
                                                {submittingAction === 'sign_up' ? 'Signing up…' : 'Sign up'}
                                            </button>
                                        </div>
                                        <p className="text-xs text-slate-600">
                                            No password required. Sign in is for existing accounts; Sign up is for new accounts.
                                        </p>
                                    </div>
                                </>
                        )}
                        </div>
                    </div>
                </section>
                <section className="min-w-0 w-full lg:col-span-7 xl:col-span-8 xl:pt-1">
                    <DeferredMount
                        placeholder={
                            <div className="rounded-2xl border border-slate-200/80 bg-white/70 p-6 text-sm text-slate-600 shadow-sm">
                                Loading schedule…
                            </div>
                        }
                    >
                        <Suspense
                            fallback={
                                <div className="rounded-2xl border border-slate-200/80 bg-white/70 p-6 text-sm text-slate-600 shadow-sm">
                                    Loading schedule…
                                </div>
                            }
                        >
                            <PublicScheduleBoard />
                        </Suspense>
                    </DeferredMount>
                </section>
            </div>
        </div>
    );
}

import React, { Suspense, lazy } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import AdminAreaRoute from './components/admin/AdminAreaRoute';
import AdminPermissionGate from './components/admin/AdminPermissionGate';
import Layout from './components/Layout';
import HomeDashboard from './pages/HomeDashboard';

const Login = lazy(() => import('./pages/Login'));
const AdminLogin = lazy(() => import('./pages/AdminLogin'));
const AdminInviteSetPassword = lazy(() => import('./pages/AdminInviteSetPassword'));
const OTPVerify = lazy(() => import('./pages/OTPVerify'));
const CompleteProfile = lazy(() => import('./pages/CompleteProfile'));
const AccountSettings = lazy(() => import('./pages/AccountSettings'));
import Calendar from './pages/Calendar';
const ReservationForm = lazy(() => import('./pages/ReservationForm'));
const MyReservations = lazy(() => import('./pages/MyReservations'));
const ConfirmReservation = lazy(() => import('./pages/ConfirmReservation'));
/** Eager imports: lazy admin pages under a parent Suspense unmount the whole /admin shell (hamburger vanishes). */
import AdminReservations from './pages/admin/AdminReservations';
import AdminReports from './pages/admin/AdminReports';
import AdminUsers from './pages/admin/AdminUsers';
import AdminSpaces from './pages/admin/AdminSpaces';
import AdminPolicies from './pages/admin/AdminPolicies';
import AdminDeanEmails from './pages/admin/AdminDeanEmails';
import CollegeOfficeManager from './pages/admin/CollegeOfficeManager';
import AdminOperatingHours from './pages/admin/AdminOperatingHours';
import AdminCloudSync from './pages/admin/AdminCloudSync';
const Unauthorized = lazy(() => import('./pages/Unauthorized'));

function RouteLoading() {
    return (
        <div className="flex justify-center items-center min-h-screen bg-xu-page text-xu-primary font-medium">
            Loading…
        </div>
    );
}

function PrivateRoute({ children, requiredPermission, requiredAnyPermissions, requiredAllPermissions }) {
    const { user, loading, hasPermission, hasAnyPermission, hasAllPermissions } = useAuth();
    if (loading && !user) return <RouteLoading />;
    if (!user) return <Navigate to="/login" replace />;
    if (requiredPermission && !hasPermission(requiredPermission)) return <Navigate to="/unauthorized" replace />;
    if (requiredAnyPermissions && !hasAnyPermission(requiredAnyPermissions)) return <Navigate to="/unauthorized" replace />;
    if (requiredAllPermissions && !hasAllPermissions(requiredAllPermissions)) return <Navigate to="/unauthorized" replace />;
    return children;
}

function AuthOnlyRoute({ children }) {
    const { user, loading } = useAuth();
    if (loading && !user) return <RouteLoading />;
    if (!user) return <Navigate to="/login" replace />;
    // Only allow entry right after successful OTP verification.
    // This prevents profile completion from showing during normal app hydration (/api/me).
    if (!sessionStorage.getItem('xu_profile_completion_after_otp')) return <Navigate to="/" replace />;
    return children;
}

function AppRoutes() {
    return (
        <Routes>
            <Route path="/login" element={<Suspense fallback={<RouteLoading />}><Login /></Suspense>} />
            <Route path="/admin/login" element={<Suspense fallback={<RouteLoading />}><AdminLogin /></Suspense>} />
            <Route path="/admin/invite" element={<Suspense fallback={<RouteLoading />}><AdminInviteSetPassword /></Suspense>} />
            <Route path="/unauthorized" element={<Suspense fallback={<RouteLoading />}><Unauthorized /></Suspense>} />
            <Route path="/otp" element={<Suspense fallback={<RouteLoading />}><OTPVerify /></Suspense>} />
            <Route
                path="/complete-profile"
                element={
                    <AuthOnlyRoute>
                        <Suspense fallback={<RouteLoading />}>
                            <CompleteProfile />
                        </Suspense>
                    </AuthOnlyRoute>
                }
            />
            <Route path="/confirm-reservation" element={<Suspense fallback={<RouteLoading />}><ConfirmReservation /></Suspense>} />
            {/* Admin shell: eager imports + no parent Suspense so AdminLayout never unmounts between admin routes */}
            <Route path="/admin" element={<AdminAreaRoute />}>
                    <Route index element={<Navigate to="dashboard" replace />} />
                    <Route path="dashboard" element={<HomeDashboard />} />
                    <Route
                        path="calendar"
                        element={
                            <AdminPermissionGate permission="calendar.view">
                                <Calendar />
                            </AdminPermissionGate>
                        }
                    />
                    <Route
                        path="reservations"
                        element={
                            <AdminPermissionGate permission="reservation.view_all">
                                <AdminReservations />
                            </AdminPermissionGate>
                        }
                    />
                    <Route
                        path="reports"
                        element={
                            <AdminPermissionGate permission="reports.view">
                                <AdminReports />
                            </AdminPermissionGate>
                        }
                    />
                    <Route
                        path="users"
                        element={
                            <AdminPermissionGate permission="users.manage">
                                <AdminUsers />
                            </AdminPermissionGate>
                        }
                    />
                    <Route
                        path="spaces"
                        element={
                            <AdminPermissionGate permission="spaces.manage">
                                <AdminSpaces />
                            </AdminPermissionGate>
                        }
                    />
                    <Route
                        path="policies"
                        element={
                            <AdminPermissionGate permission="policies.manage">
                                <AdminPolicies />
                            </AdminPermissionGate>
                        }
                    />
                    <Route
                        path="operating-hours"
                        element={
                            <AdminPermissionGate permission="policies.manage">
                                <AdminOperatingHours />
                            </AdminPermissionGate>
                        }
                    />
                    <Route
                        path="dean-emails"
                        element={
                            <AdminPermissionGate permission="policies.manage">
                                <AdminDeanEmails />
                            </AdminPermissionGate>
                        }
                    />
                    <Route
                        path="organizations"
                        element={
                            <AdminPermissionGate permission="users.manage">
                                <CollegeOfficeManager />
                            </AdminPermissionGate>
                        }
                    />
                    <Route
                        path="cloud-sync"
                        element={
                            <AdminPermissionGate permission="system.cloud_sync">
                                <AdminCloudSync />
                            </AdminPermissionGate>
                        }
                    />
            </Route>
            <Route element={<Layout />}>
                <Route
                    path="/account"
                    element={
                        <PrivateRoute>
                            <Suspense fallback={<RouteLoading />}>
                                <AccountSettings />
                            </Suspense>
                        </PrivateRoute>
                    }
                />
                    <Route
                        path="/"
                        element={
                            <PrivateRoute requiredPermission="calendar.view">
                                <HomeDashboard />
                            </PrivateRoute>
                        }
                    />
                    <Route
                        path="/calendar"
                        element={
                            <PrivateRoute requiredPermission="calendar.view">
                                <Calendar />
                            </PrivateRoute>
                        }
                    />
                <Route
                    path="/reserve"
                    element={
                        <PrivateRoute requiredPermission="reservation.create">
                            <Suspense fallback={<RouteLoading />}>
                                <ReservationForm />
                            </Suspense>
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/my-reservations"
                    element={
                        <PrivateRoute requiredPermission="reservation.view_own">
                            <Suspense fallback={<RouteLoading />}>
                                <MyReservations />
                            </Suspense>
                        </PrivateRoute>
                    }
                />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}

const rootEl = document.getElementById('root');
if (!rootEl) {
    throw new Error('XU Library SPA: mount element #root not found.');
}

createRoot(rootEl).render(
    <React.StrictMode>
        <AuthProvider>
            <BrowserRouter>
                <AppRoutes />
            </BrowserRouter>
        </AuthProvider>
    </React.StrictMode>
);

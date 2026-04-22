import React, { Suspense, lazy } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';

const Login = lazy(() => import('./pages/Login'));
const AdminLogin = lazy(() => import('./pages/AdminLogin'));
const AdminInviteSetPassword = lazy(() => import('./pages/AdminInviteSetPassword'));
const OTPVerify = lazy(() => import('./pages/OTPVerify'));
const CompleteProfile = lazy(() => import('./pages/CompleteProfile'));
const AccountSettings = lazy(() => import('./pages/AccountSettings'));
const Calendar = lazy(() => import('./pages/Calendar'));
const HomeDashboard = lazy(() => import('./pages/HomeDashboard'));
const ReservationForm = lazy(() => import('./pages/ReservationForm'));
const MyReservations = lazy(() => import('./pages/MyReservations'));
const ConfirmReservation = lazy(() => import('./pages/ConfirmReservation'));
const AdminReservations = lazy(() => import('./pages/admin/AdminReservations'));
const AdminReports = lazy(() => import('./pages/admin/AdminReports'));
const AdminUsers = lazy(() => import('./pages/admin/AdminUsers'));
const AdminSpaces = lazy(() => import('./pages/admin/AdminSpaces'));
const AdminPolicies = lazy(() => import('./pages/admin/AdminPolicies'));
const AdminDeanEmails = lazy(() => import('./pages/admin/AdminDeanEmails'));
const AdminOperatingHours = lazy(() => import('./pages/admin/AdminOperatingHours'));
const AdminCloudSync = lazy(() => import('./pages/admin/AdminCloudSync'));
const Unauthorized = lazy(() => import('./pages/Unauthorized'));
const Layout = lazy(() => import('./components/Layout'));

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

function withLayout(node) {
    return (
        <Layout>
            {node}
        </Layout>
    );
}

function AppRoutes() {
    return (
        <Suspense fallback={<RouteLoading />}>
            <Routes>
                <Route path="/login" element={<Login />} />
                <Route path="/admin/login" element={<AdminLogin />} />
                <Route path="/admin/invite" element={<AdminInviteSetPassword />} />
                <Route path="/unauthorized" element={<Unauthorized />} />
                <Route path="/otp" element={<OTPVerify />} />
                <Route
                    path="/complete-profile"
                    element={
                        <AuthOnlyRoute>
                            <CompleteProfile />
                        </AuthOnlyRoute>
                    }
                />
                <Route
                    path="/account"
                    element={
                        <PrivateRoute>
                            {withLayout(<AccountSettings />)}
                        </PrivateRoute>
                    }
                />
                <Route path="/confirm-reservation" element={<ConfirmReservation />} />
                <Route
                    path="/"
                    element={
                        <PrivateRoute requiredPermission="calendar.view">
                            {withLayout(<HomeDashboard />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/calendar"
                    element={
                        <PrivateRoute requiredPermission="calendar.view">
                            {withLayout(<Calendar />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/reserve"
                    element={
                        <PrivateRoute requiredPermission="reservation.create">
                            {withLayout(<ReservationForm />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/my-reservations"
                    element={
                        <PrivateRoute requiredPermission="reservation.view_own">
                            {withLayout(<MyReservations />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/admin/reservations"
                    element={
                        <PrivateRoute requiredPermission="reservation.view_all">
                            {withLayout(<AdminReservations />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/admin/reports"
                    element={
                        <PrivateRoute requiredPermission="reports.view">
                            {withLayout(<AdminReports />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/admin/users"
                    element={
                        <PrivateRoute requiredPermission="users.manage">
                            {withLayout(<AdminUsers />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/admin/spaces"
                    element={
                        <PrivateRoute requiredPermission="spaces.manage">
                            {withLayout(<AdminSpaces />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/admin/policies"
                    element={
                        <PrivateRoute requiredPermission="policies.manage">
                            {withLayout(<AdminPolicies />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/admin/operating-hours"
                    element={
                        <PrivateRoute requiredPermission="policies.manage">
                            {withLayout(<AdminOperatingHours />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/admin/dean-emails"
                    element={
                        <PrivateRoute requiredPermission="policies.manage">
                            {withLayout(<AdminDeanEmails />)}
                        </PrivateRoute>
                    }
                />
                <Route
                    path="/admin/cloud-sync"
                    element={
                        <PrivateRoute requiredPermission="system.cloud_sync">
                            {withLayout(<AdminCloudSync />)}
                        </PrivateRoute>
                    }
                />
                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
        </Suspense>
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

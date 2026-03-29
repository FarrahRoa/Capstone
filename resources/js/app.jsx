import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import Login from './pages/Login';
import OTPVerify from './pages/OTPVerify';
import Calendar from './pages/Calendar';
import HomeDashboard from './pages/HomeDashboard';
import ReservationForm from './pages/ReservationForm';
import MyReservations from './pages/MyReservations';
import ConfirmReservation from './pages/ConfirmReservation';
import AdminReservations from './pages/admin/AdminReservations';
import AdminReports from './pages/admin/AdminReports';
import AdminUsers from './pages/admin/AdminUsers';
import AdminSpaces from './pages/admin/AdminSpaces';
import AdminPolicies from './pages/admin/AdminPolicies';
import Unauthorized from './pages/Unauthorized';
import Layout from './components/Layout';

function PrivateRoute({ children, requiredPermission, requiredAnyPermissions, requiredAllPermissions }) {
    const { user, loading, hasPermission, hasAnyPermission, hasAllPermissions } = useAuth();
    if (loading)
        return (
            <div className="flex justify-center items-center min-h-screen bg-xu-page text-xu-primary font-medium">
                Loading…
            </div>
        );
    if (!user) return <Navigate to="/login" replace />;
    if (requiredPermission && !hasPermission(requiredPermission)) return <Navigate to="/unauthorized" replace />;
    if (requiredAnyPermissions && !hasAnyPermission(requiredAnyPermissions)) return <Navigate to="/unauthorized" replace />;
    if (requiredAllPermissions && !hasAllPermissions(requiredAllPermissions)) return <Navigate to="/unauthorized" replace />;
    return children;
}

function AppRoutes() {
    return (
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/unauthorized" element={<Unauthorized />} />
            <Route path="/otp" element={<OTPVerify />} />
            <Route path="/confirm-reservation" element={<ConfirmReservation />} />
            <Route path="/" element={<PrivateRoute requiredPermission="calendar.view"><Layout><HomeDashboard /></Layout></PrivateRoute>} />
            <Route path="/calendar" element={<PrivateRoute requiredPermission="calendar.view"><Layout><Calendar /></Layout></PrivateRoute>} />
            <Route path="/reserve" element={<PrivateRoute requiredPermission="reservation.create"><Layout><ReservationForm /></Layout></PrivateRoute>} />
            <Route path="/my-reservations" element={<PrivateRoute requiredPermission="reservation.view_own"><Layout><MyReservations /></Layout></PrivateRoute>} />
            <Route path="/admin/reservations" element={<PrivateRoute requiredPermission="reservation.view_all"><Layout><AdminReservations /></Layout></PrivateRoute>} />
            <Route path="/admin/reports" element={<PrivateRoute requiredPermission="reports.view"><Layout><AdminReports /></Layout></PrivateRoute>} />
            <Route path="/admin/users" element={<PrivateRoute requiredPermission="users.manage"><Layout><AdminUsers /></Layout></PrivateRoute>} />
            <Route path="/admin/spaces" element={<PrivateRoute requiredPermission="spaces.manage"><Layout><AdminSpaces /></Layout></PrivateRoute>} />
            <Route path="/admin/policies" element={<PrivateRoute requiredPermission="policies.manage"><Layout><AdminPolicies /></Layout></PrivateRoute>} />
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

import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import Login from './pages/Login';
import OTPVerify from './pages/OTPVerify';
import Calendar from './pages/Calendar';
import ReservationForm from './pages/ReservationForm';
import MyReservations from './pages/MyReservations';
import ConfirmReservation from './pages/ConfirmReservation';
import AdminReservations from './pages/admin/AdminReservations';
import AdminReports from './pages/admin/AdminReports';
import Layout from './components/Layout';

function PrivateRoute({ children, adminOnly }) {
    const { user, loading } = useAuth();
    if (loading) return <div className="flex justify-center items-center min-h-screen">Loading...</div>;
    if (!user) return <Navigate to="/login" replace />;
    if (adminOnly && user.role?.slug !== 'admin') return <Navigate to="/" replace />;
    return children;
}

function AppRoutes() {
    return (
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/otp" element={<OTPVerify />} />
            <Route path="/confirm-reservation" element={<ConfirmReservation />} />
            <Route path="/" element={<PrivateRoute><Layout /><Calendar /></PrivateRoute>} />
            <Route path="/reserve" element={<PrivateRoute><Layout /><ReservationForm /></PrivateRoute>} />
            <Route path="/my-reservations" element={<PrivateRoute><Layout /><MyReservations /></PrivateRoute>} />
            <Route path="/admin/reservations" element={<PrivateRoute adminOnly><Layout /><AdminReservations /></PrivateRoute>} />
            <Route path="/admin/reports" element={<PrivateRoute adminOnly><Layout /><AdminReports /></PrivateRoute>} />
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}

createRoot(document.getElementById('root')).render(
    <React.StrictMode>
        <AuthProvider>
            <BrowserRouter>
                <AppRoutes />
            </BrowserRouter>
        </AuthProvider>
    </React.StrictMode>
);

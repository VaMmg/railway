import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from 'react-query';
import { Toaster } from 'react-hot-toast';
import { AuthProvider } from './contexts/AuthContext';
import { LanguageProvider } from './contexts/LanguageContext';
import { ThemeProvider } from './contexts/ThemeContext';
import { CajaProvider } from './contexts/CajaContext';
import './responsive.css';

// Layout
import Layout from './components/layout/Layout';
import Login from './components/auth/Login';
import ProtectedRoute from './components/auth/ProtectedRoute';
import RoleBasedRedirect from './components/auth/RoleBasedRedirect';
import CajaRequerida from './components/common/CajaRequerida';

// Page components (will create these next)
import Dashboard from './pages/Dashboard';
import ClientesPage from './pages/ClientesPage';
import CreditosPage from './pages/CreditosPage';
import PagosPage from './pages/PagosPage';
import UsuariosPage from './pages/UsuariosPage';
import ConfiguracionPage from './pages/ConfiguracionPage';
import PerfilPage from './pages/PerfilPage';
import ReprogramacionesPage from './pages/ReprogramacionesPage';
import CajasPage from './pages/CajasPage';
import ConsultasPage from './pages/ConsultasPage';
import TrabajadoresPage from './pages/TrabajadoresPage';

// Global styles
import './App.css';
import './components/common/CommonStyles.css';

// Create a client for React Query
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: 1,
      staleTime: 5 * 60 * 1000, // 5 minutes
    },
  },
});

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <LanguageProvider>
          <AuthProvider>
            <CajaProvider>
        <Router>
          <div className="App">
            <Routes>
              {/* Public routes */}
              <Route path="/login" element={<Login />} />
              
              {/* Protected routes */}
              <Route
                path="/"
                element={
                  <ProtectedRoute>
                    <Layout />
                  </ProtectedRoute>
                }
              >
                {/* Redirección basada en rol */}
                <Route index element={<RoleBasedRedirect />} />
                <Route 
                  path="dashboard" 
                  element={
                    <ProtectedRoute requiredPermission="gerente" excludeRoles={['Trabajador']}>
                      <Dashboard />
                    </ProtectedRoute>
                  } 
                />
                
                {/* Cajas management - Solo para Gerente y Trabajador */}
                <Route 
                  path="cajas/*" 
                  element={
                    <ProtectedRoute requiredPermission="trabajador" excludeRoles={['Administrador']}>
                      <CajasPage />
                    </ProtectedRoute>
                  } 
                />
                
                {/* Trabajadores - Solo para Gerente */}
                <Route 
                  path="trabajadores" 
                  element={
                    <ProtectedRoute requiredPermission="gerente" excludeRoles={['Trabajador', 'Administrador']}>
                      <TrabajadoresPage />
                    </ProtectedRoute>
                  } 
                />
                
                {/* Client management */}
                <Route 
                  path="clientes/*" 
                  element={
                    <ProtectedRoute requiredPermission="trabajador">
                      <CajaRequerida>
                        <ClientesPage />
                      </CajaRequerida>
                    </ProtectedRoute>
                  } 
                />
                
                {/* Credits management */}
                <Route 
                  path="creditos/*" 
                  element={
                    <ProtectedRoute requiredPermission="trabajador">
                      <CajaRequerida>
                        <CreditosPage />
                      </CajaRequerida>
                    </ProtectedRoute>
                  } 
                />
                
                {/* Payments management */}
                <Route 
                  path="pagos/*" 
                  element={
                    <ProtectedRoute requiredPermission="trabajador">
                      <CajaRequerida>
                        <PagosPage />
                      </CajaRequerida>
                    </ProtectedRoute>
                  } 
                />

                {/* Reprogramaciones management */}
                <Route 
                  path="reprogramaciones/*" 
                  element={
                    <ProtectedRoute requiredPermission="trabajador">
                      <CajaRequerida>
                        <ReprogramacionesPage />
                      </CajaRequerida>
                    </ProtectedRoute>
                  } 
                />
                
                {/* User management - Admin only */}
                <Route 
                  path="usuarios/*" 
                  element={
                    <ProtectedRoute requiredPermission="admin">
                      <UsuariosPage />
                    </ProtectedRoute>
                  } 
                />
                
                {/* Configuration - Admin only */}
                <Route 
                  path="configuracion" 
                  element={
                    <ProtectedRoute requiredPermission="admin">
                      <ConfiguracionPage />
                    </ProtectedRoute>
                  } 
                />
                
                {/* Consultas - Trabajadores */}
                <Route 
                  path="consultas" 
                  element={
                    <ProtectedRoute requiredPermission="trabajador">
                      <CajaRequerida>
                        <ConsultasPage />
                      </CajaRequerida>
                    </ProtectedRoute>
                  } 
                />
                
                {/* Profile page - Accessible to all authenticated users */}
                <Route path="perfil" element={<PerfilPage />} />

              </Route>
              
              {/* Catch all route */}
              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>
          </div>
        </Router>
            </CajaProvider>
          </AuthProvider>
        </LanguageProvider>
      </ThemeProvider>
      
      {/* Toast notifications */}
      <Toaster
        position="top-right"
        toastOptions={{
          duration: 4000,
          style: {
            background: '#363636',
            color: '#fff',
          },
          success: {
            duration: 3000,
            theme: {
              primary: '#4aed88',
            },
          },
        }}
      />
      
    </QueryClientProvider>
  );
}

export default App;
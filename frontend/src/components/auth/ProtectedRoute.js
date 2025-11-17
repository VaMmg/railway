import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { Loader2 } from 'lucide-react';

const ProtectedRoute = ({ 
  children, 
  requiredRole = null, 
  requiredPermission = null,
  excludeRoles = null,
  fallbackPath = '/login' 
}) => {
  const { isAuthenticated, loading, user, hasRole, hasPermission } = useAuth();
  const location = useLocation();

  // Mostrar loading mientras se verifica la autenticación
  if (loading) {
    return (
      <div style={{
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '1rem',
        background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        color: 'white'
      }}>
        <Loader2 size={48} className="animate-spin" />
        <p style={{ fontSize: '1.1rem', fontWeight: 500 }}>
          Verificando permisos...
        </p>
      </div>
    );
  }

  // Redirigir al login si no está autenticado
  if (!isAuthenticated) {
    return <Navigate 
      to={fallbackPath} 
      state={{ from: location.pathname }} 
      replace 
    />;
  }

  // Verificar rol requerido
  if (requiredRole && !hasRole(requiredRole)) {
    return (
      <div style={{
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '1rem',
        padding: '2rem',
        textAlign: 'center',
        backgroundColor: '#f8fafc'
      }}>
        <div style={{
          padding: '3rem 2rem',
          backgroundColor: 'white',
          borderRadius: '12px',
          boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
          maxWidth: '400px'
        }}>
          <h1 style={{
            fontSize: '1.5rem',
            fontWeight: 600,
            color: '#dc2626',
            marginBottom: '1rem'
          }}>
            Acceso Denegado
          </h1>
          <p style={{
            color: '#6b7280',
            marginBottom: '2rem'
          }}>
            No tienes permisos suficientes para acceder a esta página.
          </p>
          <button
            onClick={() => window.history.back()}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: '#667eea',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              cursor: 'pointer',
              fontSize: '0.9rem'
            }}
          >
            Volver
          </button>
        </div>
      </div>
    );
  }

  // Verificar si el rol está excluido (por nombre de rol)
  if (excludeRoles && user && user.nombre_rol && excludeRoles.includes(user.nombre_rol)) {
    return (
      <div style={{
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '1rem',
        padding: '2rem',
        textAlign: 'center',
        backgroundColor: '#f8fafc'
      }}>
        <div style={{
          padding: '3rem 2rem',
          backgroundColor: 'white',
          borderRadius: '12px',
          boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
          maxWidth: '400px'
        }}>
          <h1 style={{
            fontSize: '1.5rem',
            fontWeight: 600,
            color: '#dc2626',
            marginBottom: '1rem'
          }}>
            Acceso Restringido
          </h1>
          <p style={{
            color: '#6b7280',
            marginBottom: '2rem'
          }}>
            Esta sección no está disponible para tu rol de usuario.
          </p>
          <button
            onClick={() => window.history.back()}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: '#667eea',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              cursor: 'pointer',
              fontSize: '0.9rem'
            }}
          >
            Volver
          </button>
        </div>
      </div>
    );
  }

  // Verificar permiso requerido
  if (requiredPermission && !hasPermission(requiredPermission)) {
    return (
      <div style={{
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '1rem',
        padding: '2rem',
        textAlign: 'center',
        backgroundColor: '#f8fafc'
      }}>
        <div style={{
          padding: '3rem 2rem',
          backgroundColor: 'white',
          borderRadius: '12px',
          boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
          maxWidth: '400px'
        }}>
          <h1 style={{
            fontSize: '1.5rem',
            fontWeight: 600,
            color: '#dc2626',
            marginBottom: '1rem'
          }}>
            Sin Permisos
          </h1>
          <p style={{
            color: '#6b7280',
            marginBottom: '2rem'
          }}>
            Tu rol actual ({user?.nombre_rol || 'Desconocido'}) no tiene permisos 
            para realizar esta acción.
          </p>
          <button
            onClick={() => window.history.back()}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: '#667eea',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              cursor: 'pointer',
              fontSize: '0.9rem'
            }}
          >
            Volver
          </button>
        </div>
      </div>
    );
  }

  // Si pasa todas las verificaciones, mostrar el componente
  return children;
};

export default ProtectedRoute;
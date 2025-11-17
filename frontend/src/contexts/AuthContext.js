import React, { createContext, useContext, useState, useEffect } from 'react';
import { authService } from '../services/api';

const AuthContext = createContext();

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth debe ser usado dentro de un AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  // Verificar autenticación al cargar la aplicación
  useEffect(() => {
    const checkAuth = async () => {
      try {
        const storedUser = authService.getCurrentUser();
        const token = localStorage.getItem('auth_token');
        
        if (token && storedUser) {
          // Asegurar que id_rol sea un número
          if (storedUser.id_rol) {
            storedUser.id_rol = parseInt(storedUser.id_rol, 10);
          }
          
          // Normalizar: asegurar que tenga tanto 'id' como 'id_usuario'
          if (storedUser.id && !storedUser.id_usuario) {
            storedUser.id_usuario = storedUser.id;
          } else if (storedUser.id_usuario && !storedUser.id) {
            storedUser.id = storedUser.id_usuario;
          }
          
          // Verificar que el token sea válido
          await authService.verifyToken();
          setUser(storedUser);
          setIsAuthenticated(true);
        }
      } catch (error) {
        console.error('Error verificando autenticación:', error);
        logout();
      } finally {
        setLoading(false);
      }
    };

    checkAuth();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const login = async (credentials) => {
    try {
      setLoading(true);
      const response = await authService.login(credentials);
      
      if (response.success) {
        const userData = response.data.user;
        
        // Asegurar que id_rol sea un número
        if (userData.id_rol) {
          userData.id_rol = parseInt(userData.id_rol, 10);
        }
        
        // Normalizar: asegurar que tenga tanto 'id' como 'id_usuario'
        if (userData.id && !userData.id_usuario) {
          userData.id_usuario = userData.id;
        } else if (userData.id_usuario && !userData.id) {
          userData.id = userData.id_usuario;
        }
        
        setUser(userData);
        setIsAuthenticated(true);
        return { success: true };
      } else {
        return { success: false, message: response.message };
      }
    } catch (error) {
      console.error('Error en login:', error);
      return { 
        success: false, 
        message: error.message || 'Error al iniciar sesión'
      };
    } finally {
      setLoading(false);
    }
  };

  const logout = () => {
    setUser(null);
    setIsAuthenticated(false);
    authService.logout();
  };

  const updateUser = (userData) => {
    setUser(userData);
    localStorage.setItem('user_data', JSON.stringify(userData));
  };

  const updateUserInfo = (userData) => {
    setUser(userData);
    localStorage.setItem('user_data', JSON.stringify(userData));
  };

  const hasRole = (requiredRoles) => {
    if (!user) return false;
    
    if (Array.isArray(requiredRoles)) {
      return requiredRoles.includes(user.id_rol);
    }
    
    return user.id_rol === requiredRoles;
  };

  const hasPermission = (permission) => {
    if (!user) {
      return false;
    }

    // Usar nombre_rol en lugar de id_rol para determinar permisos
    const roleName = (user.nombre_rol || '').toLowerCase();
    const permissionLower = (permission || '').toLowerCase();

    // Mapa de permisos por nombre de rol
    if (roleName.includes('administrador') || roleName.includes('admin')) {
      // Administrador tiene todos los permisos
      return true;
    } else if (roleName.includes('gerente')) {
      // Gerente tiene permisos de gerente y trabajador, PERO NO admin
      return ['gerente', 'trabajador'].includes(permissionLower);
    } else if (roleName.includes('trabajador')) {
      // Trabajador solo tiene permisos de trabajador
      return permissionLower === 'trabajador';
    }

    // Fallback: verificar por id_rol si nombre_rol no está disponible
    const idRol = user.id_rol;
    if (idRol === 1) return true; // Admin - todos los permisos
    if (idRol === 2) return ['gerente', 'trabajador'].includes(permissionLower); // Gerente - NO admin
    if (idRol === 3) return permissionLower === 'trabajador'; // Trabajador

    return false;
  };

  const getRoleName = () => {
    if (!user) return 'Desconocido';
    
    // Usar nombre_rol directamente si está disponible
    return user.nombre_rol || 'Desconocido';
  };

  const value = {
    user,
    loading,
    isAuthenticated,
    login,
    logout,
    updateUser,
    updateUserInfo,
    hasRole,
    hasPermission,
    getRoleName
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};
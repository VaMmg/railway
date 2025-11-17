import { Navigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

const RoleBasedRedirect = () => {
  const { user } = useAuth();
  
  // Redirigir según el rol del usuario
  if (!user || !user.nombre_rol) {
    return <Navigate to="/login" replace />;
  }
  
  const roleName = user.nombre_rol.toLowerCase();
  
  // Trabajadores van a Cajas
  if (roleName.includes('trabajador')) {
    return <Navigate to="/cajas" replace />;
  }
  
  // Admin y Gerentes van al Dashboard
  return <Navigate to="/dashboard" replace />;
};

export default RoleBasedRedirect;

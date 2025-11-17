import React from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { 
  Home,
  Users,
  CreditCard,
  DollarSign,
  BarChart3,
  Settings,
  UserCog,
  Building,
  X,
  Search,
  FileText
} from 'lucide-react';
import './Sidebar.css';

const Sidebar = ({ isOpen, onClose }) => {
  const { hasPermission, user } = useAuth();
  const location = useLocation();

  const menuItems = [
    {
      path: '/cajas',
      label: 'Cajas',
      icon: Building,
      permission: 'trabajador',
      excludeRoleNames: ['Administrador'] // Excluir admin por nombre
    },
    {
      path: '/dashboard',
      label: 'Dashboard',
      icon: Home,
      permission: 'gerente', // Solo gerentes y admin
      excludeRoleNames: ['Trabajador'] // Excluir trabajadores
    },
    {
      path: '/trabajadores',
      label: 'Trabajadores',
      icon: UserCog,
      permission: 'gerente', // Solo gerentes
      excludeRoleNames: ['Trabajador', 'Administrador'] // Solo gerentes
    },
    {
      path: '/clientes',
      label: 'Clientes',
      icon: Users,
      permission: 'trabajador'
    },
    {
      path: '/creditos',
      label: 'Créditos',
      icon: CreditCard,
      permission: 'trabajador'
    },
    {
      path: '/pagos',
      label: 'Pagos',
      icon: DollarSign,
      permission: 'trabajador'
    },
    {
      path: '/reprogramaciones',
      label: 'Reprogramaciones',
      icon: FileText,
      permission: 'trabajador'
    },
    {
      path: '/consultas',
      label: 'Consultas',
      icon: Search,
      permission: 'trabajador'
    }
  ];

  const adminItems = [
    {
      path: '/usuarios',
      label: 'Usuarios',
      icon: UserCog,
      permission: 'admin'
    },
    {
      path: '/configuracion',
      label: 'Configuración',
      icon: Settings,
      permission: 'admin'
    }
  ];

  const filteredMenuItems = menuItems.filter(item => {
    // Verificar si el usuario tiene el permiso requerido
    if (!hasPermission(item.permission)) {
      return false;
    }
    
    // Si el item tiene roles excluidos por nombre, verificar que el usuario no esté en esa lista
    if (item.excludeRoleNames && user && user.nombre_rol) {
      const isExcluded = item.excludeRoleNames.includes(user.nombre_rol);
      return !isExcluded;
    }
    
    return true;
  });
  
  const filteredAdminItems = adminItems.filter(item => hasPermission(item.permission));

  const handleItemClick = () => {
    // Cerrar sidebar en móviles al hacer clic
    if (window.innerWidth <= 768) {
      onClose();
    }
  };

  return (
    <aside className={`sidebar ${isOpen ? 'open' : ''}`}>
      <div className="sidebar-header">
        <div className="sidebar-logo">
          <Building size={32} />
          <span className="sidebar-title">SisCredito</span>
        </div>
        
        {/* Close button for mobile */}
        <button 
          className="sidebar-close"
          onClick={onClose}
        >
          <X size={24} />
        </button>
      </div>

      <nav className="sidebar-nav">
        {/* Main navigation */}
        <div className="nav-section">
          <div className="nav-section-title">Principal</div>
          <ul className="nav-list">
            {filteredMenuItems.map((item) => {
              const IconComponent = item.icon;
              const isActive = location.pathname === item.path;
              
              return (
                <li key={item.path}>
                  <NavLink
                    to={item.path}
                    className={`nav-item ${isActive ? 'active' : ''}`}
                    onClick={handleItemClick}
                  >
                    <IconComponent size={20} />
                    <span>{item.label}</span>
                  </NavLink>
                </li>
              );
            })}
          </ul>
        </div>

        {/* Admin section */}
        {filteredAdminItems.length > 0 && (
          <div className="nav-section">
            <div className="nav-section-title">Administración</div>
            <ul className="nav-list">
              {filteredAdminItems.map((item) => {
                const IconComponent = item.icon;
                const isActive = location.pathname === item.path;
                
                return (
                  <li key={item.path}>
                    <NavLink
                      to={item.path}
                      className={`nav-item ${isActive ? 'active' : ''}`}
                      onClick={handleItemClick}
                    >
                      <IconComponent size={20} />
                      <span>{item.label}</span>
                    </NavLink>
                  </li>
                );
              })}
            </ul>
          </div>
        )}
      </nav>
    </aside>
  );
};

export default Sidebar;
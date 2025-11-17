import React, { useState } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import { useNavigate } from 'react-router-dom';
import { useQuery } from 'react-query';
import { 
  Menu, 
  User, 
  Settings, 
  LogOut, 
  ChevronDown,
  Bell,
  CreditCard,
  Clock,
  X
} from 'lucide-react';
import { notificationsService } from '../../services/api';
import './Header.css';

const Header = ({ onToggleSidebar }) => {
  const { user, logout, getRoleName, hasPermission } = useAuth();
  const navigate = useNavigate();
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  
  // Obtener notificaciones
  const { data: notificationsData, refetch: refetchNotifications } = useQuery(
    'notificaciones',
    () => notificationsService.getAll(),
    {
      refetchInterval: 60000, // Refrescar cada minuto
      enabled: user?.nombre_rol === 'Gerente' || user?.nombre_rol === 'Trabajador'
    }
  );
  
  const notificaciones = notificationsData?.data?.notificaciones || [];
  const totalNotificaciones = notificaciones.length;
  

  const handleLogout = () => {
    logout();
  };

  const toggleDropdown = () => {
    setDropdownOpen(!dropdownOpen);
  };

  const closeDropdown = () => {
    setDropdownOpen(false);
  };

  const handleMiPerfil = () => {
    navigate('/perfil');
    closeDropdown();
  };

  const handleConfiguracion = () => {
    navigate('/configuracion');
    closeDropdown();
  };

  const toggleNotifications = () => {
    setNotificationsOpen(!notificationsOpen);
    if (!notificationsOpen) {
      refetchNotifications();
    }
  };

  const closeNotifications = () => {
    setNotificationsOpen(false);
  };

  const handleNotificationClick = async (notificacion) => {
    try {
      // Marcar como leída
      if (notificacion.id_notificacion) {
        await notificationsService.markAsRead(notificacion.id_notificacion);
        // Refrescar notificaciones
        refetchNotifications();
      }
      
      // Redirigir según el tipo
      if (notificacion.tipo === 'credito_pendiente' || notificacion.tipo === 'credito_aprobado' || notificacion.tipo === 'credito_rechazado') {
        navigate('/creditos');
      } else if (notificacion.tipo === 'cuota_por_vencer') {
        navigate('/pagos');
      } else if (notificacion.tipo === 'pago_registrado') {
        navigate('/pagos');
      } else if (notificacion.tipo === 'cliente_nuevo') {
        navigate('/clientes');
      } else if (notificacion.tipo === 'caja_abierta' || notificacion.tipo === 'caja_cerrada') {
        navigate('/cajas');
      } else if (notificacion.tipo === 'reprogramacion_pendiente' || notificacion.tipo === 'reprogramacion_aprobada' || notificacion.tipo === 'reprogramacion_rechazada') {
        navigate('/reprogramaciones');
      }
      
      closeNotifications();
    } catch (error) {
      console.error('Error al marcar notificación como leída:', error);
      // Aún así redirigir
      closeNotifications();
    }
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-PE', {
      style: 'currency',
      currency: 'PEN'
    }).format(amount || 0);
  };

  const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-PE');
  };

  return (
    <header className="header">
      <div className="header-left">
        {/* Menu button */}
        <button
          className="menu-button"
          onClick={onToggleSidebar}
          aria-label="Toggle sidebar"
        >
          <Menu size={24} />
        </button>

        {/* Search bar removed as requested */}
      </div>

      <div className="header-right">
        {/* Notifications button - Solo para Gerente y Trabajador */}
        {(user?.nombre_rol === 'Gerente' || user?.nombre_rol === 'Trabajador') && (
          <div className="notifications-menu">
            <button
              className="notification-button"
              onClick={toggleNotifications}
              aria-label="Notificaciones"
            >
              <Bell size={20} />
              {totalNotificaciones > 0 && (
                <span className="notification-badge">{totalNotificaciones}</span>
              )}
            </button>

            {/* Dropdown de notificaciones */}
            {notificationsOpen && (
              <>
                <div className="notifications-dropdown">
                  <div className="notifications-header">
                    <h3>Notificaciones</h3>
                    <button className="close-notifications" onClick={closeNotifications}>
                      <X size={18} />
                    </button>
                  </div>

                  <div className="notifications-body">
                    {notificaciones.length === 0 ? (
                      <div className="no-notifications">
                        <Bell size={32} />
                        <p>No hay notificaciones</p>
                      </div>
                    ) : (
                      notificaciones.map((notif, index) => (
                        <div
                          key={index}
                          className={`notification-item ${notif.tipo}`}
                          onClick={() => handleNotificationClick(notif)}
                        >
                          <div className="notification-icon">
                            {notif.tipo === 'credito_pendiente' ? (
                              <CreditCard size={20} />
                            ) : (
                              <Clock size={20} />
                            )}
                          </div>
                          <div className="notification-content">
                            <p className="notification-message">{notif.mensaje}</p>
                            {notif.tipo === 'credito_pendiente' && (
                              <p className="notification-detail">
                                Monto: {formatCurrency(notif.monto_aprobado)} - Trabajador: {notif.trabajador_nombre}
                              </p>
                            )}
                            {notif.tipo === 'cuota_por_vencer' && (
                              <p className="notification-detail">
                                Monto: {formatCurrency(notif.monto_total)} - Cuota #{notif.numero_cuota} - Tel: {notif.cliente_telefono || 'N/A'}
                              </p>
                            )}
                            <p className="notification-date">{formatDate(notif.fecha_creacion)}</p>
                          </div>
                        </div>
                      ))
                    )}
                  </div>
                </div>
                <div className="notifications-overlay" onClick={closeNotifications} />
              </>
            )}
          </div>
        )}

        {/* User menu */}
        <div className="user-menu">
          <button
            className="user-button"
            onClick={toggleDropdown}
          >
            <div className="user-avatar">
              <User size={20} />
            </div>
            <div className="user-info">
              <span className="user-name">{user?.nombre_completo}</span>
              <span className="user-role">{getRoleName()}</span>
            </div>
            <ChevronDown
              size={16}
              className={`chevron ${dropdownOpen ? 'open' : ''}`}
            />
          </button>

          {/* Dropdown menu */}
          {dropdownOpen && (
            <div className="user-dropdown">
              <div className="dropdown-header">
                <div className="dropdown-user-avatar">
                  <User size={24} />
                </div>
                <div className="dropdown-user-info">
                  <p className="dropdown-user-name">{user?.nombre_completo}</p>
                  <p className="dropdown-user-email">{user?.usuario}</p>
                  <p className="dropdown-user-role">{getRoleName()}</p>
                </div>
              </div>

              <div className="dropdown-divider"></div>

              <div className="dropdown-menu">
                <button 
                  className="dropdown-item"
                  onClick={handleMiPerfil}
                >
                  <User size={18} />
                  Mi Perfil
                </button>
                {hasPermission('admin') && (
                  <button 
                    className="dropdown-item"
                    onClick={handleConfiguracion}
                  >
                    <Settings size={18} />
                    Configuración
                  </button>
                )}

                <div className="dropdown-divider"></div>

                <button 
                  className="dropdown-item logout"
                  onClick={handleLogout}
                >
                  <LogOut size={18} />
                  Cerrar Sesión
                </button>
              </div>
            </div>
          )}

          {/* Overlay para cerrar dropdown */}
          {dropdownOpen && (
            <div 
              className="dropdown-overlay"
              onClick={closeDropdown}
            />
          )}
        </div>
      </div>
    </header>
  );
};

export default Header;
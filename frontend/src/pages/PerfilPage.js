import React from 'react';
import { useAuth } from '../contexts/AuthContext';
import { User, Calendar, Shield } from 'lucide-react';
import './Pages.css';

const PerfilPage = () => {
  const { user } = useAuth();

  const formatRole = (roleId) => {
    switch (roleId) {
      case '1': return 'Administrador';
      case '2': return 'Gerente';
      case '3': return 'Trabajador';
      default: return 'Usuario';
    }
  };

  return (
    <div className="page-container">
      <div className="page-header">
        <div className="flex items-center gap-4">
          <User size={32} />
          <div>
            <h1>Mi Perfil</h1>
            <p>Información personal y configuración de cuenta</p>
          </div>
        </div>
      </div>

      <div className="page-content">
        <div className="max-w-4xl">
          {/* Información personal */}
          <div className="card">
            <div className="card-header">
              <h3>Información Personal</h3>
            </div>
            <div className="card-body">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="form-group">
                  <label htmlFor="nombres">Nombres</label>
                  <input
                    type="text"
                    id="nombres"
                    value={user?.nombres || ''}
                    disabled={true}
                    placeholder="Nombres"
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="apellido_paterno">Apellido Paterno</label>
                  <input
                    type="text"
                    id="apellido_paterno"
                    value={user?.apellido_paterno || ''}
                    disabled={true}
                    placeholder="Apellido paterno"
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="apellido_materno">Apellido Materno</label>
                  <input
                    type="text"
                    id="apellido_materno"
                    value={user?.apellido_materno || ''}
                    disabled={true}
                    placeholder="Apellido materno"
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="dni">DNI</label>
                  <input
                    type="text"
                    id="dni"
                    value={user?.dni || ''}
                    disabled={true}
                    placeholder="Número de DNI"
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Información de cuenta */}
          <div className="card">
            <div className="card-header">
              <h3>Información de Cuenta</h3>
            </div>
            <div className="card-body">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="form-group">
                  <label htmlFor="usuario">Usuario</label>
                  <input
                    type="text"
                    id="usuario"
                    value={user?.usuario || ''}
                    disabled={true}
                    placeholder="Nombre de usuario"
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="rol">Rol</label>
                  <input
                    type="text"
                    id="rol"
                    value={formatRole(user?.id_rol)}
                    disabled={true}
                    placeholder="Rol del usuario"
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Información del sistema */}
          <div className="card">
            <div className="card-header">
              <h3>Información del Sistema</h3>
            </div>
            <div className="card-body">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="info-item">
                  <div className="info-label">
                    <Calendar size={18} />
                    Fecha de creación
                  </div>
                  <div className="info-value">
                    {user?.fecha_creacion ? new Date(user.fecha_creacion).toLocaleDateString('es-PE') : 'No disponible'}
                  </div>
                </div>

                <div className="info-item">
                  <div className="info-label">
                    <Calendar size={18} />
                    Último acceso
                  </div>
                  <div className="info-value">
                    {user?.ultimo_acceso ? new Date(user.ultimo_acceso).toLocaleDateString('es-PE') : 'No disponible'}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PerfilPage;

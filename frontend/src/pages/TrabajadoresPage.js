import { useState } from 'react';
import { useQuery } from 'react-query';
import { 
  Users, 
  ChevronDown,
  ChevronRight,
  User,
  CreditCard,
  DollarSign,
  Calendar
} from 'lucide-react';
import { usersService, clientsService } from '../services/api';
import './TrabajadoresPage.css';

const TrabajadoresPage = () => {
  const [trabajadorExpandido, setTrabajadorExpandido] = useState(null);

  // Cargar todos los usuarios
  const { data: usuariosData, isLoading } = useQuery(
    'usuarios-trabajadores',
    () => usersService.getAll()
  );

  // Cargar todos los clientes
  const { data: clientesData } = useQuery(
    'clientes-todos',
    () => clientsService.getAll()
  );

  const usuarios = usuariosData?.data?.usuarios || [];
  const clientes = clientesData?.data?.clientes || [];

  // Filtrar solo trabajadores
  const trabajadores = usuarios.filter(u => 
    u.nombre_rol?.toLowerCase().includes('trabajador')
  );

  // Obtener clientes de un trabajador específico
  const getClientesPorTrabajador = (idTrabajador) => {
    return clientes.filter(c => c.id_usuario === idTrabajador);
  };

  const toggleTrabajador = (idTrabajador) => {
    if (trabajadorExpandido === idTrabajador) {
      setTrabajadorExpandido(null);
    } else {
      setTrabajadorExpandido(idTrabajador);
    }
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-PE', {
      style: 'currency',
      currency: 'PEN'
    }).format(amount || 0);
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-PE');
  };

  if (isLoading) {
    return (
      <div className="trabajadores-page">
        <div className="loading-state">
          <div className="spinner"></div>
          <p>Cargando trabajadores...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="trabajadores-page">
      {/* Header */}
      <div className="trabajadores-header">
        <div>
          <h1>Trabajadores y Clientes</h1>
          <p>Vista de trabajadores y sus clientes asignados</p>
        </div>
      </div>

      {/* Resumen */}
      <div className="trabajadores-summary">
        <div className="summary-card">
          <div className="summary-icon trabajadores">
            <Users size={24} />
          </div>
          <div className="summary-content">
            <h3>Total Trabajadores</h3>
            <p>{trabajadores.length}</p>
          </div>
        </div>

        <div className="summary-card">
          <div className="summary-icon clientes">
            <User size={24} />
          </div>
          <div className="summary-content">
            <h3>Total Clientes</h3>
            <p>{clientes.length}</p>
          </div>
        </div>
      </div>

      {/* Lista de Trabajadores */}
      <div className="trabajadores-list">
        {trabajadores.length === 0 ? (
          <div className="empty-state">
            <Users size={48} />
            <h3>No hay trabajadores registrados</h3>
            <p>Los trabajadores aparecerán aquí cuando sean creados</p>
          </div>
        ) : (
          trabajadores.map((trabajador) => {
            const clientesTrabajador = getClientesPorTrabajador(trabajador.id_usuario);
            const isExpanded = trabajadorExpandido === trabajador.id_usuario;
            
            // Calcular estadísticas del trabajador
            const totalCreditos = clientesTrabajador.reduce((sum, c) => 
              sum + (c.creditos_activos || 0), 0
            );
            const totalDeuda = clientesTrabajador.reduce((sum, c) => 
              sum + (parseFloat(c.total_deuda) || 0), 0
            );

            return (
              <div key={trabajador.id_usuario} className="trabajador-card">
                {/* Header del Trabajador */}
                <div 
                  className="trabajador-header"
                  onClick={() => toggleTrabajador(trabajador.id_usuario)}
                >
                  <div className="trabajador-info">
                    <div className="trabajador-avatar">
                      <User size={20} />
                    </div>
                    <div className="trabajador-details">
                      <h3>{trabajador.nombres} {trabajador.apellido_paterno}</h3>
                      <p className="trabajador-usuario">@{trabajador.usuario}</p>
                    </div>
                  </div>

                  <div className="trabajador-stats">
                    <div className="stat-item">
                      <User size={16} />
                      <span>{clientesTrabajador.length} clientes</span>
                    </div>
                    <div className="stat-item">
                      <CreditCard size={16} />
                      <span>{totalCreditos} créditos</span>
                    </div>
                    <div className="stat-item">
                      <DollarSign size={16} />
                      <span>{formatCurrency(totalDeuda)}</span>
                    </div>
                  </div>

                  <button className="expand-button">
                    {isExpanded ? <ChevronDown size={20} /> : <ChevronRight size={20} />}
                  </button>
                </div>

                {/* Clientes del Trabajador */}
                {isExpanded && (
                  <div className="trabajador-clientes">
                    {clientesTrabajador.length === 0 ? (
                      <div className="empty-clientes">
                        <User size={32} />
                        <p>Este trabajador no tiene clientes asignados</p>
                      </div>
                    ) : (
                      <div className="clientes-table-container">
                        <table className="clientes-table">
                          <thead>
                            <tr>
                              <th>Cliente</th>
                              <th>DNI</th>
                              <th>Teléfono</th>
                              <th>Créditos Activos</th>
                              <th>Total Deuda</th>
                              <th>Fecha Registro</th>
                            </tr>
                          </thead>
                          <tbody>
                            {clientesTrabajador.map((cliente) => (
                              <tr key={cliente.dni}>
                                <td>
                                  <div className="cliente-cell">
                                    <User size={14} />
                                    <span>{cliente.nombres} {cliente.apellido_paterno}</span>
                                  </div>
                                </td>
                                <td>{cliente.dni}</td>
                                <td>{cliente.telefono || '-'}</td>
                                <td className="text-center">
                                  <span className="badge-count">{cliente.creditos_activos || 0}</span>
                                </td>
                                <td className="amount">{formatCurrency(cliente.total_deuda)}</td>
                                <td>{formatDate(cliente.fecha_registro)}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    )}
                  </div>
                )}
              </div>
            );
          })
        )}
      </div>
    </div>
  );
};

export default TrabajadoresPage;

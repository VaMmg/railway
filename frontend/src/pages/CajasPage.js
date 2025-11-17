import { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { 
  Plus, 
  Building, 
  DollarSign,
  X,
  Eye,
  CheckCircle,
  AlertCircle,
  Users,
  Wallet,
  Trash2
} from 'lucide-react';
import { cajasService, usersService } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import { useCaja } from '../contexts/CajaContext';
import { useAutoRefresh } from '../hooks/useAutoRefresh';
import './CajasPage.css';

const CajasPage = () => {
  const { user } = useAuth();
  const { recargarCaja } = useCaja();
  const queryClient = useQueryClient();
  
  const [showAsignarModal, setShowAsignarModal] = useState(false);
  const [showDetalleModal, setShowDetalleModal] = useState(false);
  const [cajaSeleccionada, setCajaSeleccionada] = useState(null);
  const [miCaja, setMiCaja] = useState(null);
  const [loading, setLoading] = useState(true);
  const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);

  // Auto-refresh cada 20 segundos
  useAutoRefresh(() => {
    queryClient.invalidateQueries(['cajas']);
    queryClient.invalidateQueries(['miCaja']);
  }, 20000, autoRefreshEnabled);
  
  const [formData, setFormData] = useState({
    id_usuario: '',
    limite_credito: '5000.00',
    saldo_inicial: '0.00'
  });

  // Determinar rol
  const isGerente = user?.nombre_rol?.toLowerCase().includes('gerente');
  const isAdmin = user?.nombre_rol?.toLowerCase().includes('administrador');
  const isTrabajador = user?.nombre_rol?.toLowerCase().includes('trabajador');

  // Cargar usuarios trabajadores (solo para gerentes)
  const { data: usuariosData } = useQuery(
    'usuarios-trabajadores',
    () => usersService.getAll(),
    { enabled: isGerente || isAdmin }
  );

  // Cargar caja principal (para gerentes)
  const { data: cajaPrincipalData, refetch: refetchCajaPrincipal } = useQuery(
    'caja-principal',
    () => cajasService.getCajaPrincipal(),
    { 
      enabled: isGerente || isAdmin,
      onSuccess: (data) => {
        console.log('[DATA] Caja Principal Data:', data);
      }
    }
  );

  // Cargar cajas (para gerentes)
  const { data: cajasData, isLoading: loadingCajas, refetch: refetchCajas } = useQuery(
    'cajas-todas',
    () => cajasService.getAll(),
    { enabled: isGerente || isAdmin }
  );

  // Cargar mi caja (para trabajadores)
  useEffect(() => {
    if (isTrabajador) {
      const cargarMiCaja = async () => {
        try {
          setLoading(true);
          console.log('[LOAD] Cargando caja del trabajador...');
          const response = await cajasService.getMiCaja();
          console.log('[DATA] Respuesta de getMiCaja:', response);
          console.log('[DATA] Caja recibida:', response?.data?.caja);
          setMiCaja(response?.data?.caja || null);
        } catch (error) {
          console.error('[ERROR] Error al cargar caja:', error);
          setMiCaja(null);
        } finally {
          setLoading(false);
        }
      };
      cargarMiCaja();
    } else {
      setLoading(false);
    }
  }, [isTrabajador]);

  // Asignar caja
  const asignarMutation = useMutation(
    (data) => cajasService.abrirCaja(data),
    {
      onSuccess: async () => {
        await refetchCajas();
        setShowAsignarModal(false);
        resetForm();
        alert('Caja asignada exitosamente');
      },
      onError: (error) => {
        alert(error?.message || 'Error al asignar caja');
      }
    }
  );

  // Activar/Desactivar caja
  const toggleCajaMutation = useMutation(
    (accion) => {
      if (accion === 'activar') {
        return cajasService.actualizarEstado(miCaja?.id_cajas_usuario, 'Abierta');
      } else {
        return cajasService.cerrarCaja(miCaja?.id_cajas_usuario, { comentario: 'Cerrada por el trabajador' });
      }
    },
    {
      onSuccess: async (data, accion) => {
        // Recargar la caja
        const response = await cajasService.getMiCaja();
        setMiCaja(response?.data?.caja || null);
        // Recargar el contexto global
        await recargarCaja();
        alert(accion === 'activar' ? 'Caja activada exitosamente' : 'Caja cerrada exitosamente');
      },
      onError: (error) => {
        alert(error?.message || 'Error al cambiar estado de la caja');
      }
    }
  );

  const resetForm = () => {
    setFormData({
      id_usuario: '',
      limite_credito: '5000.00',
      saldo_inicial: '0.00'
    });
  };

  const handleActivarCaja = () => {
    if (window.confirm('¿Estás seguro de activar tu caja?')) {
      toggleCajaMutation.mutate('activar');
    }
  };

  const handleCerrarCaja = () => {
    if (window.confirm('¿Estás seguro de cerrar tu caja? No podrás realizar más operaciones hasta que la vuelvas a activar.')) {
      toggleCajaMutation.mutate('cerrar');
    }
  };

  // Abrir/Cerrar Caja Principal (Gerente)
  const cajaPrincipalMutation = useMutation(
    (accion) => {
      console.log('[MUTATION] Mutation caja principal:', accion);
      if (accion === 'abrir') {
        return cajasService.abrirCajaPrincipal(5000); // Saldo inicial por defecto
      } else {
        return cajasService.cerrarCajaPrincipal();
      }
    },
    {
      onSuccess: async (data, accion) => {
        console.log('[OK] Caja principal actualizada:', data);
        await refetchCajaPrincipal();
        await refetchCajas();
        alert(accion === 'abrir' ? 'Caja principal abierta exitosamente' : 'Caja principal cerrada exitosamente');
      },
      onError: (error) => {
        console.error('[ERROR] Error en caja principal:', error);
        alert(error?.response?.data?.message || error?.message || 'Error al cambiar estado de la caja principal');
      }
    }
  );

  // Habilitar/Deshabilitar caja de trabajador (Gerente)
  const habilitarCajaMutation = useMutation(
    ({ idCaja, habilitar }) => cajasService.habilitarCaja(idCaja, habilitar),
    {
      onSuccess: async () => {
        await refetchCajas();
        alert('Estado de caja actualizado');
      },
      onError: (error) => {
        alert(error?.message || 'Error al cambiar habilitación');
      }
    }
  );

  // Eliminar caja de trabajador (Gerente)
  const eliminarCajaMutation = useMutation(
    (idCaja) => cajasService.eliminarCaja(idCaja),
    {
      onSuccess: async () => {
        await refetchCajas();
        alert('Caja eliminada exitosamente');
      },
      onError: (error) => {
        alert(error?.message || 'Error al eliminar caja');
      }
    }
  );

  const handleAbrirCajaPrincipal = () => {
    console.log('[ACTION] Intentando abrir caja principal...');
    if (window.confirm('¿Abrir la caja principal del día?')) {
      cajaPrincipalMutation.mutate('abrir');
    }
  };

  const handleCerrarCajaPrincipal = () => {
    if (window.confirm('¿Cerrar la caja principal? Asegúrate de que todas las cajas de trabajadores estén cerradas.')) {
      cajaPrincipalMutation.mutate('cerrar');
    }
  };

  const handleHabilitarCaja = (idCaja, habilitar) => {
    const mensaje = habilitar 
      ? '¿Habilitar esta caja para que el trabajador pueda activarla?' 
      : '¿Deshabilitar esta caja? El trabajador no podrá activarla.';
    
    if (window.confirm(mensaje)) {
      habilitarCajaMutation.mutate({ idCaja, habilitar });
    }
  };

  const handleEliminarCaja = (caja) => {
    if (window.confirm(`[!] ADVERTENCIA: ¿Está seguro de eliminar la caja de ${caja.nombres} ${caja.apellido_paterno}?\n\nEsta acción eliminará permanentemente la caja y su historial.\n\n¿Desea continuar?`)) {
      eliminarCajaMutation.mutate(caja.id_cajas_usuario);
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!formData.id_usuario) {
      alert('Selecciona un trabajador');
      return;
    }
    asignarMutation.mutate(formData);
  };

  const handleVerDetalle = (caja) => {
    setCajaSeleccionada(caja);
    setShowDetalleModal(true);
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-PE', {
      style: 'currency',
      currency: 'PEN'
    }).format(amount || 0);
  };

  const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('es-PE');
  };

  // Vista para Trabajadores sin caja
  if (isTrabajador && !loading && !miCaja) {
    return (
      <div className="cajas-page">
        <div className="empty-state-center">
          <Building size={64} className="empty-icon" />
          <h2>No hay cajas registradas</h2>
          <p>Crea tu primera caja para comenzar</p>
          <div className="empty-hint">
            <AlertCircle size={16} />
            <span>Contacta con tu gerente para que te asigne una caja</span>
          </div>
        </div>
      </div>
    );
  }

  // Vista para Trabajadores con caja
  if (isTrabajador && miCaja) {
    const cajaAbierta = miCaja.estado_caja === 'Abierta';
    
    return (
      <div className="cajas-page">
        <div className="cajas-header">
          <div>
            <h1>Mi Caja</h1>
            <p>Gestiona tu caja asignada</p>
          </div>
          {cajaAbierta ? (
            <button 
              className="btn-danger" 
              onClick={handleCerrarCaja}
              disabled={toggleCajaMutation.isLoading}
            >
              <X size={20} />
              {toggleCajaMutation.isLoading ? 'Cerrando...' : 'Cerrar Caja'}
            </button>
          ) : (
            <button 
              className="btn-primary" 
              onClick={handleActivarCaja}
              disabled={toggleCajaMutation.isLoading}
            >
              <CheckCircle size={20} />
              {toggleCajaMutation.isLoading ? 'Activando...' : 'Activar Caja'}
            </button>
          )}
        </div>

        <div className="caja-detail-card">
          <div className="caja-detail-header">
            <div className="caja-icon-large">
              <Wallet size={32} />
            </div>
            <div>
              <h2>Caja Asignada</h2>
              <span className={`badge ${miCaja.estado_caja?.toLowerCase()}`}>
                {miCaja.estado_caja}
              </span>
            </div>
          </div>

          <div className="caja-detail-body">
            <div className="detail-row">
              <span className="detail-label">Saldo Actual</span>
              <span className="detail-value highlight">{formatCurrency(miCaja.saldo_actual)}</span>
            </div>
            <div className="detail-row">
              <span className="detail-label">Límite de Crédito</span>
              <span className="detail-value">{formatCurrency(miCaja.limite_credito)}</span>
            </div>
            <div className="detail-row">
              <span className="detail-label">Hora de Apertura</span>
              <span className="detail-value">{formatDateTime(miCaja.hora_apertura)}</span>
            </div>
            {miCaja.hora_cierre && (
              <div className="detail-row">
                <span className="detail-label">Hora de Cierre</span>
                <span className="detail-value">{formatDateTime(miCaja.hora_cierre)}</span>
              </div>
            )}
          </div>

          {!cajaAbierta && (
            <div className="caja-detail-footer">
              <AlertCircle size={16} />
              <span>Tu caja está cerrada. Actívala para comenzar a trabajar.</span>
            </div>
          )}
        </div>
      </div>
    );
  }

  // Vista para Gerentes/Admin
  const cajas = cajasData?.data?.cajas || [];
  const todosUsuarios = usuariosData?.data?.usuarios || [];
  
  // Filtrar solo trabajadores
  const usuarios = todosUsuarios.filter(u => 
    u.nombre_rol?.toLowerCase().includes('trabajador')
  );
  
  // Calcular estadísticas
  const totalCajas = cajas.length;
  const cajasAbiertas = cajas.filter(c => c.estado_caja === 'Abierta').length;
  const totalSaldo = cajas.reduce((sum, c) => sum + Number(c.saldo_actual || 0), 0);

  return (
    <div className="cajas-page">
      {/* Header */}
      <div className="cajas-header">
        <div>
          <h1>Gestión de Cajas</h1>
          <p>Administra las cajas de los trabajadores</p>
        </div>
        <button className="btn-primary" onClick={() => setShowAsignarModal(true)}>
          <Plus size={20} />
          Asignar Caja
        </button>
      </div>

      {/* Caja Principal */}
      <div className="caja-principal-section">
        <div className="caja-principal-header">
          <div className="caja-principal-title">
            <Building size={24} />
            <h2>Caja Principal</h2>
          </div>
          {cajaPrincipalData?.data?.esta_abierta ? (
            <button 
              className="btn-danger" 
              onClick={handleCerrarCajaPrincipal}
              disabled={cajaPrincipalMutation.isLoading}
            >
              <X size={20} />
              {cajaPrincipalMutation.isLoading ? 'Cerrando...' : 'Cerrar Caja Principal'}
            </button>
          ) : (
            <button 
              className="btn-primary" 
              onClick={handleAbrirCajaPrincipal}
              disabled={cajaPrincipalMutation.isLoading}
            >
              <CheckCircle size={20} />
              {cajaPrincipalMutation.isLoading ? 'Abriendo...' : 'Abrir Caja Principal'}
            </button>
          )}
        </div>
        <div className="caja-principal-body">
          {cajaPrincipalData?.data?.caja_principal ? (
            <>
              <div className="caja-principal-info">
                <span className="label">Estado:</span>
                <span className={`badge ${cajaPrincipalData.data.caja_principal.estado_caja?.toLowerCase()}`}>
                  {cajaPrincipalData.data.caja_principal.estado_caja}
                </span>
              </div>
              <div className="caja-principal-info">
                <span className="label">Saldo:</span>
                <span className="value">{formatCurrency(cajaPrincipalData.data.caja_principal.saldo_actual)}</span>
              </div>
              <div className="caja-principal-info">
                <span className="label">Apertura:</span>
                <span className="value">{formatDateTime(cajaPrincipalData.data.caja_principal.hora_apertura)}</span>
              </div>
            </>
          ) : (
            <div className="caja-principal-empty">
              <AlertCircle size={20} />
              <span>No hay caja principal abierta hoy. Abre la caja para comenzar.</span>
            </div>
          )}
        </div>
      </div>

      {/* Resumen */}
      <div className="cajas-summary">
        <div className="summary-card">
          <div className="summary-icon total">
            <Building size={24} />
          </div>
          <div className="summary-content">
            <h3>Total Cajas</h3>
            <p>{totalCajas}</p>
          </div>
        </div>

        <div className="summary-card">
          <div className="summary-icon activas">
            <CheckCircle size={24} />
          </div>
          <div className="summary-content">
            <h3>Cajas Abiertas</h3>
            <p>{cajasAbiertas}</p>
          </div>
        </div>

        <div className="summary-card">
          <div className="summary-icon cerradas">
            <DollarSign size={24} />
          </div>
          <div className="summary-content">
            <h3>Saldo Total</h3>
            <p>{formatCurrency(totalSaldo)}</p>
          </div>
        </div>
      </div>

      {/* Tabla de Cajas */}
      <div className="cajas-table-container">
        {loadingCajas ? (
          <div className="loading-state">
            <div className="spinner"></div>
            <p>Cargando cajas...</p>
          </div>
        ) : cajas.length === 0 ? (
          <div className="empty-state">
            <Building size={48} />
            <h3>No hay cajas asignadas</h3>
            <p>Asigna cajas a los trabajadores para comenzar</p>
          </div>
        ) : (
          <table className="cajas-table">
            <thead>
              <tr>
                <th>Trabajador</th>
                <th>Usuario</th>
                <th>Saldo Actual</th>
                <th>Límite Crédito</th>
                <th>Habilitada</th>
                <th>Estado</th>
                <th>Apertura</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              {cajas.map((caja) => (
                <tr key={caja.id_cajas_usuario}>
                  <td>
                    <div className="user-cell">
                      <Users size={16} />
                      <span>{caja.nombres} {caja.apellido_paterno}</span>
                    </div>
                  </td>
                  <td>{caja.usuario}</td>
                  <td className="amount">{formatCurrency(caja.saldo_actual)}</td>
                  <td className="amount">{formatCurrency(caja.limite_credito)}</td>
                  <td>
                    {caja.habilitada_por_gerente ? (
                      <span className="status-badge activa">
                        <CheckCircle size={14} /> Sí
                      </span>
                    ) : (
                      <span className="status-badge cerrada">
                        <X size={14} /> No
                      </span>
                    )}
                  </td>
                  <td>
                    <span className={`status-badge ${caja.estado_caja?.toLowerCase()}`}>
                      {caja.estado_caja}
                    </span>
                  </td>
                  <td>{formatDateTime(caja.hora_apertura)}</td>
                  <td>
                    <div className="action-buttons">
                      {caja.habilitada_por_gerente ? (
                        <button 
                          className="btn-icon danger"
                          onClick={() => handleHabilitarCaja(caja.id_cajas_usuario, false)}
                          title="Deshabilitar caja"
                          disabled={!cajaPrincipalData?.data?.esta_abierta}
                        >
                          <X size={18} />
                        </button>
                      ) : (
                        <button 
                          className="btn-icon success"
                          onClick={() => handleHabilitarCaja(caja.id_cajas_usuario, true)}
                          title="Habilitar caja"
                          disabled={!cajaPrincipalData?.data?.esta_abierta}
                        >
                          <CheckCircle size={18} />
                        </button>
                      )}
                      <button 
                        className="btn-icon view"
                        onClick={() => handleVerDetalle(caja)}
                        title="Ver detalle"
                      >
                        <Eye size={18} />
                      </button>
                      <button 
                        className="btn-icon delete"
                        onClick={() => handleEliminarCaja(caja)}
                        title="Eliminar caja"
                        disabled={eliminarCajaMutation.isLoading}
                      >
                        <Trash2 size={18} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Modal Asignar Caja */}
      {showAsignarModal && createPortal(
        <div className="modal-overlay" onClick={() => { setShowAsignarModal(false); resetForm(); }}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h2>Asignar Caja a Trabajador</h2>
              <button className="modal-close" onClick={() => { setShowAsignarModal(false); resetForm(); }}>
                <X size={24} />
              </button>
            </div>

            <form onSubmit={handleSubmit}>
              <div className="modal-body">
                <div className="form-group">
                  <label>Trabajador *</label>
                  <select
                    className="form-control"
                    value={formData.id_usuario}
                    onChange={(e) => setFormData({ ...formData, id_usuario: e.target.value })}
                    required
                  >
                    <option value="">Seleccionar trabajador...</option>
                    {usuarios.map((u) => (
                      <option key={u.id_usuario} value={u.id_usuario}>
                        {u.nombres} {u.apellido_paterno} - {u.usuario}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="form-group">
                  <label>Límite de Crédito *</label>
                  <input
                    type="number"
                    className="form-control"
                    value={formData.limite_credito}
                    onChange={(e) => setFormData({ ...formData, limite_credito: e.target.value })}
                    step="0.01"
                    min="0"
                    required
                  />
                  <small>Monto máximo que puede manejar el trabajador</small>
                </div>

                <div className="form-group">
                  <label>Saldo Inicial *</label>
                  <input
                    type="number"
                    className="form-control"
                    value={formData.saldo_inicial}
                    onChange={(e) => setFormData({ ...formData, saldo_inicial: e.target.value })}
                    step="0.01"
                    min="0"
                    required
                  />
                  <small>Monto inicial con el que abrirá la caja</small>
                </div>
              </div>

              <div className="modal-footer">
                <button 
                  type="button" 
                  className="btn-secondary" 
                  onClick={() => { setShowAsignarModal(false); resetForm(); }}
                >
                  Cancelar
                </button>
                <button 
                  type="submit" 
                  className="btn-primary" 
                  disabled={asignarMutation.isLoading}
                >
                  {asignarMutation.isLoading ? 'Asignando...' : 'Asignar Caja'}
                </button>
              </div>
            </form>
          </div>
        </div>,
        document.body
      )}

      {/* Modal Detalle Caja */}
      {showDetalleModal && cajaSeleccionada && createPortal(
        <div className="modal-overlay" onClick={() => { setShowDetalleModal(false); setCajaSeleccionada(null); }}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h2>Detalle de Caja</h2>
              <button className="modal-close" onClick={() => { setShowDetalleModal(false); setCajaSeleccionada(null); }}>
                <X size={24} />
              </button>
            </div>

            <div className="modal-body">
              <div className="detail-section">
                <h3>Información del Trabajador</h3>
                <div className="detail-grid">
                  <div className="detail-item">
                    <span className="label">Nombre:</span>
                    <span className="value">{cajaSeleccionada.nombres} {cajaSeleccionada.apellido_paterno}</span>
                  </div>
                  <div className="detail-item">
                    <span className="label">Usuario:</span>
                    <span className="value">{cajaSeleccionada.usuario}</span>
                  </div>
                </div>
              </div>

              <div className="detail-section">
                <h3>Información de la Caja</h3>
                <div className="detail-grid">
                  <div className="detail-item">
                    <span className="label">Estado:</span>
                    <span className={`status-badge ${cajaSeleccionada.estado_caja?.toLowerCase()}`}>
                      {cajaSeleccionada.estado_caja}
                    </span>
                  </div>
                  <div className="detail-item">
                    <span className="label">Saldo Actual:</span>
                    <span className="value highlight">{formatCurrency(cajaSeleccionada.saldo_actual)}</span>
                  </div>
                  <div className="detail-item">
                    <span className="label">Límite de Crédito:</span>
                    <span className="value">{formatCurrency(cajaSeleccionada.limite_credito)}</span>
                  </div>
                  <div className="detail-item">
                    <span className="label">Hora Apertura:</span>
                    <span className="value">{formatDateTime(cajaSeleccionada.hora_apertura)}</span>
                  </div>
                  {cajaSeleccionada.hora_cierre && (
                    <div className="detail-item">
                      <span className="label">Hora Cierre:</span>
                      <span className="value">{formatDateTime(cajaSeleccionada.hora_cierre)}</span>
                    </div>
                  )}
                  {cajaSeleccionada.comentario && (
                    <div className="detail-item full-width">
                      <span className="label">Comentario:</span>
                      <span className="value">{cajaSeleccionada.comentario}</span>
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="modal-footer">
              <button 
                className="btn-secondary" 
                onClick={() => { setShowDetalleModal(false); setCajaSeleccionada(null); }}
              >
                Cerrar
              </button>
            </div>
          </div>
        </div>,
        document.body
      )}
    </div>
  );
};

export default CajasPage;

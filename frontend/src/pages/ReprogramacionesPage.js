import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { 
  FileText, 
  CheckCircle, 
  XCircle, 
  Eye,
  Clock,
  AlertCircle
} from 'lucide-react';
import { reprogramacionesService } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import { useAutoRefresh } from '../hooks/useAutoRefresh';

const ReprogramacionesPage = () => {
  const { hasPermission, user } = useAuth();
  const queryClient = useQueryClient();
  const [filterStatus, setFilterStatus] = useState('Pendiente');
  const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);
  const [showMotivoModal, setShowMotivoModal] = useState(false);
  const [selectedMotivo, setSelectedMotivo] = useState(null);

  // Auto-refresh cada 20 segundos
  useAutoRefresh(() => {
    queryClient.invalidateQueries(['reprogramaciones']);
  }, 20000, autoRefreshEnabled);

  // Debug: verificar permisos
  React.useEffect(() => {
    console.log('=== DEBUG REPROGRAMACIONES ===');
    console.log('Usuario actual:', user);
    console.log('nombre_rol:', user?.nombre_rol);
    console.log('id_rol:', user?.id_rol);
    console.log('¿Tiene permiso de gerente?', hasPermission('gerente'));
    console.log('¿Tiene permiso de admin?', hasPermission('admin'));
    console.log('¿Tiene permiso de trabajador?', hasPermission('trabajador'));
    console.log('==============================');
  }, [user, hasPermission]);

  // Verificar si el usuario puede aprobar (gerente o admin)
  const puedeAprobar = React.useMemo(() => {
    if (!user) return false;
    
    const roleName = (user.nombre_rol || '').toLowerCase();
    const idRol = user.id_rol;
    
    // Verificar por nombre de rol
    const esGerentePorNombre = roleName.includes('gerente');
    const esAdminPorNombre = roleName.includes('admin') || roleName.includes('administrador');
    
    // Verificar por ID de rol
    const esGerentePorId = idRol === 2;
    const esAdminPorId = idRol === 1;
    
    const resultado = esGerentePorNombre || esAdminPorNombre || esGerentePorId || esAdminPorId;
    
    console.log('[PERMISOS] Verificación de permisos para aprobar:');
    console.log('  - Por nombre (gerente):', esGerentePorNombre);
    console.log('  - Por nombre (admin):', esAdminPorNombre);
    console.log('  - Por ID (gerente):', esGerentePorId);
    console.log('  - Por ID (admin):', esAdminPorId);
    console.log('  - RESULTADO:', resultado);
    
    return resultado;
  }, [user]);

  // Cargar reprogramaciones
  const { data: reprogramaciones = [], isLoading } = useQuery({
    queryKey: ['reprogramaciones', filterStatus],
    queryFn: async () => {
      const params = filterStatus !== 'Todas' ? { estatus: filterStatus } : {};
      const res = await reprogramacionesService.getAll(params);
      return res?.data?.reprogramaciones || [];
    }
  });

  // Aprobar reprogramación
  const aprobarMutation = useMutation({
    mutationFn: async (id) => {
      return await reprogramacionesService.aprobar(id);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries(['reprogramaciones']);
      await queryClient.refetchQueries(['reprogramaciones']);
      alert('Reprogramación aprobada y aplicada exitosamente');
    },
    onError: (error) => {
      alert('Error al aprobar: ' + (error.message || ''));
    }
  });

  // Rechazar reprogramación
  const rechazarMutation = useMutation({
    mutationFn: async ({ id, motivo }) => {
      return await reprogramacionesService.rechazar(id, motivo);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries(['reprogramaciones']);
      await queryClient.refetchQueries(['reprogramaciones']);
      alert('Reprogramación rechazada');
    },
    onError: (error) => {
      alert('Error al rechazar: ' + (error.message || ''));
    }
  });

  const handleAprobar = (reprog) => {
    if (!window.confirm(`¿Aprobar la reprogramación del crédito #${reprog.id_credito}? Esto generará nuevas cuotas.`)) {
      return;
    }
    aprobarMutation.mutate(reprog.id_reprogramacion);
  };

  const handleRechazar = (reprog) => {
    const motivo = window.prompt('Ingrese el motivo del rechazo:');
    if (!motivo || motivo.trim() === '') {
      alert('Debe ingresar un motivo para rechazar');
      return;
    }
    rechazarMutation.mutate({ id: reprog.id_reprogramacion, motivo });
  };

  const handleVerMotivo = (reprog) => {
    setSelectedMotivo({
      cliente: `${reprog.nombres || ''} ${reprog.apellido_paterno || ''} ${reprog.apellido_materno || ''}`,
      credito: reprog.id_credito,
      motivo: reprog.motivo,
      observaciones: reprog.observaciones,
      fecha: reprog.fecha_creacion
    });
    setShowMotivoModal(true);
  };

  const closeMotivoModal = () => {
    setShowMotivoModal(false);
    setSelectedMotivo(null);
  };

  const formatCurrency = (n) => new Intl.NumberFormat('es-PE', { 
    style: 'currency', 
    currency: 'PEN' 
  }).format(Number(n || 0));

  const getStatusBadge = (estatus) => {
    const map = {
      'Pendiente': { color: 'warning', icon: Clock },
      'Aprobada': { color: 'success', icon: CheckCircle },
      'Rechazada': { color: 'danger', icon: XCircle },
      'Aplicada': { color: 'primary', icon: CheckCircle }
    };
    const config = map[estatus] || { color: 'secondary', icon: AlertCircle };
    const Icon = config.icon;
    
    return (
      <span className={`badge bg-${config.color} d-flex align-items-center gap-1`} style={{ width: 'fit-content' }}>
        <Icon size={14} />
        {estatus}
      </span>
    );
  };

  return (
    <div className="container-fluid">
      <div className="row">
        <div className="col-12">
          <div className="card">
            <div className="card-header d-flex justify-content-between align-items-center">
              <h5 className="mb-0">
                <FileText className="me-2" size={24} />
                Gestión de Reprogramaciones
              </h5>
              <select 
                className="form-select" 
                style={{ width: 'auto' }}
                value={filterStatus} 
                onChange={(e) => setFilterStatus(e.target.value)}
              >
                <option value="Todas">Todas</option>
                <option value="Pendiente">Pendientes</option>
                <option value="Aprobada">Aprobadas</option>
                <option value="Rechazada">Rechazadas</option>
                <option value="Aplicada">Aplicadas</option>
              </select>
            </div>

            <div className="card-body p-0">
              {isLoading ? (
                <div className="p-4 text-center">Cargando reprogramaciones...</div>
              ) : reprogramaciones.length === 0 ? (
                <div className="p-4 text-center text-muted">
                  No hay reprogramaciones {filterStatus !== 'Todas' ? filterStatus.toLowerCase() + 's' : ''}
                </div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-hover align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th>ID</th>
                        <th>Crédito</th>
                        <th>Cliente</th>
                        <th>Cambios Propuestos</th>
                        <th>Motivo</th>
                        <th>Solicitado por</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {reprogramaciones.map((r) => (
                        <tr key={r.id_reprogramacion}>
                          <td>{r.id_reprogramacion}</td>
                          <td>
                            <span className="badge bg-info">#{r.id_credito}</span>
                          </td>
                          <td>{`${r.nombres || ''} ${r.apellido_paterno || ''} ${r.apellido_materno || ''}`}</td>
                          <td>
                            <div className="small">
                              {r.monto_anterior !== r.nuevo_monto && (
                                <div>
                                  <strong>Monto:</strong> {formatCurrency(r.monto_anterior)} → {formatCurrency(r.nuevo_monto)}
                                </div>
                              )}
                              {r.plazo_anterior !== r.nuevo_plazo_meses && (
                                <div>
                                  <strong>Plazo:</strong> {r.plazo_anterior} → {r.nuevo_plazo_meses} meses
                                </div>
                              )}
                              {r.tasa_anterior !== r.nueva_tasa_interes && (
                                <div>
                                  <strong>Tasa:</strong> {r.tasa_anterior}% → {r.nueva_tasa_interes}%
                                </div>
                              )}
                              {r.periodo_pago_anterior !== r.nuevo_periodo_pago && (
                                <div>
                                  <strong>Periodo:</strong> {r.periodo_pago_anterior} → {r.nuevo_periodo_pago}
                                </div>
                              )}
                            </div>
                          </td>
                          <td>
                            <div className="small" style={{ maxWidth: '200px' }}>
                              {r.motivo}
                            </div>
                          </td>
                          <td>{r.usuario_solicita || '-'}</td>
                          <td>{r.fecha_creacion ? new Date(r.fecha_creacion).toLocaleDateString('es-PE') : '-'}</td>
                          <td>{getStatusBadge(r.estatus)}</td>
                          <td>
                            {r.estatus === 'Pendiente' ? (
                              puedeAprobar ? (
                                <div className="btn-group btn-group-sm">
                                  <button
                                    className="btn btn-info"
                                    title="Ver motivo"
                                    onClick={() => handleVerMotivo(r)}
                                  >
                                    <Eye size={16} />
                                  </button>
                                  <button
                                    className="btn btn-success"
                                    title="Aprobar"
                                    onClick={() => handleAprobar(r)}
                                    disabled={aprobarMutation.isLoading}
                                  >
                                    <CheckCircle size={16} />
                                  </button>
                                  <button
                                    className="btn btn-danger"
                                    title="Rechazar"
                                    onClick={() => handleRechazar(r)}
                                    disabled={rechazarMutation.isLoading}
                                  >
                                    <XCircle size={16} />
                                  </button>
                                </div>
                              ) : (
                                <span className="text-muted small">
                                  Sin permisos (Rol: {user?.nombre_rol || 'N/A'})
                                </span>
                              )
                            ) : (
                              <span className="text-muted small">
                                {r.estatus === 'Rechazada' && r.motivo_rechazo ? (
                                  <span title={r.motivo_rechazo}>
                                    <Eye size={14} className="me-1" />
                                    Ver motivo
                                  </span>
                                ) : '-'}
                              </span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Modal para ver motivo */}
      {showMotivoModal && selectedMotivo && (
        <div className="modal-overlay" onClick={closeMotivoModal}>
          <div 
            className="modal-content" 
            onClick={(e) => e.stopPropagation()} 
            style={{ 
              maxWidth: '480px', 
              width: 'auto', 
              minWidth: '380px',
              height: 'fit-content',
              maxHeight: '90vh',
              overflow: 'visible'
            }}
          >
            <div className="modal-header" style={{ padding: '0.6rem 1rem', borderBottom: '1px solid #dee2e6' }}>
              <h6 style={{ margin: 0, fontSize: '0.95rem', fontWeight: '600' }}>Motivo de Reprogramación</h6>
              <button className="btn-close" onClick={closeMotivoModal} style={{ fontSize: '1.2rem' }}>×</button>
            </div>
            <div className="modal-body" style={{ padding: '0.75rem 1rem', fontSize: '0.9rem' }}>
              <div style={{ marginBottom: '0.4rem', lineHeight: '1.4' }}>
                <strong>Cliente:</strong> {selectedMotivo.cliente}
              </div>
              <div style={{ marginBottom: '0.4rem', lineHeight: '1.4' }}>
                <strong>Crédito ID:</strong> #{selectedMotivo.credito}
              </div>
              <div style={{ marginBottom: '0.4rem', lineHeight: '1.4' }}>
                <strong>Fecha:</strong> {selectedMotivo.fecha ? new Date(selectedMotivo.fecha).toLocaleDateString('es-PE') : '-'}
              </div>
              <div style={{ marginBottom: selectedMotivo.observaciones ? '0.4rem' : '0' }}>
                <strong>Motivo:</strong>
                <div style={{ 
                  padding: '0.5rem', 
                  backgroundColor: '#f8f9fa', 
                  borderRadius: '0.25rem', 
                  marginTop: '0.25rem',
                  lineHeight: '1.4'
                }}>
                  {selectedMotivo.motivo || 'No especificado'}
                </div>
              </div>
              {selectedMotivo.observaciones && (
                <div>
                  <strong>Observaciones:</strong>
                  <div style={{ 
                    padding: '0.5rem', 
                    backgroundColor: '#f8f9fa', 
                    borderRadius: '0.25rem', 
                    marginTop: '0.25rem',
                    lineHeight: '1.4'
                  }}>
                    {selectedMotivo.observaciones}
                  </div>
                </div>
              )}
            </div>
            <div className="modal-footer" style={{ padding: '0.6rem 1rem', borderTop: '1px solid #dee2e6', textAlign: 'right' }}>
              <button className="btn btn-secondary btn-sm" onClick={closeMotivoModal}>
                Cerrar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ReprogramacionesPage;

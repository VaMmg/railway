import React, { useState } from 'react';
import { Search, User, CreditCard, Calendar, AlertCircle, CheckCircle, Clock } from 'lucide-react';
import api from '../services/api';
import './Pages.css';

const ConsultasPage = () => {
  const [termino, setTermino] = useState('');
  const [resultados, setResultados] = useState([]);
  const [loading, setLoading] = useState(false);
  const [busquedaRealizada, setBusquedaRealizada] = useState(false);

  const handleBuscar = async (e) => {
    e.preventDefault();
    
    if (!termino.trim()) {
      return;
    }

    setLoading(true);
    setBusquedaRealizada(true);
    
    try {
      console.log('[SEARCH] Iniciando búsqueda con término:', termino);
      console.log('[API] URL:', `/consultas.php?action=buscar&termino=${encodeURIComponent(termino)}`);
      
      const response = await api.get(`/consultas.php?action=buscar&termino=${encodeURIComponent(termino)}`);
      
      console.log('[RESPONSE] Respuesta completa de búsqueda:', response);
      console.log('[RESPONSE] Tipo de respuesta:', typeof response);
      console.log('[RESPONSE] response.success:', response.success);
      console.log('[RESPONSE] response.data:', response.data);
      
      // Manejar diferentes formatos de respuesta
      if (response && response.success) {
        const datos = response.data || [];
        console.log('[OK] Datos encontrados:', datos.length, 'clientes');
        setResultados(datos);
      } else {
        console.warn('[WARN] No se encontraron resultados o error en la respuesta');
        setResultados([]);
        if (response && response.message) {
          console.warn('[WARN] Mensaje del servidor:', response.message);
          alert(response.message);
        }
      }
    } catch (error) {
      console.error('[ERROR] Error al buscar:', error);
      console.error('[ERROR] Error completo:', JSON.stringify(error, null, 2));
      setResultados([]);
      alert('Error al buscar: ' + (error.message || 'Error desconocido'));
    } finally {
      setLoading(false);
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Agregar 'T00:00:00' para evitar problemas de zona horaria
    const date = new Date(dateString + 'T00:00:00');
    return date.toLocaleDateString('es-PE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  };

  const formatCurrency = (amount) => {
    if (!amount) return '-';
    return new Intl.NumberFormat('es-PE', {
      style: 'currency',
      currency: 'PEN',
      minimumFractionDigits: 2
    }).format(amount);
  };

  const getEstadoColor = (estado) => {
    switch (estado?.toLowerCase()) {
      case 'activo':
      case 'aprobado':
        return 'text-success';
      case 'vencido':
        return 'text-danger';
      default:
        return 'text-secondary';
    }
  };

  return (
    <div className="page-container">
      <div className="page-header">
        <div className="flex items-center gap-4">
          <Search size={32} />
          <div>
            <h1>Consultas de Clientes</h1>
            <p>Busca información de clientes por DNI o nombre</p>
          </div>
        </div>
      </div>

      <div className="page-content">
        {/* Formulario de búsqueda */}
        <div className="card mb-4">
          <div className="card-body">
            <form onSubmit={handleBuscar}>
              <div className="row g-3 align-items-end">
                <div className="col-md-8">
                  <label className="form-label">Buscar por DNI o Nombre</label>
                  <div className="input-group">
                    <span className="input-group-text">
                      <Search size={18} />
                    </span>
                    <input
                      type="text"
                      className="form-control"
                      placeholder="Ingrese DNI o nombre del cliente..."
                      value={termino}
                      onChange={(e) => setTermino(e.target.value)}
                      disabled={loading}
                    />
                  </div>
                </div>
                <div className="col-md-4">
                  <button 
                    type="submit" 
                    className="btn btn-primary w-100"
                    disabled={loading || !termino.trim()}
                  >
                    {loading ? (
                      <>
                        <span className="spinner-border spinner-border-sm me-2" />
                        Buscando...
                      </>
                    ) : (
                      <>
                        <Search size={18} className="me-2" />
                        Buscar
                      </>
                    )}
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        {/* Resultados */}
        {busquedaRealizada && (
          <div className="card">
            <div className="card-header">
              <h5 className="mb-0">
                Resultados de búsqueda
                {resultados.length > 0 && (
                  <span className="badge bg-primary ms-2">{resultados.length}</span>
                )}
              </h5>
            </div>
            <div className="card-body">
              {loading ? (
                <div className="text-center py-5">
                  <div className="spinner-border text-primary" role="status">
                    <span className="visually-hidden">Cargando...</span>
                  </div>
                  <p className="mt-3 text-muted">Buscando clientes...</p>
                </div>
              ) : resultados.length === 0 ? (
                <div className="text-center py-5">
                  <Search size={48} className="text-muted mb-3" />
                  <h5>No se encontraron resultados</h5>
                  <p className="text-muted">
                    No hay clientes que coincidan con "{termino}"
                  </p>
                </div>
              ) : (
                <div className="row g-3">
                  {resultados.map((cliente) => (
                    <div key={cliente.id_cliente} className="col-12">
                      <div className="card border">
                        <div className="card-body">
                          {/* Información del cliente */}
                          <div className="d-flex justify-content-between align-items-start mb-3">
                            <div className="d-flex align-items-center gap-3">
                              <div className="avatar-circle">
                                <User size={24} />
                              </div>
                              <div>
                                <h5 className="mb-1">{cliente.nombre_completo}</h5>
                                <p className="text-muted mb-0">
                                  <strong>DNI:</strong> {cliente.dni}
                                </p>
                              </div>
                            </div>
                            <div className="text-end">
                              {cliente.cuotas_vencidas > 0 ? (
                                <span className="badge bg-danger">
                                  <AlertCircle size={14} className="me-1" />
                                  {cliente.cuotas_vencidas} cuota{cliente.cuotas_vencidas !== 1 ? 's' : ''} vencida{cliente.cuotas_vencidas !== 1 ? 's' : ''}
                                </span>
                              ) : (
                                <span className="badge bg-success">
                                  <CheckCircle size={14} className="me-1" />
                                  Al día
                                </span>
                              )}
                            </div>
                          </div>

                          {/* Resumen */}
                          <div className="row g-3 mb-3">
                            <div className="col-md-3">
                              <div className="d-flex align-items-center gap-2">
                                <CreditCard size={18} className="text-primary" />
                                <div>
                                  <small className="text-muted d-block">Créditos Activos</small>
                                  <strong>{cliente.creditos_activos}</strong>
                                  {cliente.creditos_cancelados && cliente.creditos_cancelados.length > 0 && (
                                    <small className="text-success d-block" style={{fontSize: '0.75rem'}}>
                                      +{cliente.creditos_cancelados.length} cancelado{cliente.creditos_cancelados.length !== 1 ? 's' : ''}
                                    </small>
                                  )}
                                </div>
                              </div>
                            </div>
                            <div className="col-md-3">
                              <div className="d-flex align-items-center gap-2">
                                <Calendar size={18} className={cliente.cuotas_vencidas > 0 ? 'text-danger' : 'text-warning'} />
                                <div>
                                  <small className="text-muted d-block">Próxima Cuota</small>
                                  <strong className={cliente.cuotas_vencidas > 0 ? 'text-danger' : ''}>
                                    {cliente.creditos && cliente.creditos.length > 0 && cliente.creditos[0].proxima_cuota_fecha
                                      ? formatDate(cliente.creditos[0].proxima_cuota_fecha)
                                      : '-'}
                                  </strong>
                                  {cliente.cuotas_vencidas > 0 && (
                                    <small className="text-danger d-block" style={{fontSize: '0.75rem'}}>
                                      Tiene cuotas vencidas
                                    </small>
                                  )}
                                </div>
                              </div>
                            </div>
                            <div className="col-md-3">
                              <div className="d-flex align-items-center gap-2">
                                <Clock size={18} className="text-info" />
                                <div>
                                  <small className="text-muted d-block">Estado</small>
                                  <strong className={cliente.cuotas_vencidas > 0 ? 'text-danger' : 'text-success'}>
                                    {cliente.cuotas_vencidas > 0 ? 'Con mora' : 'Al día'}
                                  </strong>
                                </div>
                              </div>
                            </div>
                            <div className="col-md-3">
                              <div className="d-flex align-items-center gap-2">
                                <span style={{fontSize: '18px', fontWeight: 'bold'}}>S/</span>
                                <div>
                                  <small className="text-muted d-block">Monto Próxima Cuota</small>
                                  <strong className="text-success">
                                    {cliente.creditos && cliente.creditos.length > 0 && cliente.creditos[0].proxima_cuota_monto
                                      ? formatCurrency(cliente.creditos[0].proxima_cuota_monto)
                                      : '-'}
                                  </strong>
                                </div>
                              </div>
                            </div>
                          </div>

                          {/* Créditos activos del cliente */}
                          {cliente.creditos && cliente.creditos.length > 0 && (
                            <div className="mb-4">
                              <h6 className="mb-2">
                                <span className="badge bg-primary me-2">Activos</span>
                                Créditos Activos
                              </h6>
                              <div className="table-responsive">
                                <table className="table table-sm table-hover mb-0">
                                  <thead className="table-light">
                                    <tr>
                                      <th>ID Crédito</th>
                                      <th>Fecha Otorgamiento</th>
                                      <th>Vencimiento</th>
                                      <th>Cuotas Pagadas</th>
                                      <th>Próxima Cuota</th>
                                      <th>Monto a Pagar</th>
                                      <th>Estado</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {cliente.creditos.map((credito) => (
                                      <tr key={credito.id_credito}>
                                        <td>
                                          <strong>#{credito.id_credito}</strong>
                                        </td>
                                        <td>{formatDate(credito.fecha_otorgamiento)}</td>
                                        <td>{formatDate(credito.fecha_vencimiento)}</td>
                                        <td>
                                          <span className="badge bg-info">
                                            {credito.cuotas_pagadas} / {credito.total_cuotas}
                                          </span>
                                        </td>
                                        <td>
                                          {credito.proxima_cuota_fecha ? (
                                            <div>
                                              <small className="text-muted d-block">Cuota #{credito.proxima_cuota_numero}</small>
                                              <strong className={credito.proxima_cuota_estado === 'Vencida' ? 'text-danger' : 'text-primary'}>
                                                {formatDate(credito.proxima_cuota_fecha)}
                                              </strong>
                                              {credito.proxima_cuota_estado === 'Vencida' && (
                                                <div>
                                                  <span className="badge bg-danger mt-1" style={{fontSize: '0.7rem'}}>
                                                    VENCIDA {credito.dias_hasta_cuota ? `(${Math.abs(credito.dias_hasta_cuota)} días)` : ''}
                                                  </span>
                                                </div>
                                              )}
                                              {credito.proxima_cuota_estado === 'Pendiente' && credito.dias_hasta_cuota !== undefined && (
                                                <div>
                                                  {credito.dias_hasta_cuota === 0 ? (
                                                    <span className="badge bg-warning text-dark mt-1" style={{fontSize: '0.7rem'}}>
                                                      VENCE HOY
                                                    </span>
                                                  ) : credito.dias_hasta_cuota > 0 && credito.dias_hasta_cuota <= 7 ? (
                                                    <span className="badge bg-info mt-1" style={{fontSize: '0.7rem'}}>
                                                      En {credito.dias_hasta_cuota} día{credito.dias_hasta_cuota !== 1 ? 's' : ''}
                                                    </span>
                                                  ) : null}
                                                </div>
                                              )}
                                            </div>
                                          ) : (
                                            <span className="text-muted">-</span>
                                          )}
                                        </td>
                                        <td>
                                          {credito.proxima_cuota_monto ? (
                                            <strong className="text-success">
                                              {formatCurrency(credito.proxima_cuota_monto)}
                                            </strong>
                                          ) : (
                                            <span className="text-muted">-</span>
                                          )}
                                        </td>
                                        <td>
                                          <span className={`badge ${getEstadoColor(credito.estado_credito)}`}>
                                            {credito.estado_credito}
                                          </span>
                                        </td>
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          )}

                          {/* Créditos cancelados/pagados del cliente */}
                          {cliente.creditos_cancelados && cliente.creditos_cancelados.length > 0 && (
                            <div className="mt-4">
                              <h6 className="mb-2">
                                <span className="badge bg-success me-2">Historial</span>
                                Créditos Cancelados
                              </h6>
                              <div className="table-responsive">
                                <table className="table table-sm table-hover mb-0">
                                  <thead className="table-light">
                                    <tr>
                                      <th>ID Crédito</th>
                                      <th>Monto Aprobado</th>
                                      <th>Fecha Otorgamiento</th>
                                      <th>Fecha Vencimiento</th>
                                      <th>Cuotas</th>
                                      <th>Pagos Realizados</th>
                                      <th>Último Pago</th>
                                      <th>Estado</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {cliente.creditos_cancelados.map((credito) => (
                                      <tr key={`cancelado-${credito.id_credito}`}>
                                        <td>
                                          <strong>#{credito.id_credito}</strong>
                                        </td>
                                        <td>
                                          <strong className="text-success">
                                            {formatCurrency(credito.monto_aprobado)}
                                          </strong>
                                        </td>
                                        <td>{formatDate(credito.fecha_otorgamiento)}</td>
                                        <td>{formatDate(credito.fecha_vencimiento)}</td>
                                        <td>
                                          <span className="badge bg-success">
                                            {credito.total_cuotas} / {credito.total_cuotas}
                                          </span>
                                        </td>
                                        <td>
                                          <span className="badge bg-info">
                                            {credito.cuotas_pagadas} pagos
                                          </span>
                                        </td>
                                        <td>{formatDate(credito.fecha_ultimo_pago)}</td>
                                        <td>
                                          <span className="badge bg-success">
                                            {credito.estado_credito}
                                          </span>
                                        </td>
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      <style>{`
        .avatar-circle {
          width: 50px;
          height: 50px;
          border-radius: 50%;
          background-color: #e3f2fd;
          display: flex;
          align-items: center;
          justify-content: center;
          color: #1976d2;
        }
      `}</style>
    </div>
  );
};

export default ConsultasPage;

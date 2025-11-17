import React from 'react';
import { createPortal } from 'react-dom';
import {
  User,
  DollarSign,
  Percent,
  Calendar as CalendarIcon,
  Clock,
  Shield,
  Users as UsersIcon,
  X,
  Search
} from 'lucide-react';

const CreditFormModal = ({
  show,
  onClose,
  onSubmit,
  formData,
  setFormData,
  editingCredito,
  clientes,
  trabajadoresDisponibles = [],
  segurosCatalog,
  hasPermission,
  isLoading,
  t,
  // Fiadores
  fiadorQuery,
  setFiadorQuery,
  fiadorResults,
  setFiadorResults,
  selectedFiadores,
  setSelectedFiadores,
  // Avales
  avalQuery,
  setAvalQuery,
  avalResults,
  setAvalResults,
  selectedAvales,
  setSelectedAvales,
  // Services
  peopleService
}) => {
  const handleFiadorSearch = async () => {
    if (!fiadorQuery || fiadorQuery.trim().length < 2) {
      alert('Ingrese al menos 2 caracteres');
      return;
    }
    try {
      const res = await peopleService.search(fiadorQuery, { limit: 10 });
      const items = res?.data?.data || res?.data || [];
      setFiadorResults(items);
    } catch (e) {
      alert('Error buscando personas');
    }
  };

  const handleAvalSearch = async () => {
    if (!avalQuery || avalQuery.trim().length < 2) {
      alert('Ingrese al menos 2 caracteres');
      return;
    }
    try {
      const res = await peopleService.search(avalQuery, { limit: 10 });
      const items = res?.data?.data || res?.data || [];
      setAvalResults(items);
    } catch (e) {
      alert('Error buscando personas');
    }
  };

  const addFiador = (person) => {
    if (!selectedFiadores.find(x => x.dni === person.dni)) {
      const next = [...selectedFiadores, person];
      setSelectedFiadores(next);
      const joined = Array.from(new Set([
        ...(formData.fiadores_dnis_text || '').split(',').map(s => s.trim()).filter(Boolean),
        person.dni
      ])).join(',');
      setFormData({ ...formData, fiadores_dnis_text: joined });
    }
  };

  const removeFiador = (dni) => {
    const next = selectedFiadores.filter(x => x.dni !== dni);
    setSelectedFiadores(next);
    const remaining = (formData.fiadores_dnis_text || '')
      .split(',')
      .map(s => s.trim())
      .filter(Boolean)
      .filter(d => d !== dni);
    setFormData({ ...formData, fiadores_dnis_text: remaining.join(',') });
  };

  const addAval = (person) => {
    if (!selectedAvales.find(x => x.dni === person.dni)) {
      const next = [...selectedAvales, person];
      setSelectedAvales(next);
      const joined = Array.from(new Set([
        ...(formData.avales_dnis_text || '').split(',').map(s => s.trim()).filter(Boolean),
        person.dni
      ])).join(',');
      setFormData({ ...formData, avales_dnis_text: joined });
    }
  };

  const removeAval = (dni) => {
    const next = selectedAvales.filter(x => x.dni !== dni);
    setSelectedAvales(next);
    const remaining = (formData.avales_dnis_text || '')
      .split(',')
      .map(s => s.trim())
      .filter(Boolean)
      .filter(d => d !== dni);
    setFormData({ ...formData, avales_dnis_text: remaining.join(',') });
  };

  if (!show) return null;

  return createPortal(
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2>{editingCredito ? t('creditos.editar_credito') : t('creditos.crear_credito')}</h2>
          <button type="button" className="modal-close" onClick={onClose}>
            <X size={24} />
          </button>
        </div>

        <form onSubmit={onSubmit}>
          <div className="modal-body">
            {!editingCredito ? (
              <div className="grid grid-cols-2 gap-4">
                {/* Información del Crédito */}
                <div className="form-section col-span-2">
                  <h3>
                    <DollarSign size={20} className="inline-block mr-2" />
                    Información del Crédito
                  </h3>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label">
                        <User size={16} className="inline-block mr-1" />
                        Cliente *
                      </label>
                      <select
                        className="form-input"
                        value={formData.id_cliente}
                        onChange={(e) => setFormData({ ...formData, id_cliente: e.target.value })}
                        required
                      >
                        <option value="">Seleccionar cliente...</option>
                        
                        {/* Clientes existentes */}
                        {clientes.length > 0 && (
                          <optgroup label="Clientes Registrados">
                            {clientes.map((cliente) => (
                              <option key={cliente.id_cliente} value={cliente.id_cliente}>
                                {cliente.nombres} {cliente.apellidos || `${cliente.apellido_paterno || ''} ${cliente.apellido_materno || ''}`} - {cliente.dni}
                              </option>
                            ))}
                          </optgroup>
                        )}
                        
                        {/* Trabajadores disponibles (solo para gerente) */}
                        {hasPermission('gerente') && trabajadoresDisponibles.length > 0 && (
                          <optgroup label="Trabajadores (Convertir a Cliente)">
                            {trabajadoresDisponibles.map((trabajador) => (
                              <option key={`trabajador-${trabajador.dni}`} value={`trabajador-${trabajador.dni}`}>
                                {trabajador.nombres} {trabajador.apellido_paterno} {trabajador.apellido_materno} - {trabajador.dni} [!]
                              </option>
                            ))}
                          </optgroup>
                        )}
                      </select>
                      {hasPermission('gerente') && trabajadoresDisponibles.length > 0 && (
                        <small style={{ color: '#6b7280', fontSize: '0.875rem', marginTop: '0.25rem', display: 'block' }}>
                          [INFO] Los trabajadores se convertirán automáticamente en clientes al crear el crédito
                        </small>
                      )}
                    </div>

                    <div className="form-group">
                      <label className="form-label">
                        <DollarSign size={16} className="inline-block mr-1" />
                        Monto Original (S/) *
                      </label>
                      <input
                        type="number"
                        className="form-input"
                        value={formData.monto_original}
                        onChange={(e) => setFormData({ ...formData, monto_original: e.target.value })}
                        min="1"
                        step="0.01"
                        required
                        placeholder="0.00"
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">
                        <DollarSign size={16} className="inline-block mr-1" />
                        Monto Aprobado (S/)
                      </label>
                      <input
                        type="number"
                        className="form-input"
                        value={formData.monto_aprobado}
                        onChange={(e) => setFormData({ ...formData, monto_aprobado: e.target.value })}
                        min="0"
                        step="0.01"
                        placeholder="Se asignará automáticamente"
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">
                        <CalendarIcon size={16} className="inline-block mr-1" />
                        Plazo (meses) *
                      </label>
                      <input
                        type="number"
                        className="form-input"
                        value={formData.plazos_meses}
                        onChange={(e) => setFormData({ ...formData, plazos_meses: e.target.value })}
                        min="1"
                        max="360"
                        required
                        placeholder="12"
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">
                        <Percent size={16} className="inline-block mr-1" />
                        Tasa de Interés (%)
                      </label>
                      <input
                        type="number"
                        className="form-input"
                        value={formData.tasa_interes}
                        onChange={(e) => setFormData({ ...formData, tasa_interes: e.target.value })}
                        min="0"
                        max="100"
                        step="0.01"
                        placeholder="12.5"
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">
                        <Clock size={16} className="inline-block mr-1" />
                        Período de Pago
                      </label>
                      <select
                        className="form-input"
                        value={formData.periodo_pago}
                        onChange={(e) => setFormData({ ...formData, periodo_pago: e.target.value })}
                      >
                        <option value="Diario">Diario</option>
                        <option value="Semanal">Semanal</option>
                        <option value="Quincenal">Quincenal</option>
                        <option value="Mensual">Mensual</option>
                      </select>
                    </div>
                  </div>
                </div>

                {/* Información Adicional */}
                <div className="form-section col-span-2">
                  <h3>
                    <Shield size={20} className="inline-block mr-2" />
                    Información Adicional
                  </h3>

                  <div className="grid grid-cols-1 gap-4">
                    {/* Seguros */}
                    <div className="form-group">
                      <label className="form-label">Seguros (IDs, separados por coma)</label>
                      <input
                        type="text"
                        className="form-input"
                        value={formData.seguros_ids_text}
                        onChange={(e) => setFormData({ ...formData, seguros_ids_text: e.target.value })}
                        placeholder="Ej: 1,2,3"
                      />
                      {hasPermission('gerente') && segurosCatalog.length > 0 && (
                        <div className="mt-2">
                          <label className="form-label text-sm">Seleccionar Seguros</label>
                          <select
                            multiple
                            className="form-input"
                            style={{ minHeight: '100px' }}
                            onChange={(e) => {
                              const selected = Array.from(e.target.selectedOptions).map(o => o.value);
                              setFormData({ ...formData, seguros_ids_text: selected.join(',') });
                            }}
                          >
                            {segurosCatalog.map(seg => (
                              <option key={seg.id_seguro} value={seg.id_seguro}>
                                {seg.nombre_seguro} - S/. {Number(seg.costo || 0).toFixed(2)}
                              </option>
                            ))}
                          </select>
                        </div>
                      )}
                    </div>

                    {/* Fiadores */}
                    <div className="form-group">
                      <label className="form-label">
                        <UsersIcon size={16} className="inline-block mr-1" />
                        Fiadores (DNIs, separados por coma)
                      </label>
                      <input
                        type="text"
                        className="form-input"
                        value={formData.fiadores_dnis_text}
                        onChange={(e) => setFormData({ ...formData, fiadores_dnis_text: e.target.value })}
                        placeholder="Ej: 12345678,87654321"
                      />

                      <div className="mt-2">
                        <label className="form-label text-sm">Buscar Fiador</label>
                        <div className="flex gap-2">
                          <input
                            type="text"
                            className="form-input flex-1"
                            placeholder="Buscar por DNI o nombre"
                            value={fiadorQuery}
                            onChange={(e) => setFiadorQuery(e.target.value)}
                          />
                          <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={handleFiadorSearch}
                          >
                            <Search size={16} />
                          </button>
                        </div>

                        {fiadorResults.length > 0 && (
                          <div className="mt-2 border rounded-lg p-2" style={{ maxHeight: '160px', overflowY: 'auto' }}>
                            {fiadorResults.map((p, idx) => (
                              <button
                                key={idx}
                                type="button"
                                className="w-full text-left p-2 rounded flex justify-between items-center"
                                style={{ cursor: 'pointer' }}
                                onMouseEnter={(e) => e.currentTarget.style.backgroundColor = '#f9fafb'}
                                onMouseLeave={(e) => e.currentTarget.style.backgroundColor = 'transparent'}
                                onClick={() => addFiador(p)}
                              >
                                <span className="text-sm">{p.dni} - {p.nombres} {p.apellido_paterno}</span>
                                <span className="badge badge-info text-xs">Agregar</span>
                              </button>
                            ))}
                          </div>
                        )}

                        {selectedFiadores.length > 0 && (
                          <div className="mt-2 flex flex-wrap gap-2">
                            {selectedFiadores.map((p, idx) => (
                              <span key={idx} className="credit-chip">
                                {p.dni}
                                <button
                                  type="button"
                                  onClick={() => removeFiador(p.dni)}
                                  style={{ marginLeft: '4px', cursor: 'pointer' }}
                                >
                                  ×
                                </button>
                              </span>
                            ))}
                          </div>
                        )}
                      </div>
                    </div>

                    {/* Avales */}
                    <div className="form-group">
                      <label className="form-label">
                        <UsersIcon size={16} className="inline-block mr-1" />
                        Avales (DNIs, separados por coma)
                      </label>
                      <input
                        type="text"
                        className="form-input"
                        value={formData.avales_dnis_text}
                        onChange={(e) => setFormData({ ...formData, avales_dnis_text: e.target.value })}
                        placeholder="Ej: 11223344,55667788"
                      />

                      <div className="mt-2">
                        <label className="form-label text-sm">Buscar Aval</label>
                        <div className="flex gap-2">
                          <input
                            type="text"
                            className="form-input flex-1"
                            placeholder="Buscar por DNI o nombre"
                            value={avalQuery}
                            onChange={(e) => setAvalQuery(e.target.value)}
                          />
                          <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={handleAvalSearch}
                          >
                            <Search size={16} />
                          </button>
                        </div>

                        {avalResults.length > 0 && (
                          <div className="mt-2 border rounded-lg p-2" style={{ maxHeight: '160px', overflowY: 'auto' }}>
                            {avalResults.map((p, idx) => (
                              <button
                                key={idx}
                                type="button"
                                className="w-full text-left p-2 rounded flex justify-between items-center"
                                style={{ cursor: 'pointer' }}
                                onMouseEnter={(e) => e.currentTarget.style.backgroundColor = '#f9fafb'}
                                onMouseLeave={(e) => e.currentTarget.style.backgroundColor = 'transparent'}
                                onClick={() => addAval(p)}
                              >
                                <span className="text-sm">{p.dni} - {p.nombres} {p.apellido_paterno}</span>
                                <span className="badge badge-info text-xs">Agregar</span>
                              </button>
                            ))}
                          </div>
                        )}

                        {selectedAvales.length > 0 && (
                          <div className="mt-2 flex flex-wrap gap-2">
                            {selectedAvales.map((p, idx) => (
                              <span key={idx} className="credit-chip">
                                {p.dni}
                                <button
                                  type="button"
                                  onClick={() => removeAval(p.dni)}
                                  style={{ marginLeft: '4px', cursor: 'pointer' }}
                                >
                                  ×
                                </button>
                              </span>
                            ))}
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              <div className="grid grid-cols-2 gap-4">
                <div className="form-group">
                  <label className="form-label">Estado</label>
                  <select
                    className="form-input"
                    value={formData.estado_credito}
                    onChange={(e) => setFormData({ ...formData, estado_credito: e.target.value })}
                  >
                    <option value="Pendiente">Pendiente</option>
                    <option value="Aprobado">Aprobado</option>
                    <option value="Desaprobado">Desaprobado</option>
                    <option value="En Mora">En Mora</option>
                    <option value="Cancelado">Cancelado</option>
                    <option value="Pagado">Pagado</option>
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Mora (S/)</label>
                  <input
                    type="number"
                    className="form-input"
                    value={formData.mora}
                    onChange={(e) => setFormData({ ...formData, mora: e.target.value })}
                    min="0"
                    step="0.01"
                  />
                </div>
              </div>
            )}
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              Cancelar
            </button>
            <button type="submit" className="btn btn-primary" disabled={isLoading}>
              {isLoading ? (
                <>
                  <div className="spinner-border spinner-border-sm me-2" role="status">
                    <span className="visually-hidden">Cargando...</span>
                  </div>
                  Guardando...
                </>
              ) : (
                editingCredito ? 'Actualizar' : 'Crear'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>,
    document.body
  );
};

export default CreditFormModal;

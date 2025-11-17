import { useState } from 'react';
import { createPortal } from 'react-dom';
import { X, AlertCircle, Calendar, DollarSign, Percent, Clock, FileText } from 'lucide-react';

const ReprogramacionModal = ({ isOpen, onClose, credito, onSubmit, isLoading }) => {
  const [formData, setFormData] = useState({
    motivo: '',
    nuevo_plazo_meses: credito?.plazos_meses || '',
    nueva_tasa_interes: credito?.tasa_interes || '',
    nuevo_monto: credito?.monto_aprobado || credito?.monto_original || '',
    nuevo_periodo_pago: credito?.periodo_pago || 'Mensual',
    observaciones: ''
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    
    if (!formData.motivo.trim()) {
      alert('El motivo es requerido');
      return;
    }
    
    onSubmit({
      id_credito: credito.id_credito,
      ...formData
    });
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  if (!isOpen) return null;

  const formatCurrency = (n) => new Intl.NumberFormat('es-PE', { 
    style: 'currency', 
    currency: 'PEN' 
  }).format(Number(n || 0));

  return createPortal(
    <div className="modal-overlay" onClick={onClose} style={{ zIndex: 9999 }}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '700px', maxHeight: '90vh', overflow: 'auto' }}>
        {/* Header */}
        <div className="modal-header" style={{ background: 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)', color: 'white', borderBottom: 'none' }}>
          <div>
            <h2 style={{ color: 'white', margin: 0 }}>Solicitar Reprogramación</h2>
            <p style={{ color: 'rgba(255,255,255,0.9)', fontSize: '0.9rem', margin: '0.25rem 0 0 0' }}>
              Crédito #{credito?.id_credito} - {credito?.nombres} {credito?.apellido_paterno}
            </p>
          </div>
          <button 
            type="button"
            className="modal-close"
            onClick={onClose}
            disabled={isLoading}
            style={{ color: 'white' }}
          >
            <X size={24} />
          </button>
        </div>

        {/* Body */}
        <form onSubmit={handleSubmit} className="modal-body" style={{ maxHeight: 'calc(90vh - 200px)', overflowY: 'auto' }}>
          {/* Información Actual */}
          <div style={{ background: '#e0f2fe', border: '1px solid #7dd3fc', borderRadius: '8px', padding: '1rem', marginBottom: '1.5rem' }}>
            <h3 style={{ fontWeight: '600', color: '#0c4a6e', marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <AlertCircle size={18} />
              Condiciones Actuales del Crédito
            </h3>
            <div className="row g-3">
              <div className="col-md-3">
                <span style={{ color: '#0369a1', fontWeight: '500', fontSize: '0.875rem' }}>Monto:</span>
                <p style={{ color: '#0c4a6e', fontWeight: '600', margin: '0.25rem 0 0 0' }}>{formatCurrency(credito?.monto_aprobado || credito?.monto_original)}</p>
              </div>
              <div className="col-md-3">
                <span style={{ color: '#0369a1', fontWeight: '500', fontSize: '0.875rem' }}>Plazo:</span>
                <p style={{ color: '#0c4a6e', fontWeight: '600', margin: '0.25rem 0 0 0' }}>{credito?.plazos_meses} meses</p>
              </div>
              <div className="col-md-3">
                <span style={{ color: '#0369a1', fontWeight: '500', fontSize: '0.875rem' }}>Tasa:</span>
                <p style={{ color: '#0c4a6e', fontWeight: '600', margin: '0.25rem 0 0 0' }}>{credito?.tasa_interes}% mensual</p>
              </div>
              <div className="col-md-3">
                <span style={{ color: '#0369a1', fontWeight: '500', fontSize: '0.875rem' }}>Periodo:</span>
                <p style={{ color: '#0c4a6e', fontWeight: '600', margin: '0.25rem 0 0 0' }}>{credito?.periodo_pago}</p>
              </div>
            </div>
          </div>

          {/* Motivo */}
          <div className="form-group">
            <label className="form-label">
              Motivo de la Reprogramación *
            </label>
            <textarea
              name="motivo"
              value={formData.motivo}
              onChange={handleChange}
              className="form-control"
              rows="3"
              placeholder="Explica por qué se necesita reprogramar este crédito..."
              required
              disabled={isLoading}
            />
          </div>

          {/* Nuevas Condiciones */}
          <div style={{ 
            background: '#f8f9fa', 
            borderRadius: '10px', 
            padding: '1.5rem',
            marginBottom: '1.5rem',
            border: '1px solid #dee2e6'
          }}>
            <h3 style={{ 
              fontWeight: '600', 
              marginBottom: '1.25rem',
              color: '#495057',
              fontSize: '1.1rem',
              display: 'flex',
              alignItems: 'center',
              gap: '0.5rem'
            }}>
              <FileText size={20} />
              Nuevas Condiciones Propuestas
            </h3>
            
            {/* Fila 1: Monto y Plazo */}
            <div style={{ display: 'flex', gap: '1rem', marginBottom: '1rem' }}>
              <div style={{ flex: 1 }}>
                <label className="form-label d-flex align-items-center gap-2" style={{ 
                  fontWeight: '600', 
                  color: '#495057',
                  marginBottom: '0.5rem',
                  fontSize: '0.9rem'
                }}>
                  <DollarSign size={16} />
                  Nuevo Monto (S/.)
                </label>
                <input
                  type="number"
                  name="nuevo_monto"
                  value={formData.nuevo_monto}
                  onChange={handleChange}
                  className="form-control"
                  style={{ 
                    borderRadius: '6px',
                    border: '1px solid #ced4da',
                    fontSize: '1rem',
                    padding: '0.6rem 0.75rem',
                    width: '100%'
                  }}
                  step="0.01"
                  min="0"
                  disabled={isLoading}
                />
              </div>

              <div style={{ flex: 1 }}>
                <label className="form-label d-flex align-items-center gap-2" style={{ 
                  fontWeight: '600', 
                  color: '#495057',
                  marginBottom: '0.5rem',
                  fontSize: '0.9rem'
                }}>
                  <Calendar size={16} />
                  Nuevo Plazo (meses)
                </label>
                <input
                  type="number"
                  name="nuevo_plazo_meses"
                  value={formData.nuevo_plazo_meses}
                  onChange={handleChange}
                  className="form-control"
                  style={{ 
                    borderRadius: '6px',
                    border: '1px solid #ced4da',
                    fontSize: '1rem',
                    padding: '0.6rem 0.75rem',
                    width: '100%'
                  }}
                  min="1"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Fila 2: Tasa y Periodo */}
            <div style={{ display: 'flex', gap: '1rem' }}>
              <div style={{ flex: 1 }}>
                <label className="form-label d-flex align-items-center gap-2" style={{ 
                  fontWeight: '600', 
                  color: '#495057',
                  marginBottom: '0.5rem',
                  fontSize: '0.9rem'
                }}>
                  <Percent size={16} />
                  Nueva Tasa de Interés (% mensual)
                </label>
                <input
                  type="number"
                  name="nueva_tasa_interes"
                  value={formData.nueva_tasa_interes}
                  onChange={handleChange}
                  className="form-control"
                  style={{ 
                    borderRadius: '6px',
                    border: '1px solid #ced4da',
                    fontSize: '1rem',
                    padding: '0.6rem 0.75rem',
                    width: '100%'
                  }}
                  step="0.01"
                  min="0"
                  disabled={isLoading}
                />
              </div>

              <div style={{ flex: 1 }}>
                <label className="form-label d-flex align-items-center gap-2" style={{ 
                  fontWeight: '600', 
                  color: '#495057',
                  marginBottom: '0.5rem',
                  fontSize: '0.9rem'
                }}>
                  <Clock size={16} />
                  Nuevo Periodo de Pago
                </label>
                <select
                  name="nuevo_periodo_pago"
                  value={formData.nuevo_periodo_pago}
                  onChange={handleChange}
                  className="form-select"
                  style={{ 
                    borderRadius: '6px',
                    border: '1px solid #ced4da',
                    fontSize: '1rem',
                    padding: '0.6rem 0.75rem',
                    width: '100%'
                  }}
                  disabled={isLoading}
                >
                  <option value="Diario">Diario</option>
                  <option value="Semanal">Semanal</option>
                  <option value="Quincenal">Quincenal</option>
                  <option value="Mensual">Mensual</option>
                </select>
              </div>
            </div>
          </div>

          {/* Observaciones */}
          <div className="form-group">
            <label className="form-label">
              Observaciones Adicionales
            </label>
            <textarea
              name="observaciones"
              value={formData.observaciones}
              onChange={handleChange}
              className="form-control"
              rows="2"
              placeholder="Información adicional relevante..."
              disabled={isLoading}
            />
          </div>

          {/* Advertencia */}
          <div className="alert alert-warning" style={{ marginBottom: 0 }}>
            <strong>Nota:</strong> Esta solicitud será enviada al gerente para su aprobación. 
            Una vez aprobada, se generarán nuevas cuotas y se mantendrá el historial del crédito anterior.
          </div>
        </form>

        {/* Footer */}
        <div className="modal-footer">
          <button
            type="button"
            onClick={onClose}
            className="btn btn-secondary"
            disabled={isLoading}
          >
            Cancelar
          </button>
          <button
            onClick={handleSubmit}
            className="btn btn-primary"
            disabled={isLoading}
          >
            {isLoading ? 'Enviando...' : 'Solicitar Reprogramación'}
          </button>
        </div>
      </div>
    </div>,
    document.body
  );
};

export default ReprogramacionModal;

import React from 'react';
import { useNavigate } from 'react-router-dom';
import { AlertTriangle, Lock } from 'lucide-react';
import { useCaja } from '../../contexts/CajaContext';
import './CajaRequerida.css';

const CajaRequerida = ({ children }) => {
  const { cajaActiva, loading, isTrabajador } = useCaja();
  const navigate = useNavigate();

  // Si no es trabajador, permitir acceso
  if (!isTrabajador) {
    return children;
  }

  // Mostrar loading solo por un momento corto
  if (loading) {
    return (
      <div className="caja-loading">
        <div className="spinner"></div>
        <p>Cargando...</p>
      </div>
    );
  }

  // Si la caja no está activa, mostrar bloqueo
  if (!cajaActiva) {
    return (
      <div className="caja-bloqueada-container">
        <div className="caja-bloqueada-card">
          <div className="caja-bloqueada-icon">
            <Lock size={64} />
          </div>
          <h1>Caja No Activa</h1>
          <p>Debes activar tu caja antes de poder usar esta funcionalidad.</p>
          
          <div className="caja-bloqueada-steps">
            <div className="step">
              <AlertTriangle size={20} />
              <span>Tu caja debe estar habilitada por el gerente</span>
            </div>
            <div className="step">
              <AlertTriangle size={20} />
              <span>La caja principal debe estar abierta</span>
            </div>
            <div className="step">
              <AlertTriangle size={20} />
              <span>Debes activar tu caja desde la sección "Cajas"</span>
            </div>
          </div>

          <button 
            className="btn-primary-large"
            onClick={() => navigate('/cajas')}
          >
            Ir a Mi Caja
          </button>
        </div>
      </div>
    );
  }

  // Si la caja está activa, mostrar el contenido
  return children;
};

export default CajaRequerida;

import React, { useState } from 'react';
import { 
  Settings, 
  Database, 
  Shield, 
  Bell, 
  Globe, 
  CreditCard,
  Percent,
  Save,
  HardDrive
} from 'lucide-react';
import { toast } from 'react-hot-toast';
import BackupSettings from '../components/common/BackupSettings';
import './Pages.css';

const ConfiguracionPage = () => {
  const [activeTab, setActiveTab] = useState('general');
  const [isLoading, setIsLoading] = useState(false);
  const [config, setConfig] = useState({
    // Configuración general
    nombreSistema: 'Sistema de Créditos',
    version: '1.0.0',
    monedaPrincipal: 'PEN',
    decimalesMoneda: 2,
    
    // Configuración de créditos
    tasaInteresDefecto: 18.00,
    montoMaximoCredito: 50000.00,
    montoMinimoCredito: 100.00,
    plazoMaximoMeses: 60,
    plazoMinimoMeses: 1,
    
    // Configuración de notificaciones
    notificarVencimientos: true,
    diasAntesVencimiento: 7,
    notificarPagos: true,
    notificarAprobaciones: true,
    
    // Configuración de seguridad
    sesionExpiraMinutos: 120,
    intentosLoginMax: 3,
    bloqueoTiempoMinutos: 15
  });

  const handleSave = async () => {
    setIsLoading(true);
    try {
      // Aquí iría la llamada al API para guardar la configuración
      // await api.post('/configuracion', config);
      
      // Por ahora solo simulamos el guardado
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      toast.success('Configuración guardada exitosamente');
    } catch (error) {
      console.error('Error saving config:', error);
      toast.error('Error al guardar la configuración');
    } finally {
      setIsLoading(false);
    }
  };

  const tabs = [
    { id: 'general', label: 'General', icon: Settings },
    { id: 'creditos', label: 'Créditos', icon: CreditCard },
    { id: 'respaldos', label: 'Respaldos', icon: HardDrive },
    { id: 'notificaciones', label: 'Notificaciones', icon: Bell },
    { id: 'seguridad', label: 'Seguridad', icon: Shield },
    { id: 'sistema', label: 'Sistema', icon: Database }
  ];

  return (
    <div className="page-container">
      <div className="page-header">
        <div className="flex items-center gap-4">
          <Settings size={32} />
          <div>
            <h1>Configuración del Sistema</h1>
            <p>Configura parámetros generales del sistema</p>
          </div>
        </div>
        <div className="flex gap-2">
          <button
            onClick={handleSave}
            disabled={isLoading}
            className="btn btn-primary"
          >
            <Save size={20} />
            {isLoading ? 'Guardando...' : 'Guardar Cambios'}
          </button>
        </div>
      </div>
      
      <div className="page-content">
        <div className="max-w-6xl">
          {/* Tabs */}
          <div className="card">
            <div className="card-header">
              <div className="tabs">
                {tabs.map(tab => {
                  const IconComponent = tab.icon;
                  return (
                    <button
                      key={tab.id}
                      onClick={() => setActiveTab(tab.id)}
                      className={`tab ${activeTab === tab.id ? 'active' : ''}`}
                    >
                      <IconComponent size={18} />
                      {tab.label}
                    </button>
                  );
                })}
              </div>
            </div>
            
            <div className="card-body">
              {/* General Tab */}
              {activeTab === 'general' && (
                <div className="config-section">
                  <h3>Configuración General</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="form-group">
                      <label>Nombre del Sistema</label>
                      <input
                        type="text"
                        value={config.nombreSistema}
                        onChange={(e) => setConfig(prev => ({ ...prev, nombreSistema: e.target.value }))}
                        placeholder="Nombre del sistema"
                      />
                    </div>
                    <div className="form-group">
                      <label>Versión</label>
                      <input
                        type="text"
                        value={config.version}
                        disabled
                        placeholder="Versión del sistema"
                      />
                    </div>
                    <div className="form-group">
                      <label>Moneda Principal</label>
                      <select
                        value={config.monedaPrincipal}
                        onChange={(e) => setConfig(prev => ({ ...prev, monedaPrincipal: e.target.value }))}
                      >
                        <option value="PEN">Soles Peruanos (PEN)</option>
                        <option value="USD">Dólares (USD)</option>
                        <option value="EUR">Euros (EUR)</option>
                      </select>
                    </div>
                    <div className="form-group">
                      <label>Decimales para Moneda</label>
                      <input
                        type="number"
                        min="0"
                        max="4"
                        value={config.decimalesMoneda}
                        onChange={(e) => setConfig(prev => ({ ...prev, decimalesMoneda: parseInt(e.target.value) }))}
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* Créditos Tab */}
              {activeTab === 'creditos' && (
                <div className="config-section">
                  <h3>Configuración de Créditos</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="form-group">
                      <label>Tasa de Interés por Defecto (%)</label>
                      <div className="input-group">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          max="100"
                          value={config.tasaInteresDefecto}
                          onChange={(e) => setConfig(prev => ({ ...prev, tasaInteresDefecto: parseFloat(e.target.value) }))}
                        />
                        <Percent size={18} className="input-icon-right" />
                      </div>
                    </div>
                    <div className="form-group">
                      <label>Monto Máximo de Crédito</label>
                      <input
                        type="number"
                        step="100"
                        min="0"
                        value={config.montoMaximoCredito}
                        onChange={(e) => setConfig(prev => ({ ...prev, montoMaximoCredito: parseFloat(e.target.value) }))}
                      />
                    </div>
                    <div className="form-group">
                      <label>Monto Mínimo de Crédito</label>
                      <input
                        type="number"
                        step="10"
                        min="0"
                        value={config.montoMinimoCredito}
                        onChange={(e) => setConfig(prev => ({ ...prev, montoMinimoCredito: parseFloat(e.target.value) }))}
                      />
                    </div>
                    <div className="form-group">
                      <label>Plazo Máximo (meses)</label>
                      <input
                        type="number"
                        min="1"
                        max="120"
                        value={config.plazoMaximoMeses}
                        onChange={(e) => setConfig(prev => ({ ...prev, plazoMaximoMeses: parseInt(e.target.value) }))}
                      />
                    </div>
                  </div>
                </div>
              )}

              

              {/* Respaldos Tab */}
              {activeTab === 'respaldos' && (
                <div className="config-section">
                  <BackupSettings />
                </div>
              )}

              {/* Notificaciones Tab */}
              {activeTab === 'notificaciones' && (
                <div className="config-section">
                  <h3>Configuración de Notificaciones</h3>
                  <div className="grid grid-cols-1 gap-6">
                    <div className="form-group">
                      <label className="checkbox-label">
                        <input
                          type="checkbox"
                          checked={config.notificarVencimientos}
                          onChange={(e) => setConfig(prev => ({ ...prev, notificarVencimientos: e.target.checked }))}
                        />
                        Notificar vencimientos próximos
                      </label>
                    </div>
                    {config.notificarVencimientos && (
                      <div className="form-group">
                        <label>Días antes del vencimiento</label>
                        <input
                          type="number"
                          min="1"
                          max="30"
                          value={config.diasAntesVencimiento}
                          onChange={(e) => setConfig(prev => ({ ...prev, diasAntesVencimiento: parseInt(e.target.value) }))}
                        />
                      </div>
                    )}
                    <div className="form-group">
                      <label className="checkbox-label">
                        <input
                          type="checkbox"
                          checked={config.notificarPagos}
                          onChange={(e) => setConfig(prev => ({ ...prev, notificarPagos: e.target.checked }))}
                        />
                        Notificar pagos realizados
                      </label>
                    </div>
                    <div className="form-group">
                      <label className="checkbox-label">
                        <input
                          type="checkbox"
                          checked={config.notificarAprobaciones}
                          onChange={(e) => setConfig(prev => ({ ...prev, notificarAprobaciones: e.target.checked }))}
                        />
                        Notificar aprobaciones de crédito
                      </label>
                    </div>
                  </div>
                </div>
              )}

              {/* Seguridad Tab */}
              {activeTab === 'seguridad' && (
                <div className="config-section">
                  <h3>Configuración de Seguridad</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="form-group">
                      <label>Expiración de Sesión (minutos)</label>
                      <input
                        type="number"
                        min="30"
                        max="480"
                        value={config.sesionExpiraMinutos}
                        onChange={(e) => setConfig(prev => ({ ...prev, sesionExpiraMinutos: parseInt(e.target.value) }))}
                      />
                    </div>
                    <div className="form-group">
                      <label>Máximo Intentos de Login</label>
                      <input
                        type="number"
                        min="3"
                        max="10"
                        value={config.intentosLoginMax}
                        onChange={(e) => setConfig(prev => ({ ...prev, intentosLoginMax: parseInt(e.target.value) }))}
                      />
                    </div>
                    <div className="form-group">
                      <label>Tiempo de Bloqueo (minutos)</label>
                      <input
                        type="number"
                        min="5"
                        max="60"
                        value={config.bloqueoTiempoMinutos}
                        onChange={(e) => setConfig(prev => ({ ...prev, bloqueoTiempoMinutos: parseInt(e.target.value) }))}
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* Sistema Tab */}
              {activeTab === 'sistema' && (
                <div className="config-section">
                  <h3>Información del Sistema</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="info-item">
                      <div className="info-label">
                        <Database size={18} />
                        Base de datos
                      </div>
                      <div className="info-value">MySQL 8.0</div>
                    </div>
                    <div className="info-item">
                      <div className="info-label">
                        <Globe size={18} />
                        Versión PHP
                      </div>
                      <div className="info-value">8.0.28</div>
                    </div>
                    <div className="info-item">
                      <div className="info-label">
                        <Settings size={18} />
                        Entorno
                      </div>
                      <div className="info-value">Desarrollo</div>
                    </div>
                    <div className="info-item">
                      <div className="info-label">
                        <Shield size={18} />
                        Último respaldo
                      </div>
                      <div className="info-value">No disponible</div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ConfiguracionPage;
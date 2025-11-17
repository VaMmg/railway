import React, { useState, useEffect } from 'react';
import { 
  Database, 
  Download, 
  Upload, 
  Clock, 
  Calendar, 
  CheckCircle, 
  AlertCircle,
  Settings,
  Play,
  Pause,
  RefreshCw
} from 'lucide-react';
import { toast } from 'react-hot-toast';
import { useLanguage } from '../../contexts/LanguageContext';
import './BackupSettings.css';

const BackupSettings = () => {
  const { t } = useLanguage();
  const [isLoading, setIsLoading] = useState(false);
  const [backupHistory, setBackupHistory] = useState([]);
  const [autoBackupEnabled, setAutoBackupEnabled] = useState(true);
  const [backupConfig, setBackupConfig] = useState({
    intervalDays: 3,
    maxBackups: 10,
    compressionEnabled: true,
    includeUploads: false,
    backupTime: '02:00'
  });
  const [lastBackup, setLastBackup] = useState(null);
  const [nextBackup, setNextBackup] = useState(null);

  // Cargar configuración y historial al montar el componente
  useEffect(() => {
    loadBackupConfig();
    loadBackupHistory();
  }, []);

  const loadBackupConfig = async () => {
    try {
      // Simulación - en producción sería una llamada al API
      const savedConfig = localStorage.getItem('sistemaCredito_backupConfig');
      if (savedConfig) {
        const config = JSON.parse(savedConfig);
        setBackupConfig(config);
        setAutoBackupEnabled(config.autoBackupEnabled || true);
      }
      
      // Calcular próximo respaldo
      calculateNextBackup();
      
      // Obtener último respaldo
      const lastBackupData = localStorage.getItem('sistemaCredito_lastBackup');
      if (lastBackupData) {
        setLastBackup(JSON.parse(lastBackupData));
      }
    } catch (error) {
      console.error('Error loading backup config:', error);
    }
  };

  const loadBackupHistory = async () => {
    try {
      // Simulación - en producción sería una llamada al API
      const savedHistory = localStorage.getItem('sistemaCredito_backupHistory');
      if (savedHistory) {
        setBackupHistory(JSON.parse(savedHistory));
      } else {
        // Datos de ejemplo
        const exampleHistory = [
          {
            id: 1,
            date: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString(),
            type: 'automatic',
            status: 'success',
            size: '15.2 MB',
            duration: '2.3s'
          },
          {
            id: 2,
            date: new Date(Date.now() - 6 * 24 * 60 * 60 * 1000).toISOString(),
            type: 'manual',
            status: 'success',
            size: '14.8 MB',
            duration: '1.9s'
          }
        ];
        setBackupHistory(exampleHistory);
        localStorage.setItem('sistemaCredito_backupHistory', JSON.stringify(exampleHistory));
      }
    } catch (error) {
      console.error('Error loading backup history:', error);
    }
  };

  const calculateNextBackup = () => {
    if (!autoBackupEnabled) {
      setNextBackup(null);
      return;
    }

    const lastBackupDate = lastBackup ? new Date(lastBackup.date) : new Date();
    const nextDate = new Date(lastBackupDate);
    nextDate.setDate(nextDate.getDate() + backupConfig.intervalDays);
    
    // Establecer la hora configurada
    const [hours, minutes] = backupConfig.backupTime.split(':');
    nextDate.setHours(parseInt(hours), parseInt(minutes), 0, 0);
    
    setNextBackup(nextDate);
  };

  const saveBackupConfig = async () => {
    try {
      const configToSave = {
        ...backupConfig,
        autoBackupEnabled
      };
      
      localStorage.setItem('sistemaCredito_backupConfig', JSON.stringify(configToSave));
      calculateNextBackup();
      
      toast.success(t('backupSettings.configSaved') || 'Configuración de respaldos guardada');
    } catch (error) {
      console.error('Error saving backup config:', error);
      toast.error(t('backupSettings.configError') || 'Error al guardar la configuración');
    }
  };

  const createManualBackup = async () => {
    setIsLoading(true);
    try {
      // Simulación del proceso de respaldo
      toast.loading(t('backupSettings.creatingBackup') || 'Creando respaldo...');
      
      // Simular tiempo de procesamiento
      await new Promise(resolve => setTimeout(resolve, 3000));
      
      const newBackup = {
        id: Date.now(),
        date: new Date().toISOString(),
        type: 'manual',
        status: 'success',
        size: `${(Math.random() * 5 + 10).toFixed(1)} MB`,
        duration: `${(Math.random() * 2 + 1).toFixed(1)}s`
      };

      // Actualizar historial
      const updatedHistory = [newBackup, ...backupHistory].slice(0, backupConfig.maxBackups);
      setBackupHistory(updatedHistory);
      localStorage.setItem('sistemaCredito_backupHistory', JSON.stringify(updatedHistory));
      
      // Actualizar último respaldo
      setLastBackup(newBackup);
      localStorage.setItem('sistemaCredito_lastBackup', JSON.stringify(newBackup));
      
      calculateNextBackup();
      
      toast.dismiss();
      toast.success(t('backupSettings.backupSuccess') || 'Respaldo creado exitosamente');
    } catch (error) {
      console.error('Error creating backup:', error);
      toast.dismiss();
      toast.error(t('backupSettings.backupError') || 'Error al crear el respaldo');
    } finally {
      setIsLoading(false);
    }
  };

  const downloadBackup = (backup) => {
    // Simular descarga de respaldo
    toast.success(t('backupSettings.downloadStarted') || 'Descarga iniciada');
    
    // En producción, aquí se haría la descarga real del archivo
    const link = document.createElement('a');
    link.href = '#'; // URL del archivo de respaldo
    link.download = `backup_${backup.date.split('T')[0]}.sql`;
    // link.click();
  };

  const restoreBackup = (backup) => {
    if (window.confirm(t('backupSettings.confirmRestore') || '¿Estás seguro de que quieres restaurar este respaldo? Esta acción no se puede deshacer.')) {
      toast.loading(t('backupSettings.restoringBackup') || 'Restaurando respaldo...');
      
      // Simular proceso de restauración
      setTimeout(() => {
        toast.dismiss();
        toast.success(t('backupSettings.restoreSuccess') || 'Respaldo restaurado exitosamente');
      }, 2000);
    }
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'success':
        return <CheckCircle size={16} className="text-success" />;
      case 'error':
        return <AlertCircle size={16} className="text-error" />;
      default:
        return <Clock size={16} className="text-warning" />;
    }
  };

  const getTypeLabel = (type) => {
    return type === 'automatic' 
      ? (t('backupSettings.automatic') || 'Automático')
      : (t('backupSettings.manual') || 'Manual');
  };

  return (
    <div className="backup-settings">
      <div className="backup-settings-header">
        <h2>{t('backupSettings.title') || 'Configuración de Respaldos'}</h2>
        <p>{t('backupSettings.description') || 'Gestiona los respaldos automáticos y manuales de la base de datos'}</p>
      </div>

      {/* Estado actual */}
      <div className="backup-status-section">
        <div className="status-cards">
          <div className="status-card">
            <div className="status-icon">
              <Database size={24} />
            </div>
            <div className="status-info">
              <h3>{t('backupSettings.lastBackup') || 'Último Respaldo'}</h3>
              <p>{lastBackup ? formatDate(lastBackup.date) : (t('backupSettings.noBackups') || 'Sin respaldos')}</p>
            </div>
          </div>

          <div className="status-card">
            <div className="status-icon">
              <Calendar size={24} />
            </div>
            <div className="status-info">
              <h3>{t('backupSettings.nextBackup') || 'Próximo Respaldo'}</h3>
              <p>
                {autoBackupEnabled && nextBackup 
                  ? formatDate(nextBackup.toISOString())
                  : (t('backupSettings.disabled') || 'Deshabilitado')
                }
              </p>
            </div>
          </div>

          <div className="status-card">
            <div className="status-icon">
              {autoBackupEnabled ? <Play size={24} /> : <Pause size={24} />}
            </div>
            <div className="status-info">
              <h3>{t('backupSettings.autoBackup') || 'Respaldo Automático'}</h3>
              <p>{autoBackupEnabled ? (t('backupSettings.enabled') || 'Habilitado') : (t('backupSettings.disabled') || 'Deshabilitado')}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Acciones rápidas */}
      <div className="backup-actions-section">
        <div className="actions-header">
          <h3>{t('backupSettings.quickActions') || 'Acciones Rápidas'}</h3>
        </div>
        <div className="actions-buttons">
          <button
            onClick={createManualBackup}
            disabled={isLoading}
            className="btn btn-primary"
          >
            <Download size={20} />
            {isLoading ? (t('backupSettings.creating') || 'Creando...') : (t('backupSettings.createBackup') || 'Crear Respaldo')}
          </button>
          
          <button
            onClick={loadBackupHistory}
            className="btn btn-secondary"
          >
            <RefreshCw size={20} />
            {t('backupSettings.refresh') || 'Actualizar'}
          </button>
        </div>
      </div>

      {/* Configuración */}
      <div className="backup-config-section">
        <div className="config-header">
          <h3>{t('backupSettings.configuration') || 'Configuración'}</h3>
        </div>
        
        <div className="config-form">
          <div className="config-row">
            <label className="checkbox-label">
              <input
                type="checkbox"
                checked={autoBackupEnabled}
                onChange={(e) => setAutoBackupEnabled(e.target.checked)}
              />
              {t('backupSettings.enableAutoBackup') || 'Habilitar respaldos automáticos'}
            </label>
          </div>

          {autoBackupEnabled && (
            <>
              <div className="config-row">
                <label>{t('backupSettings.intervalDays') || 'Intervalo (días)'}</label>
                <input
                  type="number"
                  min="1"
                  max="30"
                  value={backupConfig.intervalDays}
                  onChange={(e) => setBackupConfig(prev => ({ ...prev, intervalDays: parseInt(e.target.value) }))}
                />
              </div>

              <div className="config-row">
                <label>{t('backupSettings.backupTime') || 'Hora del respaldo'}</label>
                <input
                  type="time"
                  value={backupConfig.backupTime}
                  onChange={(e) => setBackupConfig(prev => ({ ...prev, backupTime: e.target.value }))}
                />
              </div>
            </>
          )}

          <div className="config-row">
            <label>{t('backupSettings.maxBackups') || 'Máximo de respaldos a mantener'}</label>
            <input
              type="number"
              min="1"
              max="50"
              value={backupConfig.maxBackups}
              onChange={(e) => setBackupConfig(prev => ({ ...prev, maxBackups: parseInt(e.target.value) }))}
            />
          </div>

          <div className="config-row">
            <label className="checkbox-label">
              <input
                type="checkbox"
                checked={backupConfig.compressionEnabled}
                onChange={(e) => setBackupConfig(prev => ({ ...prev, compressionEnabled: e.target.checked }))}
              />
              {t('backupSettings.enableCompression') || 'Habilitar compresión'}
            </label>
          </div>

          <div className="config-actions">
            <button
              onClick={saveBackupConfig}
              className="btn btn-primary"
            >
              <Settings size={20} />
              {t('backupSettings.saveConfig') || 'Guardar Configuración'}
            </button>
          </div>
        </div>
      </div>

      {/* Historial de respaldos */}
      <div className="backup-history-section">
        <div className="history-header">
          <h3>{t('backupSettings.history') || 'Historial de Respaldos'}</h3>
        </div>
        
        <div className="history-table">
          {backupHistory.length > 0 ? (
            <table>
              <thead>
                <tr>
                  <th>{t('backupSettings.date') || 'Fecha'}</th>
                  <th>{t('backupSettings.type') || 'Tipo'}</th>
                  <th>{t('backupSettings.status') || 'Estado'}</th>
                  <th>{t('backupSettings.size') || 'Tamaño'}</th>
                  <th>{t('backupSettings.duration') || 'Duración'}</th>
                  <th>{t('backupSettings.actions') || 'Acciones'}</th>
                </tr>
              </thead>
              <tbody>
                {backupHistory.map((backup) => (
                  <tr key={backup.id}>
                    <td>{formatDate(backup.date)}</td>
                    <td>
                      <span className={`backup-type ${backup.type}`}>
                        {getTypeLabel(backup.type)}
                      </span>
                    </td>
                    <td>
                      <div className="backup-status">
                        {getStatusIcon(backup.status)}
                        <span>{backup.status === 'success' ? (t('backupSettings.success') || 'Éxito') : (t('backupSettings.error') || 'Error')}</span>
                      </div>
                    </td>
                    <td>{backup.size}</td>
                    <td>{backup.duration}</td>
                    <td>
                      <div className="backup-actions">
                        <button
                          onClick={() => downloadBackup(backup)}
                          className="btn btn-sm btn-secondary"
                          title={t('backupSettings.download') || 'Descargar'}
                        >
                          <Download size={16} />
                        </button>
                        <button
                          onClick={() => restoreBackup(backup)}
                          className="btn btn-sm btn-warning"
                          title={t('backupSettings.restore') || 'Restaurar'}
                        >
                          <Upload size={16} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <div className="empty-state">
              <Database size={48} />
              <h3>{t('backupSettings.noBackups') || 'No hay respaldos'}</h3>
              <p>{t('backupSettings.noBackupsDescription') || 'Aún no se han creado respaldos de la base de datos'}</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default BackupSettings;

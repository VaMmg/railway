import React, { useState } from 'react';
import { useTheme } from '../../contexts/ThemeContext';
import { useLanguage } from '../../contexts/LanguageContext';
import './ColorSettings.css';

const ColorSettings = () => {
  const { 
    currentTheme, 
    themes, 
    customColors, 
    changeTheme, 
    updateCustomColor, 
    resetCustomColors,
    getCurrentColors,
    addCustomTheme,
    deleteCustomTheme,
    defaultThemes
  } = useTheme();
  
  const { t } = useLanguage();
  const [activeTab, setActiveTab] = useState('themes');
  const [newThemeName, setNewThemeName] = useState('');
  const [showNewThemeForm, setShowNewThemeForm] = useState(false);

  const currentColors = getCurrentColors();

  const colorLabels = {
    primary: t('colorSettings.primary') || 'Color Primario',
    secondary: t('colorSettings.secondary') || 'Color Secundario',
    accent: t('colorSettings.accent') || 'Color de Acento',
    background: t('colorSettings.background') || 'Fondo',
    surface: t('colorSettings.surface') || 'Superficie',
    text: t('colorSettings.text') || 'Texto',
    textSecondary: t('colorSettings.textSecondary') || 'Texto Secundario',
    success: t('colorSettings.success') || 'Éxito',
    warning: t('colorSettings.warning') || 'Advertencia',
    error: t('colorSettings.error') || 'Error',
    info: t('colorSettings.info') || 'Información'
  };

  const handleColorChange = (colorKey, value) => {
    updateCustomColor(colorKey, value);
  };

  const handleCreateTheme = () => {
    if (!newThemeName.trim()) return;

    const newTheme = {
      name: newThemeName,
      colors: { ...currentColors }
    };

    addCustomTheme(newThemeName.toLowerCase().replace(/\s+/g, '_'), newTheme);
    setNewThemeName('');
    setShowNewThemeForm(false);
  };

  const handleDeleteTheme = (themeName) => {
    if (window.confirm(t('colorSettings.confirmDelete') || '¿Estás seguro de que quieres eliminar este tema?')) {
      deleteCustomTheme(themeName);
    }
  };

  const exportTheme = () => {
    const themeData = {
      name: themes[currentTheme].name,
      colors: currentColors
    };
    
    const dataStr = JSON.stringify(themeData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    
    const link = document.createElement('a');
    link.href = url;
    link.download = `tema-${currentTheme}.json`;
    link.click();
    
    URL.revokeObjectURL(url);
  };

  const importTheme = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const themeData = JSON.parse(e.target.result);
        if (themeData.name && themeData.colors) {
          const themeName = themeData.name.toLowerCase().replace(/\s+/g, '_');
          addCustomTheme(themeName, themeData);
          changeTheme(themeName);
        }
      } catch (error) {
        alert(t('colorSettings.importError') || 'Error al importar el tema');
      }
    };
    reader.readAsText(file);
  };

  return (
    <div className="color-settings">
      <div className="color-settings-header">
        <h2>{t('colorSettings.title') || 'Configuración de Colores'}</h2>
        <p>{t('colorSettings.description') || 'Personaliza los colores de tu sistema'}</p>
      </div>

      <div className="color-settings-tabs">
        <button 
          className={`tab-button ${activeTab === 'themes' ? 'active' : ''}`}
          onClick={() => setActiveTab('themes')}
        >
          {t('colorSettings.themes') || 'Temas'}
        </button>
        <button 
          className={`tab-button ${activeTab === 'custom' ? 'active' : ''}`}
          onClick={() => setActiveTab('custom')}
        >
          {t('colorSettings.customColors') || 'Colores Personalizados'}
        </button>
        <button 
          className={`tab-button ${activeTab === 'advanced' ? 'active' : ''}`}
          onClick={() => setActiveTab('advanced')}
        >
          {t('colorSettings.advanced') || 'Avanzado'}
        </button>
      </div>

      <div className="color-settings-content">
        {activeTab === 'themes' && (
          <div className="themes-section">
            <div className="themes-grid">
              {Object.entries(themes).map(([key, theme]) => (
                <div 
                  key={key}
                  className={`theme-card ${currentTheme === key ? 'active' : ''}`}
                  onClick={() => changeTheme(key)}
                >
                  <div className="theme-preview">
                    <div className="color-preview-grid">
                      <div 
                        className="color-preview" 
                        style={{ backgroundColor: theme.colors.primary }}
                      ></div>
                      <div 
                        className="color-preview" 
                        style={{ backgroundColor: theme.colors.secondary }}
                      ></div>
                      <div 
                        className="color-preview" 
                        style={{ backgroundColor: theme.colors.accent }}
                      ></div>
                      <div 
                        className="color-preview" 
                        style={{ backgroundColor: theme.colors.background }}
                      ></div>
                    </div>
                  </div>
                  <div className="theme-info">
                    <h3>{theme.name}</h3>
                    {!defaultThemes[key] && (
                      <button 
                        className="delete-theme-btn"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleDeleteTheme(key);
                        }}
                      >
                        ×
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>

            <div className="theme-actions">
              {!showNewThemeForm ? (
                <button 
                  className="btn-primary"
                  onClick={() => setShowNewThemeForm(true)}
                >
                  {t('colorSettings.createTheme') || 'Crear Tema Personalizado'}
                </button>
              ) : (
                <div className="new-theme-form">
                  <input
                    type="text"
                    placeholder={t('colorSettings.themeName') || 'Nombre del tema'}
                    value={newThemeName}
                    onChange={(e) => setNewThemeName(e.target.value)}
                  />
                  <button 
                    className="btn-primary"
                    onClick={handleCreateTheme}
                  >
                    {t('colorSettings.create') || 'Crear'}
                  </button>
                  <button 
                    className="btn-secondary"
                    onClick={() => {
                      setShowNewThemeForm(false);
                      setNewThemeName('');
                    }}
                  >
                    {t('colorSettings.cancel') || 'Cancelar'}
                  </button>
                </div>
              )}
            </div>
          </div>
        )}

        {activeTab === 'preview' && (
          <div className="preview-section">
            <h3>{t('colorSettings.preview') || 'Vista Previa'}</h3>
            <p>{t('colorSettings.previewDescription') || 'Visualiza cómo se ven los colores actuales'}</p>
            
            <div className="preview-container">
              {/* Tarjeta de Login Preview */}
              <div className="preview-login" style={{ 
                background: `linear-gradient(135deg, ${currentColors.primary}, ${currentColors.secondary})`,
                padding: '2rem',
                borderRadius: '1rem',
                marginBottom: '1rem',
                position: 'relative',
                overflow: 'hidden'
              }}>
                <div style={{
                  position: 'absolute',
                  top: 0,
                  left: 0,
                  right: 0,
                  bottom: 0,
                  background: 'url("data:image/svg+xml,<svg width=\\"40\\" height=\\"40\\" viewBox=\\"0 0 40 40\\" xmlns=\\"http://www.w3.org/2000/svg\\"><g fill=\\"rgba(255,255,255,0.05)\\"><circle cx=\\"20\\" cy=\\"20\\" r=\\"2\\"/></g></svg>")',
                  opacity: 0.5
                }}></div>
                <div style={{
                  background: 'rgba(255, 255, 255, 0.95)',
                  backdropFilter: 'blur(10px)',
                  borderRadius: '1rem',
                  padding: '1.5rem',
                  position: 'relative',
                  border: '2px solid rgba(255, 255, 255, 0.3)',
                  boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)'
                }}>
                  <div style={{ textAlign: 'center', marginBottom: '1rem' }}>
                    <div style={{
                      width: '60px',
                      height: '60px',
                      background: `linear-gradient(135deg, ${currentColors.primary}, ${currentColors.secondary})`,
                      borderRadius: '50%',
                      margin: '0 auto 0.5rem',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      color: 'white',
                      fontSize: '1.5rem',
                      boxShadow: `0 4px 12px rgba(102, 126, 234, 0.3)`
                    }}>
                      $
                    </div>
                    <h4 style={{ 
                      color: currentColors.text,
                      background: `linear-gradient(135deg, ${currentColors.primary}, ${currentColors.secondary})`,
                      WebkitBackgroundClip: 'text',
                      WebkitTextFillColor: 'transparent',
                      backgroundClip: 'text',
                      fontSize: '1.25rem',
                      fontWeight: '700'
                    }}>
                      Sistema de Créditos
                    </h4>
                    <p style={{ color: currentColors.textSecondary, fontSize: '0.875rem' }}>
                      Ingresa tus credenciales
                    </p>
                  </div>
                  <div style={{ marginBottom: '1rem' }}>
                    <input 
                      type="text" 
                      placeholder="Usuario"
                      style={{
                        width: '100%',
                        padding: '0.75rem',
                        border: `2px solid ${currentColors.background}`,
                        borderRadius: '0.5rem',
                        marginBottom: '0.5rem',
                        transition: 'all 0.2s ease'
                      }}
                      onFocus={(e) => {
                        e.target.style.borderColor = currentColors.primary;
                        e.target.style.boxShadow = `0 0 0 3px rgba(102, 126, 234, 0.1)`;
                      }}
                      onBlur={(e) => {
                        e.target.style.borderColor = currentColors.background;
                        e.target.style.boxShadow = 'none';
                      }}
                    />
                    <input 
                      type="password" 
                      placeholder="Contraseña"
                      style={{
                        width: '100%',
                        padding: '0.75rem',
                        border: `2px solid ${currentColors.background}`,
                        borderRadius: '0.5rem',
                        transition: 'all 0.2s ease'
                      }}
                      onFocus={(e) => {
                        e.target.style.borderColor = currentColors.primary;
                        e.target.style.boxShadow = `0 0 0 3px rgba(102, 126, 234, 0.1)`;
                      }}
                      onBlur={(e) => {
                        e.target.style.borderColor = currentColors.background;
                        e.target.style.boxShadow = 'none';
                      }}
                    />
                  </div>
                  <button style={{
                    width: '100%',
                    background: `linear-gradient(135deg, ${currentColors.primary}, ${currentColors.secondary})`,
                    color: 'white',
                    border: 'none',
                    padding: '0.75rem',
                    borderRadius: '0.5rem',
                    fontWeight: '600',
                    cursor: 'pointer',
                    transition: 'all 0.2s ease',
                    position: 'relative',
                    overflow: 'hidden'
                  }}
                  onMouseEnter={(e) => {
                    e.target.style.transform = 'translateY(-2px)';
                    e.target.style.boxShadow = `0 8px 25px rgba(102, 126, 234, 0.3)`;
                  }}
                  onMouseLeave={(e) => {
                    e.target.style.transform = 'translateY(0)';
                    e.target.style.boxShadow = 'none';
                  }}>
                    Iniciar Sesión
                  </button>
                </div>
              </div>

              {/* Sidebar Preview */}
              <div className="preview-sidebar" style={{
                background: `linear-gradient(135deg, ${currentColors.primary}, ${currentColors.secondary})`,
                borderRadius: '1rem',
                padding: '1rem',
                color: 'white',
                marginBottom: '1rem',
                boxShadow: `0 4px 12px rgba(102, 126, 234, 0.3)`
              }}>
                <div style={{
                  padding: '1rem',
                  borderBottom: '2px solid rgba(255, 255, 255, 0.2)',
                  marginBottom: '1rem',
                  background: 'linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05))',
                  borderRadius: '0.5rem',
                  backdropFilter: 'blur(10px)'
                }}>
                  <h4 style={{ 
                    fontWeight: '700',
                    background: 'linear-gradient(45deg, #ffffff, rgba(255,255,255,0.8))',
                    WebkitBackgroundClip: 'text',
                    WebkitTextFillColor: 'transparent',
                    backgroundClip: 'text'
                  }}>
                    Sistema Crédito
                  </h4>
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                  {['Dashboard', 'Clientes', 'Créditos', 'Pagos', 'Reportes'].map((item, index) => (
                    <div 
                      key={item}
                      style={{
                        padding: '0.75rem 1rem',
                        borderRadius: '0 25px 25px 0',
                        background: index === 0 ? 'linear-gradient(135deg, rgba(255, 255, 255, 0.25), rgba(255, 255, 255, 0.15))' : 'transparent',
                        transform: index === 0 ? 'translateX(8px)' : 'translateX(0)',
                        boxShadow: index === 0 ? '0 6px 20px rgba(0, 0, 0, 0.3)' : 'none',
                        backdropFilter: index === 0 ? 'blur(15px)' : 'none',
                        transition: 'all 0.3s ease',
                        cursor: 'pointer',
                        position: 'relative'
                      }}
                      onMouseEnter={(e) => {
                        if (index !== 0) {
                          e.target.style.background = 'linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1))';
                          e.target.style.transform = 'translateX(5px)';
                          e.target.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.2)';
                        }
                      }}
                      onMouseLeave={(e) => {
                        if (index !== 0) {
                          e.target.style.background = 'transparent';
                          e.target.style.transform = 'translateX(0)';
                          e.target.style.boxShadow = 'none';
                        }
                      }}
                    >
                      {index === 0 && (
                        <div style={{
                          position: 'absolute',
                          left: 0,
                          top: 0,
                          bottom: 0,
                          width: '4px',
                          background: `linear-gradient(135deg, ${currentColors.accent}, ${currentColors.primary})`,
                          borderRadius: '0 2px 2px 0',
                          boxShadow: '0 0 10px rgba(255, 255, 255, 0.5)'
                        }}></div>
                      )}
                      {item}
                    </div>
                  ))}
                </div>
              </div>

              {/* Dashboard Cards Preview */}
              <div className="preview-cards" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
                {[
                  { title: 'Clientes', value: '1,234', icon: 'C', gradient: `linear-gradient(135deg, ${currentColors.primary}, ${currentColors.secondary})` },
                  { title: 'Créditos', value: '$45,678', icon: '$', gradient: `linear-gradient(135deg, ${currentColors.accent}, ${currentColors.primary})` },
                  { title: 'Pagos Hoy', value: '$12,345', icon: 'P', gradient: `linear-gradient(135deg, ${currentColors.info}, ${currentColors.accent})` },
                  { title: 'Vencidos', value: '23', icon: '!', gradient: `linear-gradient(135deg, ${currentColors.warning}, ${currentColors.error})` }
                ].map((card, index) => (
                  <div 
                    key={card.title}
                    style={{
                      background: currentColors.surface,
                      borderRadius: '0.75rem',
                      padding: '1rem',
                      boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                      border: '1px solid rgba(0, 0, 0, 0.05)',
                      transition: 'all 0.3s ease',
                      cursor: 'pointer'
                    }}
                    onMouseEnter={(e) => {
                      e.target.style.transform = 'translateY(-2px)';
                      e.target.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.1)';
                      e.target.style.borderColor = currentColors.primary;
                    }}
                    onMouseLeave={(e) => {
                      e.target.style.transform = 'translateY(0)';
                      e.target.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
                      e.target.style.borderColor = 'rgba(0, 0, 0, 0.05)';
                    }}
                  >
                    <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                      <div style={{
                        width: '50px',
                        height: '50px',
                        borderRadius: '0.75rem',
                        background: card.gradient,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: 'white',
                        fontSize: '1.25rem',
                        boxShadow: `0 4px 12px rgba(102, 126, 234, 0.3)`
                      }}>
                        {card.icon}
                      </div>
                      <div>
                        <h4 style={{ 
                          fontSize: '0.75rem',
                          fontWeight: '600',
                          color: currentColors.textSecondary,
                          textTransform: 'uppercase',
                          letterSpacing: '0.05em',
                          marginBottom: '0.25rem'
                        }}>
                          {card.title}
                        </h4>
                        <p style={{
                          fontSize: '1.25rem',
                          fontWeight: '700',
                          color: currentColors.text,
                          margin: 0
                        }}>
                          {card.value}
                        </p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {activeTab === 'custom' && (
          <div className="custom-colors-section">
            <div className="colors-grid">
              {Object.entries(colorLabels).map(([key, label]) => (
                <div key={key} className="color-input-group">
                  <label>{label}</label>
                  <div className="color-input-wrapper">
                    <input
                      type="color"
                      value={currentColors[key] || '#000000'}
                      onChange={(e) => handleColorChange(key, e.target.value)}
                      className="color-input"
                    />
                    <input
                      type="text"
                      value={currentColors[key] || '#000000'}
                      onChange={(e) => handleColorChange(key, e.target.value)}
                      className="color-text-input"
                      placeholder="#000000"
                    />
                  </div>
                </div>
              ))}
            </div>

            <div className="custom-colors-actions">
              <button 
                className="btn-secondary"
                onClick={resetCustomColors}
              >
                {t('colorSettings.resetColors') || 'Restablecer Colores'}
              </button>
            </div>
          </div>
        )}

        {activeTab === 'advanced' && (
          <div className="advanced-section">
            <div className="advanced-actions">
              <div className="action-group">
                <h3>{t('colorSettings.exportImport') || 'Exportar/Importar'}</h3>
                <p>{t('colorSettings.exportDescription') || 'Guarda o carga configuraciones de temas'}</p>
                <div className="action-buttons">
                  <button 
                    className="btn-primary"
                    onClick={exportTheme}
                  >
                    {t('colorSettings.exportTheme') || 'Exportar Tema Actual'}
                  </button>
                  <label className="btn-secondary file-input-label">
                    {t('colorSettings.importTheme') || 'Importar Tema'}
                    <input
                      type="file"
                      accept=".json"
                      onChange={importTheme}
                      style={{ display: 'none' }}
                    />
                  </label>
                </div>
              </div>

              <div className="action-group">
                <h3>{t('colorSettings.preview') || 'Vista Previa'}</h3>
                <p>{t('colorSettings.previewDescription') || 'Visualiza cómo se ven los colores actuales'}</p>
                <div className="color-preview-large">
                  {Object.entries(currentColors).map(([key, color]) => (
                    <div key={key} className="color-swatch">
                      <div 
                        className="swatch-color" 
                        style={{ backgroundColor: color }}
                      ></div>
                      <div className="swatch-info">
                        <span className="swatch-name">{colorLabels[key]}</span>
                        <span className="swatch-value">{color}</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default ColorSettings;

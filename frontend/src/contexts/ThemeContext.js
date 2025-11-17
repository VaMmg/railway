import React, { createContext, useContext, useState, useEffect } from 'react';

const ThemeContext = createContext();

// Temas predefinidos
const defaultThemes = {
  default: {
    name: 'Tema Por Defecto',
    colors: {
      primary: '#667eea',
      secondary: '#764ba2',
      accent: '#4f46e5',
      background: '#f8fafc',
      surface: '#ffffff',
      text: '#374151',
      textSecondary: '#6b7280',
      success: '#10b981',
      warning: '#f59e0b',
      error: '#ef4444',
      info: '#3b82f6'
    }
  },
  dark: {
    name: 'Tema Oscuro',
    colors: {
      primary: '#6366f1',
      secondary: '#8b5cf6',
      accent: '#a855f7',
      background: '#111827',
      surface: '#1f2937',
      text: '#f9fafb',
      textSecondary: '#d1d5db',
      success: '#10b981',
      warning: '#f59e0b',
      error: '#ef4444',
      info: '#3b82f6'
    }
  },
  ocean: {
    name: 'Océano',
    colors: {
      primary: '#0ea5e9',
      secondary: '#0284c7',
      accent: '#06b6d4',
      background: '#f0f9ff',
      surface: '#ffffff',
      text: '#0f172a',
      textSecondary: '#475569',
      success: '#059669',
      warning: '#d97706',
      error: '#dc2626',
      info: '#0284c7'
    }
  },
  forest: {
    name: 'Bosque',
    colors: {
      primary: '#059669',
      secondary: '#047857',
      accent: '#10b981',
      background: '#f0fdf4',
      surface: '#ffffff',
      text: '#064e3b',
      textSecondary: '#374151',
      success: '#10b981',
      warning: '#d97706',
      error: '#dc2626',
      info: '#0284c7'
    }
  },
  sunset: {
    name: 'Atardecer',
    colors: {
      primary: '#f97316',
      secondary: '#ea580c',
      accent: '#fb923c',
      background: '#fff7ed',
      surface: '#ffffff',
      text: '#9a3412',
      textSecondary: '#c2410c',
      success: '#10b981',
      warning: '#f59e0b',
      error: '#ef4444',
      info: '#3b82f6'
    }
  }
};

export const ThemeProvider = ({ children }) => {
  const [currentTheme, setCurrentTheme] = useState('default');
  const [customColors, setCustomColors] = useState({});
  const [themes, setThemes] = useState(defaultThemes);

  // Cargar tema guardado al inicializar
  useEffect(() => {
    const savedTheme = localStorage.getItem('sistemaCredito_theme');
    const savedCustomColors = localStorage.getItem('sistemaCredito_customColors');
    
    if (savedTheme) {
      setCurrentTheme(savedTheme);
    }
    
    if (savedCustomColors) {
      try {
        const parsedColors = JSON.parse(savedCustomColors);
        setCustomColors(parsedColors);
      } catch (error) {
        console.error('Error parsing saved custom colors:', error);
      }
    }
  }, []);

  // Aplicar variables CSS cuando cambie el tema
  useEffect(() => {
    const theme = themes[currentTheme];
    if (theme) {
      const colors = { ...theme.colors, ...customColors };
      
      // Aplicar variables CSS al documento
      Object.entries(colors).forEach(([key, value]) => {
        document.documentElement.style.setProperty(`--color-${key}`, value);
      });

      // Generar gradientes dinámicos
      document.documentElement.style.setProperty(
        '--gradient-primary', 
        `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})`
      );
      document.documentElement.style.setProperty(
        '--gradient-accent', 
        `linear-gradient(135deg, ${colors.accent}, ${colors.primary})`
      );
      document.documentElement.style.setProperty(
        '--gradient-background', 
        `linear-gradient(135deg, ${colors.background}, ${colors.surface})`
      );

      // Generar sombras dinámicas basadas en los colores
      const hexToRgb = (hex) => {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
          r: parseInt(result[1], 16),
          g: parseInt(result[2], 16),
          b: parseInt(result[3], 16)
        } : null;
      };

      const primaryRgb = hexToRgb(colors.primary);
      const secondaryRgb = hexToRgb(colors.secondary);
      const accentRgb = hexToRgb(colors.accent);

      if (primaryRgb) {
        document.documentElement.style.setProperty(
          '--shadow-primary', 
          `0 4px 12px rgba(${primaryRgb.r}, ${primaryRgb.g}, ${primaryRgb.b}, 0.3)`
        );
      }
      if (secondaryRgb) {
        document.documentElement.style.setProperty(
          '--shadow-secondary', 
          `0 8px 25px rgba(${secondaryRgb.r}, ${secondaryRgb.g}, ${secondaryRgb.b}, 0.2)`
        );
      }
      if (accentRgb) {
        document.documentElement.style.setProperty(
          '--shadow-accent', 
          `0 4px 12px rgba(${accentRgb.r}, ${accentRgb.g}, ${accentRgb.b}, 0.3)`
        );
      }

      // Generar bordes dinámicos
      document.documentElement.style.setProperty('--border-primary', `2px solid ${colors.primary}`);
      document.documentElement.style.setProperty('--border-accent', `2px solid ${colors.accent}`);
    }
  }, [currentTheme, customColors, themes]);

  const changeTheme = (themeName) => {
    setCurrentTheme(themeName);
    localStorage.setItem('sistemaCredito_theme', themeName);
  };

  const updateCustomColor = (colorKey, colorValue) => {
    const newCustomColors = {
      ...customColors,
      [colorKey]: colorValue
    };
    
    setCustomColors(newCustomColors);
    localStorage.setItem('sistemaCredito_customColors', JSON.stringify(newCustomColors));
  };

  const resetCustomColors = () => {
    setCustomColors({});
    localStorage.removeItem('sistemaCredito_customColors');
  };

  const getCurrentColors = () => {
    const theme = themes[currentTheme];
    return theme ? { ...theme.colors, ...customColors } : {};
  };

  const addCustomTheme = (themeName, themeData) => {
    const newThemes = {
      ...themes,
      [themeName]: themeData
    };
    setThemes(newThemes);
    
    // Guardar temas personalizados
    const customThemes = Object.keys(newThemes)
      .filter(key => !defaultThemes[key])
      .reduce((obj, key) => {
        obj[key] = newThemes[key];
        return obj;
      }, {});
    
    localStorage.setItem('sistemaCredito_customThemes', JSON.stringify(customThemes));
  };

  const deleteCustomTheme = (themeName) => {
    if (defaultThemes[themeName]) {
      console.warn('Cannot delete default theme');
      return;
    }

    const newThemes = { ...themes };
    delete newThemes[themeName];
    setThemes(newThemes);

    // Si el tema eliminado era el actual, cambiar al por defecto
    if (currentTheme === themeName) {
      changeTheme('default');
    }

    // Actualizar temas personalizados guardados
    const customThemes = Object.keys(newThemes)
      .filter(key => !defaultThemes[key])
      .reduce((obj, key) => {
        obj[key] = newThemes[key];
        return obj;
      }, {});
    
    localStorage.setItem('sistemaCredito_customThemes', JSON.stringify(customThemes));
  };

  // Cargar temas personalizados guardados
  useEffect(() => {
    const savedCustomThemes = localStorage.getItem('sistemaCredito_customThemes');
    if (savedCustomThemes) {
      try {
        const parsedThemes = JSON.parse(savedCustomThemes);
        setThemes(prevThemes => ({
          ...prevThemes,
          ...parsedThemes
        }));
      } catch (error) {
        console.error('Error parsing saved custom themes:', error);
      }
    }
  }, []);

  const value = {
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
  };

  return (
    <ThemeContext.Provider value={value}>
      {children}
    </ThemeContext.Provider>
  );
};

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
};

export default ThemeContext;

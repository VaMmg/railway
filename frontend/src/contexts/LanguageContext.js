import React, { createContext, useState, useContext, useEffect } from 'react';

// Importar archivos de localización
import es from '../locales/es.json';
import en from '../locales/en.json';

const translations = {
  es,
  en
};

const LanguageContext = createContext();

export const useLanguage = () => {
  const context = useContext(LanguageContext);
  if (!context) {
    throw new Error('useLanguage debe ser usado dentro de un LanguageProvider');
  }
  return context;
};

export const LanguageProvider = ({ children }) => {
  // Obtener idioma del localStorage o usar 'es' por defecto
  const [language, setLanguage] = useState(() => {
    const savedLanguage = localStorage.getItem('app_language');
    return savedLanguage && translations[savedLanguage] ? savedLanguage : 'es';
  });

  // Guardar idioma en localStorage cuando cambie
  useEffect(() => {
    localStorage.setItem('app_language', language);
  }, [language]);

  // Función de traducción con soporte para interpolación
  const t = (key, params = {}) => {
    const keys = key.split('.');
    let translation = translations[language];
    
    // Navegar por las claves anidadas
    for (const k of keys) {
      translation = translation?.[k];
    }
    
    // Si no se encuentra la traducción, usar la clave como fallback
    if (typeof translation !== 'string') {
      console.warn(`Translation not found for key: ${key} in language: ${language}`);
      return key;
    }
    
    // Interpolación de parámetros usando {param}
    return translation.replace(/\{(\w+)\}/g, (match, param) => {
      return params[param] !== undefined ? params[param] : match;
    });
  };

  // Función para cambiar idioma
  const changeLanguage = (newLanguage) => {
    if (translations[newLanguage]) {
      setLanguage(newLanguage);
    } else {
      console.warn(`Language not supported: ${newLanguage}`);
    }
  };

  // Obtener idiomas disponibles
  const getAvailableLanguages = () => {
    return Object.keys(translations);
  };

  // Obtener nombre del idioma actual
  const getLanguageName = (lang = language) => {
    const names = {
      es: 'Español',
      en: 'English'
    };
    return names[lang] || lang.toUpperCase();
  };

  const value = {
    language,
    t,
    changeLanguage,
    getAvailableLanguages,
    getLanguageName
  };

  return (
    <LanguageContext.Provider value={value}>
      {children}
    </LanguageContext.Provider>
  );
};
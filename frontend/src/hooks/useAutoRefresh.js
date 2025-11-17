import { useEffect, useRef } from 'react';

/**
 * Hook personalizado para auto-refresh de datos
 * @param {Function} callback - Función a ejecutar periódicamente
 * @param {number} interval - Intervalo en milisegundos (default: 30000 = 30 segundos)
 * @param {boolean} enabled - Si el auto-refresh está habilitado (default: true)
 */
export const useAutoRefresh = (callback, interval = 30000, enabled = true) => {
  const savedCallback = useRef();
  const intervalId = useRef();

  // Guardar el callback más reciente
  useEffect(() => {
    savedCallback.current = callback;
  }, [callback]);

  // Configurar el intervalo
  useEffect(() => {
    if (!enabled) {
      if (intervalId.current) {
        clearInterval(intervalId.current);
      }
      return;
    }

    const tick = () => {
      if (savedCallback.current) {
        savedCallback.current();
      }
    };

    // Ejecutar inmediatamente al montar
    // tick();

    // Configurar intervalo
    intervalId.current = setInterval(tick, interval);

    // Limpiar al desmontar
    return () => {
      if (intervalId.current) {
        clearInterval(intervalId.current);
      }
    };
  }, [interval, enabled]);

  // Función para forzar refresh manual
  const forceRefresh = () => {
    if (savedCallback.current) {
      savedCallback.current();
    }
  };

  return { forceRefresh };
};

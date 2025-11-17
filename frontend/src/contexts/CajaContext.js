import React, { createContext, useContext, useState, useEffect } from 'react';
import { cajasService } from '../services/api';
import { useAuth } from './AuthContext';

const CajaContext = createContext();

export const useCaja = () => {
  const context = useContext(CajaContext);
  if (!context) {
    throw new Error('useCaja debe ser usado dentro de un CajaProvider');
  }
  return context;
};

export const CajaProvider = ({ children }) => {
  const { user } = useAuth();
  const [cajaActiva, setCajaActiva] = useState(false);
  const [miCaja, setMiCaja] = useState(null);
  const [loading, setLoading] = useState(true);
  const [cajaPrincipalAbierta, setCajaPrincipalAbierta] = useState(false);

  const isTrabajador = user?.nombre_rol?.toLowerCase().includes('trabajador');
  const isGerente = user?.nombre_rol?.toLowerCase().includes('gerente');
  const isAdmin = user?.nombre_rol?.toLowerCase().includes('administrador');

  // Verificar estado de la caja
  const verificarCaja = async () => {
    console.log('[CHECK] Iniciando verificación de caja...');
    console.log('Usuario:', user);
    console.log('Es trabajador:', isTrabajador);
    
    // Solo verificar para trabajadores
    if (!isTrabajador) {
      console.log('[OK] No es trabajador, acceso permitido');
      setLoading(false);
      setCajaActiva(true); // Gerentes y admin siempre tienen acceso
      return;
    }

    try {
      setLoading(true);
      console.log('[API] Consultando estado de caja al backend...');
      
      const response = await cajasService.getMiCaja();
      
      console.log('[RESPONSE] Respuesta del backend:', response);
      
      const caja = response?.data?.caja;
      
      console.log('[CHECK] Verificación de caja:', {
        caja,
        estado: caja?.estado_caja,
        existe: !!caja
      });
      
      setMiCaja(caja);
      
      // La caja está activa si existe y está en estado 'Abierta' o 'Asignada'
      const activa = caja && (caja.estado_caja === 'Abierta' || caja.estado_caja === 'Asignada');
      setCajaActiva(activa);
      
      console.log(activa ? '[OK] Caja activa' : '[X] Caja NO activa');
      
    } catch (error) {
      console.error('[ERROR] Error al verificar caja:', error);
      // Si hay error, asumir que no hay caja activa
      setCajaActiva(false);
      setMiCaja(null);
    } finally {
      setLoading(false);
    }
  };

  // Verificar estado de caja principal (para gerentes)
  const verificarCajaPrincipal = async () => {
    if (!isGerente && !isAdmin) return;
    
    try {
      const response = await cajasService.getCajaPrincipal();
      setCajaPrincipalAbierta(response?.data?.esta_abierta || false);
    } catch (error) {
      console.error('Error al verificar caja principal:', error);
      setCajaPrincipalAbierta(false);
    }
  };

  // Verificar al cargar y cuando cambia el usuario
  useEffect(() => {
    console.log('[EFFECT] useEffect CajaContext ejecutado');
    console.log('Usuario actual:', user);
    
    // El usuario puede tener 'id' o 'id_usuario' dependiendo de cómo se cargó
    const userId = user?.id_usuario || user?.id;
    
    if (user && userId) {
      console.log('[OK] Usuario existe (ID:', userId, '), verificando caja...');
      verificarCaja();
      if (isGerente || isAdmin) {
        verificarCajaPrincipal();
      }
    } else {
      console.log('⏳ Esperando usuario o usuario no disponible');
      // Si no hay usuario, marcar como no activa pero no mostrar loading
      setLoading(false);
      setCajaActiva(!isTrabajador); // Si no es trabajador, permitir acceso
    }
  }, [user?.id, user?.id_usuario]);

  // Recargar estado de la caja
  const recargarCaja = async () => {
    await verificarCaja();
    await verificarCajaPrincipal();
  };

  const value = {
    cajaActiva,
    miCaja,
    loading,
    cajaPrincipalAbierta,
    isTrabajador,
    isGerente,
    isAdmin,
    recargarCaja,
    verificarCaja
  };

  return (
    <CajaContext.Provider value={value}>
      {children}
    </CajaContext.Provider>
  );
};

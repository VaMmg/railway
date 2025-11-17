import axios from 'axios';

// Configuración base de la API
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost/sistemaCredito/backend/api/';
console.log('[CONFIG] API_BASE_URL configurada:', API_BASE_URL);

// Crear instancia de axios
const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000, // Aumentar timeout a 30 segundos
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  withCredentials: false // Asegurar que no haya problemas con credenciales
});

// Interceptor para añadir el token a las peticiones
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Interceptor para manejar respuestas y errores
apiClient.interceptors.response.use(
  (response) => {
    console.log('[RESPONSE] Response received:', response.data);
    
    // Verificar si hay un nuevo token en los headers (renovación automática)
    const newToken = response.headers['x-new-token'];
    if (newToken) {
      console.log('[TOKEN] Token renovado automáticamente');
      localStorage.setItem('auth_token', newToken);
    }
    
    // Si la respuesta es un string (HTML + JSON), intentar extraer el JSON
    if (typeof response.data === 'string') {
      try {
        // Buscar el JSON en la respuesta (después de los warnings de PHP)
        const jsonMatch = response.data.match(/\{.*\}/s);
        if (jsonMatch) {
          const jsonData = JSON.parse(jsonMatch[0]);
          console.log('[OK] JSON extraído de respuesta HTML:', jsonData);
          return jsonData;
        }
      } catch (e) {
        console.warn('[WARN] No se pudo parsear JSON de la respuesta:', e);
      }
    }
    
    return response.data;
  },
  (error) => {
    console.error('[ERROR] Axios error:', error);
    console.error('[ERROR] Error response:', error.response);
    console.error('[ERROR] Error message:', error.message);
    
    if (error.response?.status === 401) {
      // Token inválido o expirado
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user_data');
      window.location.href = '/login';
    }
    
    // Retornar estructura consistente para errores
    const errorMessage = error.response?.data?.message || error.message || 'Error desconocido';
    return Promise.reject({
      success: false,
      message: errorMessage,
      status: error.response?.status || 500
    });
  }
);

// Servicios de autenticación
export const authService = {
  // Método de prueba para verificar conectividad
  testConnection: async () => {
    console.log('🧪 Testing connection to:', API_BASE_URL + 'test.php');
    try {
      const response = await apiClient.get('test.php');
      console.log('[OK] Connection test successful:', response);
      return response;
    } catch (error) {
      console.error('[ERROR] Connection test failed:', error);
      throw error;
    }
  },

  login: async (credentials) => {
    console.log('[LOGIN] Enviando login request:', credentials);
    console.log('[API] API Base URL:', API_BASE_URL);
    
    // Primero probar la conectividad
    try {
      await authService.testConnection();
    } catch (error) {
      console.error('[ERROR] Connection test failed before login');
      throw new Error('No se puede conectar al servidor');
    }
    
    try {
      const response = await apiClient.post('login.php', credentials);
      console.log('[OK] Login response:', response);
      if (response.success && response.data.token) {
        // Asegurar que id_rol sea un número
        if (response.data.user.id_rol) {
          response.data.user.id_rol = parseInt(response.data.user.id_rol, 10);
        }
        localStorage.setItem('auth_token', response.data.token);
        localStorage.setItem('user_data', JSON.stringify(response.data.user));
      }
      return response;
    } catch (error) {
      console.error('[ERROR] Login error:', error);
      throw error;
    }
  },
  
  logout: () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_data');
    window.location.href = '/login';
  },
  
  verifyToken: async () => {
    try {
      const response = await apiClient.get('/login.php');
      return response;
    } catch (error) {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user_data');
      throw error;
    }
  },
  
  getCurrentUser: () => {
    const userData = localStorage.getItem('user_data');
    if (userData) {
      const user = JSON.parse(userData);
      // Asegurar que id_rol sea un número
      if (user.id_rol) {
        user.id_rol = parseInt(user.id_rol, 10);
      }
      return user;
    }
    return null;
  },
  
  isAuthenticated: () => {
    return !!localStorage.getItem('auth_token');
  }
};

// Servicios del dashboard
export const dashboardService = {
  getStats: async () => {
    return await apiClient.get('/dashboard.php');
  }
};

// Servicios de clientes
export const clientsService = {
  getAll: async (params = {}) => {
    const queryString = new URLSearchParams(params).toString();
    return await apiClient.get(`/clientes.php?${queryString}`);
  },
  
  getById: async (id) => {
    return await apiClient.get(`/clientes.php?id=${id}`);
  },
  
  create: async (clientData) => {
    return await apiClient.post('/clientes.php', clientData);
  },
  
  update: async (id, clientData) => {
    return await apiClient.put(`/clientes.php?id=${id}`, clientData);
  },
  
  delete: async (id) => {
    return await apiClient.delete(`/clientes.php?id=${id}`);
  }
};

// Servicios de créditos
export const creditsService = {
  getAll: async (params = {}) => {
    const queryString = new URLSearchParams(params).toString();
    return await apiClient.get(`/creditos.php?${queryString}`);
  },
  
  getById: async (id) => {
    return await apiClient.get(`/creditos.php?id=${id}`);
  },
  
  create: async (creditData) => {
    return await apiClient.post('/creditos.php', creditData);
  },
  
  update: async (id, creditData) => {
    return await apiClient.put(`/creditos.php?id=${id}`, { action: 'actualizar', ...creditData });
  },
  
  aprobar: async (id) => {
    return await apiClient.put(`/creditos.php?id=${id}`, { action: 'aprobar' });
  },
  
  rechazar: async (id, motivo) => {
    return await apiClient.put(`/creditos.php?id=${id}`, { action: 'rechazar', motivo_rechazo: motivo });
  },
  
  reprogramar: async (id, data) => {
    // data puede incluir: nueva_tasa_interes, nuevo_plazos_meses, motivo, nuevo_cronograma
    return await apiClient.put(`/creditos.php?id=${id}`, { action: 'reprogramar', ...data });
  },
  
  getContrato: async (id) => {
    return await apiClient.get(`/creditos.php?action=contrato&id=${id}`);
  },
  
  getSolicitud: async (id) => {
    return await apiClient.get(`/creditos.php?action=solicitud&id=${id}`);
  },
  
  delete: async (id) => {
    return await apiClient.delete(`/creditos.php?id=${id}`);
  },
  
  // Obtener límites de aprobación por rol
  getLimitesAprobacion: () => {
    return {
      1: 100000, // Administrador
      2: 50000,  // Gerente
      3: 10000   // Trabajador
    };
  }
};

// Servicios de pagos
export const paymentsService = {
  getAll: async (params = {}) => {
    const queryString = new URLSearchParams(params).toString();
    return await apiClient.get(`/pagos.php?${queryString}`);
  },
  
  getById: async (id) => {
    return await apiClient.get(`/pagos.php?id=${id}`);
  },
  
  create: async (paymentData) => {
    return await apiClient.post('/pagos.php', paymentData);
  },
  
  getByCreditId: async (creditId) => {
    return await apiClient.get(`/pagos.php?credito_id=${creditId}`);
  },
  
  delete: async (id) => {
    console.log('[DELETE] paymentsService.delete llamado con ID:', id);
    console.log('[DELETE] URL completa:', `${API_BASE_URL}pagos.php?id=${id}`);
    
    try {
      const response = await apiClient.delete(`pagos.php?id=${id}`);
      console.log('[DELETE] Respuesta recibida:', response);
      return response;
    } catch (error) {
      console.error('[ERROR] Error en delete:', error);
      throw error;
    }
  }
};

// Servicios de cajas
export const cajasService = {
  getAll: async (params = {}) => {
    const queryString = new URLSearchParams(params).toString();
    return await apiClient.get(`/cajas.php?${queryString}`);
  },
  
  getEstadoCajaPrincipal: async () => {
    return await apiClient.get('/cajas.php?action=estado_caja_principal');
  },
  
  getMiCaja: async () => {
    return await apiClient.get('/cajas.php?action=mi_caja');
  },
  
  getCajasTrabajadores: async () => {
    return await apiClient.get('/cajas.php?action=cajas_trabajadores');
  },
  
  puedeAbrirCaja: async () => {
    return await apiClient.get('/cajas.php?action=puede_abrir_caja');
  },
  
  abrirCaja: async (data) => {
    return await apiClient.post('/cajas.php', { action: 'abrir', ...data });
  },
  
  cerrarCaja: async (id, data) => {
    return await apiClient.post('/cajas.php', { action: 'cerrar', id_caja: id, ...data });
  },
  
  actualizarEstado: async (id, nuevoEstado) => {
    return await apiClient.put(`/cajas.php?id=${id}`, { action: 'actualizar_estado', estado_caja: nuevoEstado });
  },
  
  habilitarCaja: async (idCaja, habilitar = true) => {
    return await apiClient.post('/cajas.php', { action: 'habilitar', id_caja: idCaja, habilitar });
  },
  
  // Caja Principal (Gerente)
  getCajaPrincipal: async () => {
    return await apiClient.get('/cajas.php?action=caja_principal');
  },
  
  abrirCajaPrincipal: async (saldoInicial) => {
    return await apiClient.post('/cajas.php', { action: 'abrir_caja_principal', saldo_inicial: saldoInicial });
  },
  
  cerrarCajaPrincipal: async () => {
    return await apiClient.post('/cajas.php', { action: 'cerrar_caja_principal' });
  },
  
  actualizarSaldo: async (id, nuevoSaldo) => {
    return await apiClient.put(`/cajas.php?id=${id}`, { action: 'actualizar_saldo', nuevo_saldo: nuevoSaldo });
  },
  
  eliminarCaja: async (id) => {
    return await apiClient.delete(`/cajas.php?id=${id}`);
  }
};

// Servicios de notificaciones
export const notificationsService = {
  getAll: async (params = {}) => {
    const queryString = new URLSearchParams(params).toString();
    return await apiClient.get(`/notificaciones.php?${queryString}`);
  },
  
  getNoLeidas: async () => {
    return await apiClient.get('/notificaciones.php?action=no_leidas');
  },
  
  getParaGerente: async () => {
    return await apiClient.get('/notificaciones.php?action=para_gerente');
  },
  
  create: async (notificationData) => {
    return await apiClient.post('/notificaciones.php', notificationData);
  },
  
  marcarLeida: async (id) => {
    return await apiClient.put(`/notificaciones.php?id=${id}`, { action: 'marcar_leida' });
  },
  
  markAsRead: async (id) => {
    return await apiClient.put(`/notificaciones.php?id=${id}`, { action: 'marcar_leida' });
  },
  
  marcarTodasLeidas: async () => {
    return await apiClient.put('/notificaciones.php?id=0', { action: 'marcar_todas_leidas' });
  }
};

// Servicios de usuarios
export const usersService = {
  getAll: async (params = {}) => {
    const queryString = new URLSearchParams(params).toString();
    return await apiClient.get(`/usuarios.php?${queryString}`);
  },
  
  getById: async (id) => {
    return await apiClient.get(`/usuarios.php?id=${id}`);
  },
  
  create: async (userData) => {
    return await apiClient.post('/usuarios.php', userData);
  },
  
  update: async (id, userData) => {
    return await apiClient.put(`/usuarios.php?id=${id}`, userData);
  },
  
  delete: async (id) => {
    return await apiClient.delete(`/usuarios.php?id=${id}`);
  },
  
  resetPassword: async (id) => {
    return await apiClient.put(`/usuarios.php?id=${id}`, { action: 'reset_password' });
  }
};

// Servicio de reprogramaciones
export const reprogramacionesService = {
  getAll: async (params = {}) => {
    const queryString = new URLSearchParams(params).toString();
    return await apiClient.get(`/reprogramaciones.php?${queryString}`);
  },
  
  getById: async (id) => {
    return await apiClient.get(`/reprogramaciones.php?id=${id}`);
  },
  
  getByCredito: async (credito_id, params = {}) => {
    const queryParams = new URLSearchParams({ ...params, credito_id }).toString();
    return await apiClient.get(`/reprogramaciones.php?${queryParams}`);
  },
  
  create: async (data) => {
    return await apiClient.post('/reprogramaciones.php', data);
  },
  
  aprobar: async (id) => {
    return await apiClient.put(`/reprogramaciones.php?id=${id}`, { action: 'aprobar' });
  },
  
  rechazar: async (id, motivo_rechazo) => {
    return await apiClient.put(`/reprogramaciones.php?id=${id}`, { 
      action: 'rechazar',
      motivo_rechazo 
    });
  }
};

// Servicio de catálogos
export const catalogService = {
  getSeguros: async ({ page = 1, limit = 100 } = {}) => {
    const params = new URLSearchParams();
    params.append('page', page);
    params.append('limit', limit);
    return await apiClient.get(`/seguros.php?${params.toString()}`);
  }
};

// Servicio de logs de acceso (solo Gerente/Admin)
export const logsService = {
  getAccessLogs: async ({ limit = 100, user_id = null } = {}) => {
    const params = new URLSearchParams();
    params.append('limit', limit);
    if (user_id) params.append('user_id', user_id);
    return await apiClient.get(`/log_accesos.php?${params.toString()}`);
  }
};

// Servicio de personas
export const peopleService = {
  search: async (q, { page = 1, limit = 10 } = {}) => {
    const params = new URLSearchParams();
    params.append('q', q);
    params.append('page', page);
    params.append('limit', limit);
    return await apiClient.get(`/personas.php?${params.toString()}`);
  },
  
  getAll: async ({ page = 1, limit = 200 } = {}) => {
    const params = new URLSearchParams();
    params.append('page', page);
    params.append('limit', limit);
    return await apiClient.get(`/personas.php?${params.toString()}`);
  },
  
  getById: async (id) => {
    return await apiClient.get(`/personas.php?id=${id}`);
  },
  
  create: async (data) => {
    return await apiClient.post('/personas.php', data);
  },
  
  update: async (id, data) => {
    return await apiClient.put(`/personas.php?id=${id}`, data);
  },
  
  delete: async (id) => {
    return await apiClient.delete(`/personas.php?id=${id}`);
  }
};

// Servicio de RENIEC
export const reniecService = {
  consultarDNI: async (dni) => {
    return await apiClient.get(`/reniec.php?dni=${dni}`);
  }
};

// Servicios de calendario
export const calendarService = {
  // Obtener eventos del mes
  getEventosDelMes: async (año, mes) => {
    const params = new URLSearchParams();
    params.append('action', 'eventos_mes');
    params.append('año', año);
    params.append('mes', mes);
    return await apiClient.get(`/calendario.php?${params.toString()}`);
  },
  
  // Obtener eventos de un día específico
  getEventosDelDia: async (fecha) => {
    const params = new URLSearchParams();
    params.append('action', 'eventos_dia');
    params.append('fecha', fecha);
    return await apiClient.get(`/calendario.php?${params.toString()}`);
  },
  
  // Obtener un evento específico
  getEvento: async (id) => {
    const params = new URLSearchParams();
    params.append('action', 'evento');
    params.append('id', id);
    return await apiClient.get(`/calendario.php?${params.toString()}`);
  },
  
  // Obtener tipos de eventos disponibles
  getTiposEvento: async () => {
    try {
      const response = await apiClient.get('/calendario.php?action=tipos_evento');
      return response;
    } catch (error) {
      console.warn('Usando tipos de evento fallback debido a error:', error);
      // Fallback si el servidor no responde
      return {
        success: true,
        data: [
          { value: 'cita', label: 'Cita con Cliente', color: '#3b82f6' },
          { value: 'llamada', label: 'Llamada de Seguimiento', color: '#10b981' },
          { value: 'reunion', label: 'Reunión', color: '#8b5cf6' },
          { value: 'tarea', label: 'Tarea Administrativa', color: '#f59e0b' },
          { value: 'visita', label: 'Visita de Campo', color: '#ef4444' },
          { value: 'otro', label: 'Otro', color: '#6b7280' }
        ]
      };
    }
  },
  
  // Obtener eventos próximos
  getEventosProximos: async (limite = 10) => {
    const params = new URLSearchParams();
    params.append('action', 'eventos_proximos');
    params.append('limite', limite);
    return await apiClient.get(`/calendario.php?${params.toString()}`);
  },
  
  // Crear nuevo evento
  crearEvento: async (eventoData) => {
    return await apiClient.post('/calendario.php', eventoData);
  },
  
  // Actualizar evento
  actualizarEvento: async (id, eventoData) => {
    return await apiClient.put(`/calendario.php?id=${id}`, eventoData);
  },
  
  // Eliminar evento
  eliminarEvento: async (id) => {
    return await apiClient.delete(`/calendario.php?id=${id}`);
  },
  
  // Marcar evento como completado
  completarEvento: async (id) => {
    return await apiClient.put(`/calendario.php?id=${id}`, { estado: 'completado' });
  },
  
  // Funciones auxiliares para fechas
  formatearFecha: (fecha, incluirHora = false) => {
    const date = new Date(fecha);
    if (incluirHora) {
      return date.toLocaleString('es-PE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      });
    } else {
      return date.toLocaleDateString('es-PE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
      });
    }
  },
  
  // Obtener color por tipo de evento
  getColorPorTipo: (tipo) => {
    const colores = {
      'cita': '#3b82f6',
      'llamada': '#10b981',
      'reunion': '#8b5cf6',
      'tarea': '#f59e0b',
      'visita': '#ef4444',
      'vencimiento': '#f59e0b',
      'vencido': '#dc2626',
      'cuota': '#3b82f6',
      'otro': '#6b7280'
    };
    return colores[tipo] || '#6b7280';
  },
  
  // Obtener nombre amigable del tipo de evento
  getNombreTipoEvento: (tipo) => {
    const nombres = {
      'cita': 'Cita con Cliente',
      'llamada': 'Llamada de Seguimiento',
      'reunion': 'Reunión',
      'tarea': 'Tarea Administrativa',
      'visita': 'Visita de Campo',
      'vencimiento': 'Vencimiento de Crédito',
      'vencido': 'Crédito Vencido',
      'cuota': 'Cuota Programada',
      'otro': 'Otro'
    };
    return nombres[tipo] || tipo;
  },
  
  // Validar datos del evento antes de enviar
  validarEvento: (evento) => {
    const errores = [];
    
    if (!evento.titulo || evento.titulo.trim() === '') {
      errores.push('El título es requerido');
    }
    
    if (!evento.fecha_inicio) {
      errores.push('La fecha de inicio es requerida');
    }
    
    if (!evento.fecha_fin) {
      errores.push('La fecha de fin es requerida');
    }
    
    if (evento.fecha_inicio && evento.fecha_fin) {
      const inicio = new Date(evento.fecha_inicio);
      const fin = new Date(evento.fecha_fin);
      if (inicio > fin) {
        errores.push('La fecha de inicio no puede ser posterior a la fecha de fin');
      }
    }
    
    if (!evento.tipo_evento) {
      errores.push('El tipo de evento es requerido');
    }
    
    return {
      valido: errores.length === 0,
      errores: errores
    };
  }
};

// Servicios de cobranza
export const cobranzaService = {
  // Obtener dashboard de cobranza
  getDashboard: async () => {
    return await apiClient.get('/cobranza.php?action=dashboard');
  },
  
  // Obtener créditos vencidos
  getCreditosVencidos: async () => {
    return await apiClient.get('/cobranza.php?action=creditos_vencidos');
  },
  
  // Obtener créditos próximos a vencer
  getProximosVencer: async (dias = 30) => {
    return await apiClient.get(`/cobranza.php?action=proximos_vencer&dias=${dias}`);
  },
  
  // Obtener seguimiento de cliente
  getSeguimientoCliente: async (idCliente) => {
    return await apiClient.get(`/cobranza.php?action=seguimiento_cliente&id_cliente=${idCliente}`);
  },
  
  // Registrar seguimiento
  registrarSeguimiento: async (data) => {
    return await apiClient.post('/cobranza.php', {
      action: 'registrar_seguimiento',
      ...data
    });
  },
  
  // Actualizar estado de crédito
  actualizarEstadoCredito: async (idCredito, nuevoEstado, observaciones = '') => {
    return await apiClient.post('/cobranza.php', {
      action: 'actualizar_estado_credito',
      id_credito: idCredito,
      nuevo_estado: nuevoEstado,
      observaciones: observaciones
    });
  },
  
  // Utilidades para formateo
  formatCurrency: (amount) => {
    return new Intl.NumberFormat('es-PE', {
      style: 'currency',
      currency: 'PEN'
    }).format(amount);
  },
  
  formatDate: (dateString, includeTime = false) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (includeTime) {
      return date.toLocaleString('es-PE');
    }
    return date.toLocaleDateString('es-PE');
  },
  
  // Obtener color por días vencido
  getDiasVencidoColor: (dias) => {
    if (dias <= 15) return 'warning';
    if (dias <= 30) return 'danger';
    return 'dark';
  },
  
  // Obtener prioridad por días
  getPrioridadCobranza: (dias) => {
    if (dias > 60) return 'Crítica';
    if (dias > 30) return 'Alta';
    if (dias > 15) return 'Media';
    return 'Normal';
  }
};

// Función helper para manejar errores de manera consistente
export const handleApiError = (error, defaultMessage = 'Ha ocurrido un error') => {
  console.error('API Error:', error);
  return error.message || defaultMessage;
};

export default apiClient;

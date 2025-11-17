import React, { useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { DollarSign, Plus, Search, Trash2, FileText, Eye, CreditCard, Calendar, Hash, X } from 'lucide-react';
import { paymentsService, creditsService } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import { useAutoRefresh } from '../hooks/useAutoRefresh';

const PagosPage = () => {
  const { hasPermission } = useAuth();
  const queryClient = useQueryClient();
  const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);

  // Auto-refresh cada 20 segundos
  useAutoRefresh(() => {
    queryClient.invalidateQueries(['pagos']);
  }, 20000, autoRefreshEnabled);

  // Helper para obtener fecha local en formato YYYY-MM-DD
  const getFechaLocal = () => {
    const hoy = new Date();
    const year = hoy.getFullYear();
    const month = String(hoy.getMonth() + 1).padStart(2, '0');
    const day = String(hoy.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  // Helper para formatear fecha sin problemas de zona horaria
  const formatearFecha = (fechaStr) => {
    if (!fechaStr) return '-';
    // Agregar 'T00:00:00' para forzar interpretación local
    const fecha = new Date(fechaStr + 'T00:00:00');
    return fecha.toLocaleDateString('es-PE', { 
      year: 'numeric', 
      month: '2-digit', 
      day: '2-digit' 
    });
  };

  // Filtros
  const [search, setSearch] = useState('');

  // Paginación
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(10);

  // Modal crear
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({
    id_credito: '',
    monto_pagado: '',
    referencia_pago: '',
    fecha_pago: '',
    tipo_pago: 'cuota_completa', // 'cuota_completa', 'multiple', 'parcial', 'personalizado', 'pago_completo'
    descuento: '0' // Descuento en soles para pago completo
  });
  
  // Lista de cuotas del kardex para el selector
  const [cuotasKardex, setCuotasKardex] = useState([]);
  const [montoCuota, setMontoCuota] = useState(0);
  const [saldoPendiente, setSaldoPendiente] = useState(0);
  const [cuotasSeleccionadas, setCuotasSeleccionadas] = useState([]); // Para múltiples cuotas

  // Modal detalle
  const [showDetail, setShowDetail] = useState(false);
  const [pagoDetalle, setPagoDetalle] = useState(null);

  // Créditos para selector (limit 100) - Solo créditos aprobados o vigentes con auto-refresh
  const { data: creditosList = [] } = useQuery({
    queryKey: ['creditos-selector'],
    queryFn: async () => {
      const res = await creditsService.getAll({ limit: 100 });
      const creditos = res?.data?.creditos || res?.creditos || [];
      // Filtrar solo créditos aprobados o vigentes (no pendientes, rechazados, etc.)
      const creditosValidos = creditos.filter(c => 
        c.estado_credito === 'Aprobado' || c.estado_credito === 'Vigente'
      );
      // Ordenar: primero los vigentes, luego los aprobados
      return creditosValidos.sort((a, b) => {
        const ordenEstado = { 'Vigente': 1, 'Aprobado': 2 };
        return (ordenEstado[a.estado_credito] || 5) - (ordenEstado[b.estado_credito] || 5);
      });
    },
    refetchInterval: 30000, // Refrescar cada 30 segundos para detectar nuevos créditos aprobados
    refetchIntervalInBackground: true
  });

  // Cargar pagos
  const { data: tableData = { pagos: [], pagination: {} }, isLoading } = useQuery({
    queryKey: ['pagos', page, limit],
    queryFn: async () => {
      const params = { page, limit };
      const res = await paymentsService.getAll(params);
      return res?.data || { pagos: res?.pagos || [], pagination: res?.pagination || {} };
    }
  });

  const pagos = useMemo(() => tableData.pagos || [], [tableData]);

  // Crear pago
  const createMutation = useMutation({
    mutationFn: async (payload) => {
      console.log('Registrando pago:', payload);
      const result = await paymentsService.create(payload);
      console.log('Pago registrado:', result);
      return result;
    },
    onSuccess: async () => {
      console.log('[OK] Pago registrado exitosamente, recargando datos...');
      
      // Invalidar y refrescar automáticamente
      await queryClient.invalidateQueries(['pagos']);
      await queryClient.invalidateQueries(['creditos']);
      await queryClient.invalidateQueries(['creditos-selector']);
      await queryClient.refetchQueries(['pagos']);
      await queryClient.refetchQueries(['creditos']);
      
      setShowModal(false);
      resetForm();
      alert('Pago registrado exitosamente');
    },
    onError: (e) => {
      console.error('[ERROR] Error al registrar pago:', e);
      alert('Error al registrar: ' + (e.message || ''));
    }
  });

  // Eliminar pago (gerente)
  const deleteMutation = useMutation({
    mutationFn: async (id) => {
      console.log('[DELETE] INICIANDO ELIMINACIÓN - Pago ID:', id);
      console.log('[DELETE] URL:', `${process.env.REACT_APP_API_URL || 'http://localhost/sistemaCredito/backend/api/'}pagos.php?id=${id}`);
      
      try {
        const result = await paymentsService.delete(id);
        console.log('[OK] RESPUESTA DEL SERVIDOR:', result);
        
        if (!result.success) {
          throw new Error(result.message || 'Error al eliminar');
        }
        
        return result;
      } catch (error) {
        console.error('[ERROR] ERROR EN LA PETICIÓN:', error);
        throw error;
      }
    },
    onSuccess: async (data, variables) => {
      console.log('[OK] PAGO ELIMINADO EXITOSAMENTE');
      console.log('Datos recibidos:', data);
      console.log('ID eliminado:', variables);
      
      // Invalidar TODAS las queries
      queryClient.invalidateQueries();
      
      // Esperar un momento y refrescar
      await new Promise(resolve => setTimeout(resolve, 300));
      
      // Refrescar queries específicas
      await queryClient.refetchQueries({ queryKey: ['pagos'], type: 'active' });
      
      alert('Pago eliminado exitosamente');
    },
    onError: (error) => {
      console.error('[ERROR] ERROR AL ELIMINAR PAGO');
      console.error('Error completo:', error);
      console.error('Error message:', error?.message);
      console.error('Error response:', error?.response);
      console.error('Error data:', error?.response?.data);
      
      const errorMsg = error?.response?.data?.message 
        || error?.message 
        || error?.toString() 
        || 'Error desconocido al eliminar el pago';
      
      alert('[ERROR] Error al eliminar pago:\n' + errorMsg);
    }
  });

  // Ver detalle de pago
  const detailMutation = useMutation({
    mutationFn: async (id) => paymentsService.getById(id),
    onSuccess: (res) => {
      const data = res?.data || res;
      setPagoDetalle(data);
      setShowDetail(true);
    },
    onError: (e) => alert('Error al cargar detalle: ' + (e.message || ''))
  });

  const resetForm = () => {
    setFormData({ id_credito: '', monto_pagado: '', referencia_pago: '', fecha_pago: '', tipo_pago: 'cuota_completa', descuento: '0' });
    setCuotasKardex([]);
    setMontoCuota(0);
    setSaldoPendiente(0);
    setCuotasSeleccionadas([]);
  };

  const openCreate = () => {
    resetForm();
    setShowModal(true);
  };

  // Función para redondear moneda peruana (igual que en KardexModal)
  const redondearMonedaPeruana = (valor) => {
    const centimos = Math.round((valor - Math.floor(valor)) * 100);
    let nuevoCentimo = 0;
    
    if (centimos < 10) {
      nuevoCentimo = 0;
    } else if (centimos < 35) {
      nuevoCentimo = 20;
    } else if (centimos < 75) {
      nuevoCentimo = 50;
    } else {
      return Math.ceil(valor);
    }
    
    return Math.floor(valor) + (nuevoCentimo / 100);
  };

  // Calcular fechas del kardex (igual que en KardexModal)
  const calcularFechasKardex = (tipoPago, numPagos, fechaInicio = new Date()) => {
    const fechas = [];
    let fechaActual = new Date(fechaInicio);
    let delta = 1;

    if (tipoPago === 'Diario') {
      delta = 1;
    } else if (tipoPago === 'Semanal') {
      delta = 7;
    } else if (tipoPago === 'Quincenal') {
      delta = 15;
    } else if (tipoPago === 'Mensual') {
      delta = 30;
    }

    while (fechas.length < numPagos) {
      if (fechaActual.getDay() !== 0) { // Saltar domingos
        fechas.push(new Date(fechaActual));
      }
      fechaActual = new Date(fechaActual);
      fechaActual.setDate(fechaActual.getDate() + delta);
    }

    return fechas;
  };

  // Generar kardex completo con todas las cuotas
  const generarKardexCompleto = (credito, pagosPorCuota = {}) => {
    const monto = Number(credito.monto_aprobado ?? credito.monto_original ?? 0);
    const interesMensual = Number(credito.tasa_interes ?? 0) / 100;
    const meses = Number(credito.plazos_meses ?? 12);
    const tipoPago = credito.periodo_pago || 'Mensual';

    // Calcular monto total con interés
    const montoTotal = monto * (1 + interesMensual * meses);
    
    // Calcular número de pagos según el periodo de pago
    let numPagos = meses;
    
    if (tipoPago === 'Diario') {
      numPagos = Math.ceil(meses * 26);
    } else if (tipoPago === 'Semanal') {
      numPagos = Math.ceil(meses * 4);
    } else if (tipoPago === 'Quincenal') {
      numPagos = meses * 2;
    } else if (tipoPago === 'Mensual') {
      numPagos = meses;
    }

    // Calcular cuota redondeada
    const cuota = redondearMonedaPeruana(montoTotal / numPagos);
    
    // Generar fechas
    const fechas = calcularFechasKardex(tipoPago, numPagos);
    
    // Generar array de cuotas con estado de pagado basado en montos pagados
    const cuotas = [];
    for (let i = 0; i < numPagos; i++) {
      const numeroCuota = i + 1;
      const montoPagado = pagosPorCuota[numeroCuota] || 0;
      const porcentajePagado = (montoPagado / cuota) * 100;
      
      cuotas.push({
        numero: numeroCuota,
        fecha: fechas[i],
        monto: cuota,
        montoPagado: montoPagado,
        porcentajePagado: porcentajePagado,
        pagada: porcentajePagado >= 99.9 // Considerar pagada si se pagó al menos el 99.9% (tolerancia por redondeos)
      });
    }
    
    return { cuotas, cuota, numPagos, montoTotal };
  };

  // Autocompletar campos cuando se selecciona un crédito
  const handleCreditoChange = async (e) => {
    const creditoId = e.target.value;
    
    if (!creditoId) {
      setFormData({ id_credito: '', monto_pagado: '', referencia_pago: '', fecha_pago: '', tipo_pago: 'cuota_completa' });
      setCuotasKardex([]);
      setMontoCuota(0);
      setSaldoPendiente(0);
      return;
    }

    // Buscar el crédito seleccionado
    const creditoSeleccionado = creditosList.find(c => String(c.id_credito) === String(creditoId));
    
    if (creditoSeleccionado) {
      try {
        // Obtener todos los pagos de este crédito
        const resPagos = await paymentsService.getAll({ credito_id: creditoId, limit: 1000 });
        const pagosCredito = resPagos?.data?.pagos || resPagos?.pagos || [];
        
        console.log('Pagos del crédito:', pagosCredito);
        console.log('Descuentos en pagos:', pagosCredito.map(p => ({ id: p.id_pago, descuento: p.descuento })));
        
        // Primero calcular el monto por cuota para poder distribuir correctamente
        const monto = Number(creditoSeleccionado.monto_aprobado ?? creditoSeleccionado.monto_original ?? 0);
        const interesMensual = Number(creditoSeleccionado.tasa_interes ?? 0) / 100;
        const meses = Number(creditoSeleccionado.plazos_meses ?? 12);
        const montoTotal = monto * (1 + interesMensual * meses);
        
        let numPagos = meses;
        const tipoPago = creditoSeleccionado.periodo_pago || 'Mensual';
        if (tipoPago === 'Diario') numPagos = Math.ceil(meses * 26);
        else if (tipoPago === 'Semanal') numPagos = Math.ceil(meses * 4);
        else if (tipoPago === 'Quincenal') numPagos = meses * 2;
        
        const montoPorCuota = redondearMonedaPeruana(montoTotal / numPagos);
        
        // Calcular cuánto se ha pagado de cada cuota
        const pagosPorCuota = {}; // { numeroCuota: montoPagado }
        
        pagosCredito.forEach(pago => {
          const ref = pago.referencia_pago || '';
          let montoRestante = Number(pago.monto_pagado || 0);
          
          // Puede haber múltiples cuotas separadas por comas (ej: "CUOTA-2-4,CUOTA-3-4")
          const referencias = ref.split(',');
          
          // Extraer números de cuota
          const numerosCuota = [];
          referencias.forEach(refIndividual => {
            const match = refIndividual.trim().match(/CUOTA-(\d+)-\d+/);
            if (match) {
              numerosCuota.push(parseInt(match[1], 10));
            }
          });
          
          // Ordenar cuotas para aplicar secuencialmente
          numerosCuota.sort((a, b) => a - b);
          
          // Aplicar el monto secuencialmente: llenar cada cuota antes de pasar a la siguiente
          numerosCuota.forEach(numCuota => {
            if (montoRestante <= 0.01) return; // Tolerancia de 1 céntimo
            
            // Calcular cuánto falta por pagar de esta cuota
            const montoPagadoAntes = pagosPorCuota[numCuota] || 0;
            const montoPendiente = montoPorCuota - montoPagadoAntes;
            
            if (montoPendiente <= 0.01) return; // Esta cuota ya está pagada
            
            // Aplicar el mínimo entre lo que queda y lo que falta de la cuota
            const montoAAplicar = Math.min(montoRestante, montoPendiente);
            
            pagosPorCuota[numCuota] = montoPagadoAntes + montoAAplicar;
            montoRestante -= montoAAplicar;
          });
        });
        
        console.log('Pagos por cuota:', pagosPorCuota);
        
        // Obtener fecha actual en formato YYYY-MM-DD (local)
        const fechaHoy = getFechaLocal();
        
        // Generar kardex completo con los montos pagados por cuota
        const { cuotas, cuota, montoTotal: montoTotalKardex } = generarKardexCompleto(creditoSeleccionado, pagosPorCuota);
        setCuotasKardex(cuotas);
        setMontoCuota(cuota);
        
        // Calcular saldo pendiente considerando descuentos
        const totalPagado = pagosCredito.reduce((sum, p) => sum + Number(p.monto_pagado || 0), 0);
        const totalDescuentos = pagosCredito.reduce((sum, p) => sum + Number(p.descuento || 0), 0);
        const saldo = montoTotalKardex - totalPagado - totalDescuentos;
        setSaldoPendiente(saldo > 0 ? saldo : 0);
        
        console.log('Cálculo de saldo:', {
          montoTotal: montoTotalKardex,
          totalPagado,
          totalDescuentos,
          saldoFinal: saldo
        });
        
        const montoSugerido = cuota.toFixed(2);
        
        // Auto-seleccionar la primera cuota pendiente
        const primeraCuotaPendiente = cuotas.find(c => !c.pagada);
        let referenciaAutoSeleccionada = '';
        let montoAutoSeleccionado = montoSugerido;
        
        if (primeraCuotaPendiente) {
          referenciaAutoSeleccionada = `CUOTA-${primeraCuotaPendiente.numero}-${cuotas.length}`;
          // Calcular el monto pendiente de esta cuota
          const montoPendiente = primeraCuotaPendiente.monto - primeraCuotaPendiente.montoPagado;
          montoAutoSeleccionado = montoPendiente.toFixed(2);
        }
        
        setFormData({
          id_credito: creditoId,
          monto_pagado: montoAutoSeleccionado,
          referencia_pago: referenciaAutoSeleccionada, // Auto-seleccionar primera cuota pendiente
          fecha_pago: fechaHoy,
          tipo_pago: 'cuota_completa'
        });
      } catch (error) {
        console.error('Error al cargar pagos del crédito:', error);
        // Si hay error, generar kardex sin cuotas pagadas
        const fechaHoy = getFechaLocal();
        const { cuotas, cuota, montoTotal: montoTotalError } = generarKardexCompleto(creditoSeleccionado, {});
        setCuotasKardex(cuotas);
        setMontoCuota(cuota);
        setSaldoPendiente(montoTotalError);
        const montoSugerido = cuota.toFixed(2);
        
        // Auto-seleccionar la primera cuota pendiente
        const primeraCuotaPendiente = cuotas.find(c => !c.pagada);
        let referenciaAutoSeleccionada = '';
        let montoAutoSeleccionado = montoSugerido;
        
        if (primeraCuotaPendiente) {
          referenciaAutoSeleccionada = `CUOTA-${primeraCuotaPendiente.numero}-${cuotas.length}`;
          const montoPendiente = primeraCuotaPendiente.monto - primeraCuotaPendiente.montoPagado;
          montoAutoSeleccionado = montoPendiente.toFixed(2);
        }
        
        setFormData({
          id_credito: creditoId,
          monto_pagado: montoAutoSeleccionado,
          referencia_pago: referenciaAutoSeleccionada, // Auto-seleccionar primera cuota pendiente
          fecha_pago: fechaHoy,
          tipo_pago: 'cuota_completa'
        });
      }
    } else {
      setFormData({ ...formData, id_credito: creditoId });
      setCuotasKardex([]);
      setMontoCuota(0);
      setSaldoPendiente(0);
    }
  };

  // Manejar cambio de tipo de pago
  const handleTipoPagoChange = (e) => {
    const tipo = e.target.value;
    let nuevoMonto = formData.monto_pagado;
    let nuevoDescuento = '0';
    
    // Limpiar selección de cuotas al cambiar tipo
    setCuotasSeleccionadas([]);
    
    if (tipo === 'cuota_completa') {
      // Buscar la primera cuota pendiente y calcular su monto pendiente
      const primeraCuotaPendiente = cuotasKardex.find(c => !c.pagada);
      if (primeraCuotaPendiente) {
        const montoPendiente = primeraCuotaPendiente.monto - primeraCuotaPendiente.montoPagado;
        nuevoMonto = montoPendiente.toFixed(2);
      } else {
        // Si no hay cuotas pendientes, usar el monto de cuota completo
        nuevoMonto = Math.min(montoCuota, saldoPendiente).toFixed(2);
      }
    } else if (tipo === 'multiple') {
      // Por defecto 2 cuotas, pero limitado al saldo pendiente
      nuevoMonto = Math.min(montoCuota * 2, saldoPendiente).toFixed(2);
    } else if (tipo === 'parcial') {
      // Por defecto media cuota
      nuevoMonto = (montoCuota / 2).toFixed(2);
    } else if (tipo === 'personalizado') {
      nuevoMonto = ''; // Dejar vacío para que el usuario ingrese
    } else if (tipo === 'pago_completo') {
      // Pago completo del crédito (saldo pendiente)
      nuevoMonto = saldoPendiente.toFixed(2);
      nuevoDescuento = '0'; // Descuento inicial en 0
    }
    
    setFormData({
      ...formData,
      tipo_pago: tipo,
      monto_pagado: nuevoMonto,
      referencia_pago: '', // Limpiar referencia al cambiar tipo
      descuento: nuevoDescuento
    });
  };

  // Manejar selección/deselección de cuotas (para tipo múltiple)
  const handleToggleCuota = (numeroCuota) => {
    let nuevasSeleccionadas = [...cuotasSeleccionadas];
    
    if (nuevasSeleccionadas.includes(numeroCuota)) {
      // Deseleccionar
      nuevasSeleccionadas = nuevasSeleccionadas.filter(n => n !== numeroCuota);
    } else {
      // Seleccionar
      nuevasSeleccionadas.push(numeroCuota);
      nuevasSeleccionadas.sort((a, b) => a - b); // Ordenar
    }
    
    setCuotasSeleccionadas(nuevasSeleccionadas);
    
    // Actualizar monto y referencia
    if (nuevasSeleccionadas.length > 0) {
      const montoTotal = (montoCuota * nuevasSeleccionadas.length).toFixed(2);
      const referencia = nuevasSeleccionadas.map(n => `CUOTA-${n}-${cuotasKardex.length}`).join(',');
      
      setFormData({
        ...formData,
        monto_pagado: montoTotal,
        referencia_pago: referencia
      });
    } else {
      setFormData({
        ...formData,
        monto_pagado: '',
        referencia_pago: ''
      });
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!formData.id_credito || !formData.monto_pagado) {
      alert('Completa crédito y monto');
      return;
    }
    
    const montoPago = Number(formData.monto_pagado);
    
    if (montoPago <= 0) {
      alert('El monto debe ser mayor a 0');
      return;
    }
    
    // Validar que el monto no exceda el saldo pendiente
    if (montoPago > saldoPendiente) {
      alert(`El monto a pagar (${formatCurrency(montoPago)}) no puede ser mayor al saldo pendiente (${formatCurrency(saldoPendiente)})`);
      return;
    }
    
    // Para pago completo, generar referencia de todas las cuotas pendientes
    if (formData.tipo_pago === 'pago_completo') {
      const cuotasPendientes = cuotasKardex.filter(c => !c.pagada);
      
      if (cuotasPendientes.length === 0) {
        alert('No hay cuotas pendientes para pagar');
        return;
      }
      
      // Generar referencia con todas las cuotas pendientes
      const referencia = cuotasPendientes.map(c => `CUOTA-${c.numero}-${cuotasKardex.length}`).join(',');
      
      const descuento = Number(formData.descuento || 0);
      const mensaje = descuento > 0
        ? `Se pagará el crédito completo con un descuento de ${formatCurrency(descuento)}\n\nSaldo pendiente: ${formatCurrency(saldoPendiente)}\nDescuento: ${formatCurrency(descuento)}\nTotal a pagar: ${formatCurrency(montoPago)}\n\n¿Continuar?`
        : `Se pagará el crédito completo por ${formatCurrency(montoPago)}\n\n¿Continuar?`;
      
      if (window.confirm(mensaje)) {
        const payload = {
          id_credito: Number(formData.id_credito),
          monto_pagado: parseFloat(formData.monto_pagado),
          referencia_pago: referencia,
          fecha_pago: formData.fecha_pago || undefined,
          descuento: descuento > 0 ? descuento : undefined
        };
        createMutation.mutate(payload);
      }
      return;
    }
    
    // Para monto personalizado, calcular automáticamente las cuotas que cubre
    if (formData.tipo_pago === 'personalizado' && montoPago > 0) {
      // Calcular cuántas cuotas cubre el monto
      const cuotasPendientes = cuotasKardex.filter(c => !c.pagada);
      
      if (cuotasPendientes.length === 0) {
        alert('No hay cuotas pendientes para pagar');
        return;
      }
      
      // Generar referencia automática basada en cuántas cuotas cubre
      let montoRestante = montoPago;
      const cuotasCubiertas = [];
      
      for (const cuota of cuotasPendientes) {
        if (montoRestante <= 0) break;
        
        const montoPendienteCuota = cuota.monto - cuota.montoPagado;
        
        if (montoRestante >= montoPendienteCuota) {
          // Cubre esta cuota completa
          cuotasCubiertas.push(cuota.numero);
          montoRestante -= montoPendienteCuota;
        } else {
          // Cubre parcialmente esta cuota
          cuotasCubiertas.push(cuota.numero);
          break;
        }
      }
      
      // Generar referencia con las cuotas cubiertas
      const referencia = cuotasCubiertas.map(n => `CUOTA-${n}-${cuotasKardex.length}`).join(',');
      
      const payload = {
        id_credito: Number(formData.id_credito),
        monto_pagado: parseFloat(formData.monto_pagado),
        referencia_pago: referencia,
        fecha_pago: formData.fecha_pago || undefined
      };
      
      // Mostrar confirmación de cuántas cuotas se pagarán
      const numCuotas = cuotasCubiertas.length;
      const mensaje = numCuotas === 1 
        ? `Se pagará la cuota ${cuotasCubiertas[0]}${montoRestante > 0 ? ' y se adelantará ' + formatCurrency(montoRestante) : ''}`
        : `Se pagarán ${numCuotas} cuotas (${cuotasCubiertas.join(', ')})${montoRestante > 0 ? ' y se adelantará ' + formatCurrency(montoRestante) : ''}`;
      
      if (window.confirm(`${mensaje}\n\n¿Continuar con el pago?`)) {
        createMutation.mutate(payload);
      }
      return;
    }
    
    // Validar que se haya seleccionado una cuota para otros tipos (excepto pago completo)
    if (!formData.referencia_pago && formData.tipo_pago !== 'pago_completo') {
      alert('Debes seleccionar al menos una cuota');
      return;
    }
    
    const payload = {
      id_credito: Number(formData.id_credito),
      monto_pagado: parseFloat(formData.monto_pagado),
      referencia_pago: formData.referencia_pago || undefined,
      fecha_pago: formData.fecha_pago || undefined
    };
    createMutation.mutate(payload);
  };

  const handleViewDetail = (pago) => {
    detailMutation.mutate(pago.id_pago);
  };

  const handleDelete = (pago) => {
    if (!window.confirm(`¿Eliminar pago #${pago.id_pago}?`)) return;
    deleteMutation.mutate(pago.id_pago);
  };

  const filtered = pagos
    .filter((p) => {
      const q = search.toLowerCase();
      const nombre = `${p.nombres || ''} ${p.apellido_paterno || ''} ${p.apellido_materno || ''}`.toLowerCase();
      const dni = (p.dni || '').toLowerCase();
      return nombre.includes(q) || dni.includes(q) || String(p.id_credito).includes(q);
    })
    .sort((a, b) => (b.id_pago || 0) - (a.id_pago || 0)); // Ordenar por ID descendente (más recientes primero)

  const formatCurrency = (n) => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(Number(n || 0));

  return (
    <div className="container-fluid">
      <div className="row">
        <div className="col-12">
          <div className="card pagos-card">
            <div className="card-header pagos-header">
              <div className="d-flex justify-content-between align-items-center">
                <h5 className="card-title mb-0">
                  <DollarSign className="me-2" /> Gestión de Pagos
                </h5>
                {hasPermission('trabajador') && (
                  <button className="btn btn-primary pagos-new-btn" onClick={openCreate}>
                    <Plus size={18} className="me-2" /> Nuevo Pago
                  </button>
                )}
              </div>
            </div>

            <div className="card-body">
              {/* Filtros */}
              <div className="row g-2 mb-3 pagos-controles">
                <div className="col-md-6">
                  <div className="input-group">
                    <span className="input-group-text"><Search size={16} /></span>
                    <input className="form-control" placeholder="Buscar por cliente, DNI o crédito" value={search} onChange={(e) => setSearch(e.target.value)} />
                  </div>
                </div>
              </div>

              {/* Tabla */}
              <div className="table-responsive">
                <table className="table table-striped table-hover">
                  <thead className="table-dark">
                    <tr>
                      <th>#Pago</th>
                      <th>Crédito</th>
                      <th>Cliente</th>
                      <th>Fecha</th>
                      <th>Monto</th>
                      <th>Capital</th>
                      <th>Interés</th>
                      <th>Mora</th>
                      <th>Referencia</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {isLoading ? (
                      <tr><td colSpan="10">Cargando...</td></tr>
                    ) : filtered.length ? (
                      filtered.map((p) => (
                        <tr key={p.id_pago}>
                          <td>{p.id_pago}</td>
                          <td>
                            <span className="d-flex align-items-center">
                              <FileText size={14} className="me-1" /> #{p.id_credito}
                            </span>
                          </td>
                          <td>{`${p.nombres || ''} ${p.apellido_paterno || ''} ${p.apellido_materno || ''}`}</td>
                          <td>{formatearFecha(p.fecha_pago)}</td>
                          <td>{formatCurrency(p.monto_pagado)}</td>
                          <td>{formatCurrency(p.capital_pagado)}</td>
                          <td>{formatCurrency(p.interes_pagado)}</td>
                          <td>{formatCurrency(p.mora_pagada)}</td>
                          <td>{p.referencia_pago || '-'}</td>
                          <td>
                            <div className="btn-group btn-group-sm" role="group">
                              <button className="btn btn-outline-secondary" title="Detalle" onClick={() => handleViewDetail(p)}>
                                <Eye size={14} />
                              </button>
                              {hasPermission('gerente') && (
                                <button className="btn btn-outline-danger" title="Eliminar" onClick={() => handleDelete(p)}>
                                  <Trash2 size={14} />
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr><td colSpan="10" className="text-center text-muted">No se encontraron pagos</td></tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Paginación */}
            <div className="card-footer d-flex justify-content-between align-items-center">
              <div>
                <button className="btn btn-outline-secondary btn-sm me-2" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
                  Anterior
                </button>
                <button className="btn btn-outline-secondary btn-sm" disabled={page >= (tableData.pagination?.pages || 1)} onClick={() => setPage((p) => p + 1)}>
                  Siguiente
                </button>
              </div>
              <div className="d-flex align-items-center gap-2">
                <span className="me-2">Página {page} de {tableData.pagination?.pages || 1}</span>
                <select className="form-select form-select-sm" style={{ width: 'auto' }} value={limit} onChange={(e) => { setLimit(Number(e.target.value)); setPage(1); }}>
                  {[10, 20, 50].map((n) => (
                    <option key={n} value={n}>{n} por página</option>
                  ))}
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Modal Crear Pago */}
      {showModal && createPortal(
        <div className="modal-overlay" onClick={() => { setShowModal(false); resetForm(); }}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '600px' }}>
            <div className="modal-header">
              <h2>Registrar Pago</h2>
              <button type="button" className="modal-close" onClick={() => { setShowModal(false); resetForm(); }}>
                <X size={24} />
              </button>
            </div>
            <form onSubmit={handleSubmit}>
              <div className="modal-body">
                <div className="form-group">
                  <label className="form-label">
                    <CreditCard size={16} className="inline-block mr-1" />
                    Crédito *
                  </label>
                  <select className="form-input" value={formData.id_credito} onChange={handleCreditoChange} required>
                    <option value="">Seleccionar crédito...</option>
                    {creditosList.map((c) => {
                      const estaPagado = c.estado_credito === 'Pagado';
                      const estaRechazado = c.estado_credito === 'Rechazado';
                      const noDisponible = estaPagado || estaRechazado;
                      
                      let estadoTexto = '';
                      if (estaPagado) estadoTexto = ' (Pagado)';
                      if (estaRechazado) estadoTexto = ' (Rechazado)';
                      
                      return (
                        <option 
                          key={c.id_credito} 
                          value={c.id_credito}
                          disabled={noDisponible}
                          style={noDisponible ? { color: '#999', fontStyle: 'italic' } : {}}
                        >
                          #{c.id_credito} - {`${c.nombres || ''} ${c.apellido_paterno || ''} ${c.apellido_materno || ''}`} - S/ {Number(c.monto_aprobado || c.monto_original || 0).toFixed(2)}{estadoTexto}
                        </option>
                      );
                    })}
                  </select>
                </div>

                {/* Información del crédito seleccionado */}
                {formData.id_credito && (
                  <div style={{ 
                    background: '#e3f2fd', 
                    border: '1px solid #90caf9', 
                    borderRadius: '6px', 
                    padding: '0.75rem', 
                    marginBottom: '1rem',
                    fontSize: '0.9rem'
                  }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                      <span><strong>Monto por cuota:</strong></span>
                      <span style={{ color: '#1976d2', fontWeight: '600' }}>{formatCurrency(montoCuota)}</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                      <span><strong>Saldo pendiente:</strong></span>
                      <span style={{ color: '#d32f2f', fontWeight: '600' }}>{formatCurrency(saldoPendiente)}</span>
                    </div>
                  </div>
                )}
                
                <div className="form-group">
                  <label className="form-label">
                    <DollarSign size={16} className="inline-block mr-1" />
                    Tipo de Pago *
                  </label>
                  <select 
                    className="form-input" 
                    value={formData.tipo_pago} 
                    onChange={handleTipoPagoChange}
                    disabled={!formData.id_credito}
                  >
                    <option value="cuota_completa">Cuota Completa</option>
                    <option value="multiple">Múltiples Cuotas</option>
                    <option value="parcial">Pago Parcial</option>
                    <option value="personalizado">Monto Personalizado</option>
                    <option value="pago_completo">Pago Completo del Crédito</option>
                  </select>
                </div>
                
                {/* Campo de descuento (solo para pago completo) */}
                {formData.tipo_pago === 'pago_completo' && (
                  <div className="form-group">
                    <label className="form-label">
                      <DollarSign size={16} className="inline-block mr-1" />
                      Descuento Aplicado (S/.) *
                    </label>
                    <input 
                      type="text" 
                      className="form-input" 
                      value={formData.descuento} 
                      onChange={(e) => {
                        const valor = e.target.value;
                        if (valor === '' || /^\d*\.?\d*$/.test(valor)) {
                          const descuento = Number(valor || 0);
                          const montoFinal = Math.max(0, saldoPendiente - descuento);
                          setFormData({ 
                            ...formData, 
                            descuento: valor,
                            monto_pagado: montoFinal.toFixed(2)
                          });
                        }
                      }}
                      onKeyPress={(e) => {
                        const char = e.key;
                        const currentValue = e.target.value;
                        if (char >= '0' && char <= '9') return;
                        if (char === '.' && !currentValue.includes('.')) return;
                        e.preventDefault();
                      }}
                      placeholder="0.00"
                    />
                    <small className="text-muted">
                      Ingresa el descuento que se aplicará al saldo pendiente
                    </small>
                    {formData.descuento && Number(formData.descuento) > 0 && (
                      <div style={{ 
                        background: '#e8f5e9', 
                        border: '1px solid #81c784', 
                        borderRadius: '4px', 
                        padding: '0.5rem', 
                        marginTop: '0.5rem',
                        fontSize: '0.85rem'
                      }}>
                        <div style={{ marginBottom: '0.25rem' }}>
                          <strong>Saldo pendiente:</strong> {formatCurrency(saldoPendiente)}
                        </div>
                        <div style={{ marginBottom: '0.25rem', color: '#2e7d32' }}>
                          <strong>Descuento:</strong> - {formatCurrency(Number(formData.descuento))}
                        </div>
                        <div style={{ borderTop: '1px solid #81c784', paddingTop: '0.25rem', marginTop: '0.25rem' }}>
                          <strong>Total a pagar:</strong> {formatCurrency(Number(formData.monto_pagado))}
                        </div>
                      </div>
                    )}
                  </div>
                )}
                
                <div className="form-group">
                  <label className="form-label">
                    <DollarSign size={16} className="inline-block mr-1" />
                    Monto a Pagar (S/.) *
                  </label>
                  <input 
                    type="text" 
                    className="form-input" 
                    value={formData.monto_pagado} 
                    onChange={(e) => {
                      // Solo permitir números y punto decimal
                      const valor = e.target.value;
                      if (valor === '' || /^\d*\.?\d*$/.test(valor)) {
                        setFormData({ ...formData, monto_pagado: valor });
                      }
                    }}
                    onKeyPress={(e) => {
                      // Bloquear cualquier tecla que no sea número o punto decimal
                      const char = e.key;
                      const currentValue = e.target.value;
                      
                      // Permitir números (0-9)
                      if (char >= '0' && char <= '9') {
                        return;
                      }
                      
                      // Permitir punto decimal solo si no hay uno ya
                      if (char === '.' && !currentValue.includes('.')) {
                        return;
                      }
                      
                      // Bloquear todo lo demás
                      e.preventDefault();
                    }}
                    onPaste={(e) => {
                      // Validar contenido pegado
                      e.preventDefault();
                      const pastedText = e.clipboardData.getData('text');
                      if (/^\d*\.?\d*$/.test(pastedText)) {
                        setFormData({ ...formData, monto_pagado: pastedText });
                      }
                    }}
                    required 
                    placeholder="0.00"
                    disabled={!formData.id_credito}
                    readOnly={formData.tipo_pago !== 'personalizado'}
                    style={(formData.tipo_pago !== 'personalizado') ? { background: '#e9ecef', cursor: 'not-allowed' } : {}}
                  />
                  {/* Información de cuota con adelanto para tipo "cuota_completa" */}
                  {formData.tipo_pago === 'cuota_completa' && formData.referencia_pago && formData.monto_pagado && (
                    (() => {
                      const match = formData.referencia_pago.match(/CUOTA-(\d+)-\d+/);
                      if (match) {
                        const numCuota = parseInt(match[1], 10);
                        const cuotaSeleccionada = cuotasKardex.find(c => c.numero === numCuota);
                        
                        if (cuotaSeleccionada && cuotaSeleccionada.montoPagado > 0) {
                          return (
                            <div style={{ 
                              background: '#e3f2fd', 
                              border: '1px solid #90caf9', 
                              borderRadius: '4px', 
                              padding: '0.5rem', 
                              marginTop: '0.5rem',
                              fontSize: '0.85rem'
                            }}>
                              <strong>Cuota con adelanto:</strong>
                              <div style={{ marginTop: '0.25rem' }}>
                                Ya pagado: {formatCurrency(cuotaSeleccionada.montoPagado)} ({cuotaSeleccionada.porcentajePagado.toFixed(0)}%)
                              </div>
                              <div style={{ color: '#1976d2', fontWeight: '600' }}>
                                Falta por pagar: {formatCurrency(cuotaSeleccionada.monto - cuotaSeleccionada.montoPagado)}
                              </div>
                            </div>
                          );
                        }
                      }
                      return null;
                    })()
                  )}
                  
                  {/* Advertencia si el monto excede el saldo pendiente */}
                  {formData.monto_pagado && Number(formData.monto_pagado) > saldoPendiente && (
                    <div style={{ 
                      background: '#fee', 
                      border: '1px solid #fcc', 
                      borderRadius: '4px', 
                      padding: '0.5rem', 
                      marginTop: '0.5rem',
                      color: '#c00'
                    }}>
                      <strong>Advertencia:</strong> El monto ({formatCurrency(Number(formData.monto_pagado))}) excede el saldo pendiente ({formatCurrency(saldoPendiente)})
                    </div>
                  )}
                  
                  {/* Información de cuotas cubiertas para monto personalizado */}
                  {formData.tipo_pago === 'personalizado' && formData.monto_pagado && Number(formData.monto_pagado) > 0 && montoCuota > 0 && (
                    <div style={{ 
                      background: '#e8f5e9', 
                      border: '1px solid #81c784', 
                      borderRadius: '4px', 
                      padding: '0.5rem', 
                      marginTop: '0.5rem',
                      fontSize: '0.85rem'
                    }}>
                      {(() => {
                        const monto = Number(formData.monto_pagado);
                        const cuotasPendientes = cuotasKardex.filter(c => !c.pagada);
                        let montoRestante = monto;
                        const cuotasCubiertas = [];
                        
                        for (const cuota of cuotasPendientes) {
                          if (montoRestante <= 0) break;
                          const montoPendiente = cuota.monto - cuota.montoPagado;
                          if (montoRestante >= montoPendiente) {
                            cuotasCubiertas.push(cuota.numero);
                            montoRestante -= montoPendiente;
                          } else {
                            cuotasCubiertas.push(cuota.numero);
                            break;
                          }
                        }
                        
                        const numCuotas = cuotasCubiertas.length;
                        if (numCuotas === 0) return null;
                        
                        return (
                          <>
                            <strong>Este pago cubrirá:</strong>
                            <div style={{ marginTop: '0.25rem' }}>
                              {numCuotas === 1 ? (
                                <>Cuota {cuotasCubiertas[0]}</>
                              ) : (
                                <>{numCuotas} cuotas ({cuotasCubiertas.join(', ')})</>
                              )}
                            </div>
                            {montoRestante > 0.01 && (
                              <div style={{ marginTop: '0.25rem', color: '#2e7d32' }}>
                                <strong>Adelanto:</strong> {formatCurrency(montoRestante)}
                              </div>
                            )}
                          </>
                        );
                      })()}
                    </div>
                  )}
                  
                  {formData.tipo_pago === 'multiple' && montoCuota > 0 && cuotasSeleccionadas.length > 0 && (
                    <small className="text-muted">
                      {cuotasSeleccionadas.length} cuota{cuotasSeleccionadas.length !== 1 ? 's' : ''} × {formatCurrency(montoCuota)} = {formatCurrency(Number(formData.monto_pagado))}
                    </small>
                  )}
                  {formData.tipo_pago === 'parcial' && montoCuota > 0 && formData.monto_pagado && (
                    <small className="text-muted">
                      Equivale al {((Number(formData.monto_pagado) / montoCuota) * 100).toFixed(0)}% de una cuota
                    </small>
                  )}
                </div>
                
                <div className="form-group">
                  <label className="form-label">
                    <Calendar size={16} className="inline-block mr-1" />
                    Fecha de Pago
                  </label>
                  <input 
                    type="date" 
                    className="form-input" 
                    value={formData.fecha_pago} 
                    onChange={(e) => setFormData({ ...formData, fecha_pago: e.target.value })} 
                    readOnly
                    style={{ background: '#e9ecef', cursor: 'not-allowed' }}
                  />
                  <small className="text-muted">
                    La fecha se establece automáticamente al día actual
                  </small>
                </div>
                
                <div className="form-group">
                  <label className="form-label">
                    <Hash size={16} className="inline-block mr-1" />
                    {formData.tipo_pago === 'multiple' ? 'Cuotas a Pagar *' : 'Cuota a Pagar *'}
                  </label>
                  
                  {formData.tipo_pago === 'multiple' ? (
                    // Mostrar checkboxes para selección múltiple
                    <div style={{ 
                      maxHeight: '200px', 
                      overflowY: 'auto', 
                      border: '1px solid #ced4da', 
                      borderRadius: '6px', 
                      padding: '0.75rem',
                      background: '#f8f9fa'
                    }}>
                      {cuotasKardex.length === 0 ? (
                        <p className="text-muted mb-0">Selecciona un crédito primero</p>
                      ) : (
                        cuotasKardex.map((cuota) => {
                          // Encontrar la primera cuota no pagada completamente
                          const primeraCuotaPendiente = cuotasKardex.find(c => !c.pagada);
                          const numeroPrimeraPendiente = primeraCuotaPendiente ? primeraCuotaPendiente.numero : null;
                          
                          // En modo múltiple: permitir seleccionar cuotas consecutivas desde la primera pendiente
                          // Encontrar la última cuota seleccionada
                          const ultimaCuotaSeleccionada = cuotasSeleccionadas.length > 0 
                            ? Math.max(...cuotasSeleccionadas) 
                            : (numeroPrimeraPendiente ? numeroPrimeraPendiente - 1 : 0);
                          
                          // Deshabilitar si:
                          // 1. La cuota ya está pagada
                          // 2. O si hay un "hueco" en la selección (no es consecutiva)
                          const hayHuecoEnSeleccion = cuotasSeleccionadas.length > 0 && 
                                                      cuota.numero > ultimaCuotaSeleccionada + 1 && 
                                                      !cuotasSeleccionadas.includes(cuota.numero);
                          
                          const hayPendientesAnteriores = numeroPrimeraPendiente && 
                                                          cuota.numero > numeroPrimeraPendiente && 
                                                          cuotasSeleccionadas.length === 0;
                          
                          const estaDeshabilitada = cuota.pagada || hayPendientesAnteriores || hayHuecoEnSeleccion;
                          
                          return (
                            <div 
                              key={cuota.numero} 
                              style={{ 
                                marginBottom: '0.5rem',
                                padding: '0.5rem',
                                background: estaDeshabilitada ? '#e9ecef' : 'white',
                                borderRadius: '4px',
                                border: '1px solid #dee2e6'
                              }}
                            >
                              <label style={{ 
                                display: 'flex', 
                                alignItems: 'center', 
                                cursor: estaDeshabilitada ? 'not-allowed' : 'pointer',
                                margin: 0,
                                opacity: estaDeshabilitada ? 0.6 : 1
                              }}>
                                <input
                                  type="checkbox"
                                  checked={cuotasSeleccionadas.includes(cuota.numero)}
                                  onChange={() => handleToggleCuota(cuota.numero)}
                                  disabled={estaDeshabilitada}
                                  style={{ marginRight: '0.5rem' }}
                                />
                                <span style={{ flex: 1 }}>
                                  {cuota.pagada && '[Pagada] '}
                                  {(hayPendientesAnteriores || hayHuecoEnSeleccion) && !cuota.pagada && '[!] '}
                                  <strong>Cuota {cuota.numero}</strong> de {cuotasKardex.length} - {cuota.fecha.toLocaleDateString('es-PE')} - {formatCurrency(cuota.monto)}
                                  {cuota.pagada ? (
                                    <span style={{ color: '#28a745', marginLeft: '0.5rem' }}>(Pagada)</span>
                                  ) : hayPendientesAnteriores ? (
                                    <span style={{ color: '#ff6b6b', marginLeft: '0.5rem' }}>(Pagar cuotas anteriores primero)</span>
                                  ) : hayHuecoEnSeleccion ? (
                                    <span style={{ color: '#ff6b6b', marginLeft: '0.5rem' }}>(Seleccionar cuotas consecutivas)</span>
                                  ) : cuota.montoPagado > 0 ? (
                                    <span style={{ color: '#ff9800', marginLeft: '0.5rem' }}>
                                      (Parcial: {formatCurrency(cuota.montoPagado)} / {cuota.porcentajePagado.toFixed(0)}%)
                                    </span>
                                  ) : null}
                                </span>
                              </label>
                            </div>
                          );
                        })
                      )}
                      {cuotasSeleccionadas.length > 0 && (
                        <div style={{ 
                          marginTop: '0.75rem', 
                          padding: '0.5rem', 
                          background: '#d1ecf1', 
                          borderRadius: '4px',
                          border: '1px solid #bee5eb'
                        }}>
                          <strong>Seleccionadas:</strong> {cuotasSeleccionadas.length} cuota{cuotasSeleccionadas.length !== 1 ? 's' : ''} 
                          ({cuotasSeleccionadas.join(', ')})
                        </div>
                      )}
                    </div>
                  ) : (
                    // Mostrar select normal para otros tipos
                    <select 
                      className="form-input" 
                      value={formData.referencia_pago} 
                      onChange={(e) => {
                        const referencia = e.target.value;
                        
                        // Si es tipo "cuota_completa", calcular el monto pendiente de la cuota seleccionada
                        if (formData.tipo_pago === 'cuota_completa' && referencia) {
                          // Extraer el número de cuota de la referencia (ej: "CUOTA-3-4" -> 3)
                          const match = referencia.match(/CUOTA-(\d+)-\d+/);
                          if (match) {
                            const numCuota = parseInt(match[1], 10);
                            const cuotaSeleccionada = cuotasKardex.find(c => c.numero === numCuota);
                            
                            if (cuotaSeleccionada) {
                              // Calcular cuánto falta por pagar de esta cuota
                              const montoPendiente = cuotaSeleccionada.monto - cuotaSeleccionada.montoPagado;
                              setFormData({ 
                                ...formData, 
                                referencia_pago: referencia,
                                monto_pagado: montoPendiente.toFixed(2)
                              });
                              return;
                            }
                          }
                        }
                        
                        // Para otros casos, solo actualizar la referencia
                        setFormData({ ...formData, referencia_pago: referencia });
                      }} 
                      required={formData.tipo_pago !== 'personalizado' && formData.tipo_pago !== 'pago_completo'}
                      disabled={cuotasKardex.length === 0 || formData.tipo_pago === 'personalizado' || formData.tipo_pago === 'pago_completo'}
                      style={(formData.tipo_pago === 'personalizado' || formData.tipo_pago === 'pago_completo') ? { background: '#e9ecef', cursor: 'not-allowed' } : {}}
                    >
                      <option value="">Seleccionar cuota...</option>
                      {cuotasKardex.map((cuota, index) => {
                        // Encontrar la primera cuota no pagada completamente
                        const primeraCuotaPendiente = cuotasKardex.find(c => !c.pagada);
                        const numeroPrimeraPendiente = primeraCuotaPendiente ? primeraCuotaPendiente.numero : null;
                        
                        // Deshabilitar si:
                        // 1. La cuota ya está pagada
                        // 2. O si hay cuotas anteriores sin pagar (validación secuencial)
                        const hayPendientesAnteriores = numeroPrimeraPendiente && cuota.numero > numeroPrimeraPendiente;
                        const estaDeshabilitada = cuota.pagada || hayPendientesAnteriores;
                        
                        return (
                          <option 
                            key={cuota.numero} 
                            value={`CUOTA-${cuota.numero}-${cuotasKardex.length}`}
                            disabled={estaDeshabilitada}
                          >
                            Cuota {cuota.numero} de {cuotasKardex.length} - {cuota.fecha.toLocaleDateString('es-PE')} - S/ {cuota.monto.toFixed(2)}
                            {cuota.pagada ? ' (Pagada)' : 
                             hayPendientesAnteriores ? ' [!] (Pagar cuotas anteriores primero)' :
                             cuota.montoPagado > 0 ? ` (Parcial: S/ ${cuota.montoPagado.toFixed(2)} / ${cuota.porcentajePagado.toFixed(0)}%)` : ''}
                          </option>
                        );
                      })}
                    </select>
                  )}
                  
                  <small className="text-muted">
                    {formData.tipo_pago === 'parcial' && 'Selecciona la cuota a la que se aplicará el pago parcial'}
                    {formData.tipo_pago === 'multiple' && 'Selecciona las cuotas consecutivas que deseas pagar (empezando desde la primera pendiente). El monto se calculará automáticamente.'}
                    {formData.tipo_pago === 'personalizado' && 'No es necesario seleccionar cuota. El sistema aplicará el monto automáticamente a las cuotas pendientes en orden.'}
                    {formData.tipo_pago === 'pago_completo' && 'Se pagarán todas las cuotas pendientes automáticamente. No es necesario seleccionar.'}
                  </small>
                </div>
              </div>
              
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => { setShowModal(false); resetForm(); }}>
                  Cancelar
                </button>
                <button type="submit" className="btn btn-primary" disabled={createMutation.isLoading}>
                  {createMutation.isLoading ? (
                    <>
                      <span className="spinner-border spinner-border-sm me-2" role="status" />
                      Guardando...
                    </>
                  ) : 'Registrar'}
                </button>
              </div>
            </form>
          </div>
        </div>,
        document.body
      )}

      {/* Modal Detalle Pago */}
      {showDetail && pagoDetalle && createPortal(
        <div className="modal-overlay" onClick={() => { setShowDetail(false); setPagoDetalle(null); }}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '800px' }}>
            <div className="modal-header">
              <h2>Detalle de Pago #{pagoDetalle.id_pago}</h2>
              <button type="button" className="modal-close" onClick={() => { setShowDetail(false); setPagoDetalle(null); }}>
                <X size={24} />
              </button>
            </div>
            <div className="modal-body">
              {/* Información principal del pago */}
              <div style={{ 
                background: '#f8f9fa', 
                padding: '1rem', 
                borderRadius: '8px', 
                marginBottom: '1.5rem',
                border: '1px solid #dee2e6'
              }}>
                <div className="row">
                  <div className="col-md-6 mb-2">
                    <strong>Cliente:</strong><br/>
                    {`${pagoDetalle.nombres || ''} ${pagoDetalle.apellido_paterno || ''} ${pagoDetalle.apellido_materno || ''}`}
                  </div>
                  <div className="col-md-3 mb-2">
                    <strong>Crédito:</strong><br/>
                    #{pagoDetalle.id_credito}
                  </div>
                  <div className="col-md-3 mb-2">
                    <strong>Fecha:</strong><br/>
                    {formatearFecha(pagoDetalle.fecha_pago)}
                  </div>
                </div>
                
                {/* Resumen financiero */}
                <div style={{ 
                  marginTop: '1rem', 
                  paddingTop: '1rem', 
                  borderTop: '1px solid #dee2e6' 
                }}>
                  <div className="row">
                    <div className="col-md-3 mb-2">
                      <small className="text-muted">Monto Pagado</small><br/>
                      <strong style={{ fontSize: '1.1rem', color: '#28a745' }}>
                        {formatCurrency(pagoDetalle.monto_pagado)}
                      </strong>
                    </div>
                    {pagoDetalle.descuento > 0 && (
                      <div className="col-md-3 mb-2">
                        <small className="text-muted">Descuento Aplicado</small><br/>
                        <strong style={{ fontSize: '1.1rem', color: '#17a2b8' }}>
                          {formatCurrency(pagoDetalle.descuento)}
                        </strong>
                      </div>
                    )}
                    <div className="col-md-3 mb-2">
                      <small className="text-muted">Capital</small><br/>
                      <strong>{formatCurrency(pagoDetalle.capital_pagado || 0)}</strong>
                    </div>
                    <div className="col-md-3 mb-2">
                      <small className="text-muted">Interés</small><br/>
                      <strong>{formatCurrency(pagoDetalle.interes_pagado || 0)}</strong>
                    </div>
                    {pagoDetalle.mora_pagada > 0 && (
                      <div className="col-md-3 mb-2">
                        <small className="text-muted">Mora</small><br/>
                        <strong style={{ color: '#dc3545' }}>
                          {formatCurrency(pagoDetalle.mora_pagada)}
                        </strong>
                      </div>
                    )}
                  </div>
                </div>
                
                {/* Referencia */}
                {pagoDetalle.referencia_pago && (
                  <div style={{ marginTop: '0.75rem' }}>
                    <small className="text-muted">Referencia:</small> 
                    <span style={{ marginLeft: '0.5rem', fontFamily: 'monospace', fontSize: '0.9rem' }}>
                      {pagoDetalle.referencia_pago}
                    </span>
                  </div>
                )}
              </div>
              
              {/* Tabla de detalles por cuota */}
              {(pagoDetalle.detalles && pagoDetalle.detalles.length > 0) && (
                <>
                  <h6 style={{ marginBottom: '1rem', color: '#495057' }}>Distribución por Cuota</h6>
                  <div className="table-responsive">
                    <table className="table table-sm table-striped">
                      <thead className="table-dark">
                        <tr>
                          <th>Cuota</th>
                          <th>Fecha Programada</th>
                          <th>Monto Aplicado</th>
                          <th>Capital</th>
                          <th>Interés</th>
                          <th>Mora</th>
                        </tr>
                      </thead>
                      <tbody>
                        {pagoDetalle.detalles.map((d, idx) => (
                          <tr key={idx}>
                            <td>{d.numero_cuota || '-'}</td>
                            <td>{d.fecha_programada ? new Date(d.fecha_programada).toLocaleDateString() : '-'}</td>
                            <td>{formatCurrency(d.monto_aplicado)}</td>
                            <td>{formatCurrency(d.capital_aplicado)}</td>
                            <td>{formatCurrency(d.interes_aplicado)}</td>
                            <td>{formatCurrency(d.mora_aplicada)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </>
              )}
              
              {(!pagoDetalle.detalles || pagoDetalle.detalles.length === 0) && (
                <div style={{ 
                  textAlign: 'center', 
                  padding: '2rem', 
                  color: '#6c757d',
                  background: '#f8f9fa',
                  borderRadius: '8px'
                }}>
                  <p className="mb-0">Este pago no tiene detalles de distribución por cuota.</p>
                  <small>El sistema funciona con referencias de pago.</small>
                </div>
              )}
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={() => { setShowDetail(false); setPagoDetalle(null); }}>
                Cerrar
              </button>
            </div>
          </div>
        </div>,
        document.body
      )}
    </div>
  );
};

export default PagosPage;

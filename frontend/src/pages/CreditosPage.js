import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { 
  Plus, 
  Search, 
  Edit, 
  FileText,
  CheckCircle,
  XCircle,
  RefreshCw,
  Trash2,
  FileCheck
} from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { useLanguage } from '../contexts/LanguageContext';
import { creditsService, clientsService, catalogService, peopleService, reprogramacionesService } from '../services/api';
import { useAutoRefresh } from '../hooks/useAutoRefresh';
import KardexModal from '../components/creditos/KardexModal';
import CreditFormModal from '../components/creditos/CreditFormModal';
import ReprogramacionModal from '../components/creditos/ReprogramacionModal';

// Parámetros configurables del frontend (con valores por defecto)
const MAX_FIADORES = Number(process.env.REACT_APP_MAX_FIADORES || 3);
const MAX_AVALES = Number(process.env.REACT_APP_MAX_AVALES || 3);
const DNI_LENGTH = Number(process.env.REACT_APP_DNI_LENGTH || 8);

const CreditosPage = () => {
  const { hasPermission } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();
  const [showModal, setShowModal] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [filterStatus, setFilterStatus] = useState('Todos');
  const [editingCredito, setEditingCredito] = useState(null);
  const [showKardex, setShowKardex] = useState(false);
  const [selectedCreditoForKardex, setSelectedCreditoForKardex] = useState(null);
  const [showReprogramacion, setShowReprogramacion] = useState(false);
  const [selectedCreditoForReprog, setSelectedCreditoForReprog] = useState(null);
  const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);

  // Auto-refresh cada 20 segundos
  useAutoRefresh(() => {
    queryClient.invalidateQueries(['creditos']);
  }, 20000, autoRefreshEnabled);

  const [formData, setFormData] = useState({
    // Para creación
    id_cliente: '',
    monto_original: '',
    monto_aprobado: '',
    tasa_interes: '10',
    plazos_meses: '',
    periodo_pago: 'Mensual',
    // Extras opcionales (texto simple para parsear)
    seguros_ids_text: '', // ej: 1,2,3
    fiadores_dnis_text: '', // ej: 12345678,87654321
    avales_dnis_text: '',
    // Para edición rápida de estado/mora
    estado_credito: 'Pendiente',
    mora: 0,
    observaciones: ''
  });

  // Autocompletado personas (fiadores/avales)
  const [fiadorQuery, setFiadorQuery] = useState('');
  const [avalQuery, setAvalQuery] = useState('');
  const [fiadorResults, setFiadorResults] = useState([]);
  const [avalResults, setAvalResults] = useState([]);
  const [selectedFiadores, setSelectedFiadores] = useState([]); // [{dni, nombres, ...}]
  const [selectedAvales, setSelectedAvales] = useState([]);

  // Obtener créditos (lista) con auto-refresh cada 30 segundos
  const { data: creditos = [], isLoading } = useQuery({
    queryKey: ['creditos'],
    queryFn: async () => {
      const res = await creditsService.getAll();
      return res?.data?.creditos || res?.creditos || [];
    },
    refetchInterval: 30000, // Refrescar cada 30 segundos
    refetchIntervalInBackground: true, // Continuar refrescando incluso si la pestaña está en segundo plano
    staleTime: 0, // Considerar datos obsoletos inmediatamente
    cacheTime: 1000 * 60 * 5 // Cache por 5 minutos
  });

  // Catálogo de seguros (para gerentes)
  const { data: segurosCatalog = [] } = useQuery({
    queryKey: ['catalog-seguros'],
    queryFn: async () => {
      const res = await catalogService.getSeguros({ limit: 200 });
      return res?.data?.data || res?.data || res?.items || [];
    },
    enabled: hasPermission('gerente')
  });

  // Obtener clientes para el selector (solo activos)
  const { data: clientes = [] } = useQuery({
    queryKey: ['clientes-activos'],
    queryFn: async () => {
      const res = await clientsService.getAll();
      const clientesList = res?.data?.clientes || res?.data || res?.clientes || [];
      // Filtrar solo clientes activos
      return clientesList.filter(cliente => cliente.estado_cliente === 'Activo' || cliente.estado === 'Activo');
    }
  });

  // Obtener trabajadores disponibles (solo para gerente) - trabajadores que NO son clientes
  const { data: trabajadoresDisponibles = [] } = useQuery({
    queryKey: ['trabajadores-disponibles-credito'],
    queryFn: async () => {
      const [personasRes, clientesRes] = await Promise.all([
        peopleService.getAll(),
        clientsService.getAll()
      ]);
      
      const personas = personasRes?.data || personasRes;
      const personasList = Array.isArray(personas) ? personas : personas?.data || personas?.personas || [];
      
      const clientesList = clientesRes?.data?.clientes || clientesRes?.data || clientesRes?.clientes || [];
      const dniClientes = clientesList.map(c => c.dni);
      
      // Filtrar solo trabajadores (id_rol = 3) que no sean clientes
      return personasList.filter(p => 
        (p.rol_usuario === 3 || p.id_rol === 3) && 
        !dniClientes.includes(p.dni)
      );
    },
    enabled: hasPermission('gerente') // Solo ejecutar si es gerente
  });

  // Crear crédito
  const createCreditoMutation = useMutation({
    mutationFn: async (newCredito) => {
      return await creditsService.create(newCredito);
    },
    onSuccess: async (response) => {
      // Invalidar y refrescar automáticamente
      await queryClient.invalidateQueries(['creditos']);
      await queryClient.refetchQueries(['creditos']);
      setShowModal(false);
      resetForm();
      // Mostrar mensaje según el estado del crédito creado
      const mensaje = response?.message || response?.data?.message || 'Crédito creado exitosamente';
      alert(mensaje);
    },
    onError: (error) => {
      alert('Error al crear crédito: ' + (error.message || '')); 
    }
  });

  // Actualizar estado/mora del crédito
  const updateCreditoMutation = useMutation({
    mutationFn: async ({ id_credito, payload }) => {
      return await creditsService.update(id_credito, payload);
    },
    onSuccess: async () => {
      // Invalidar y refrescar automáticamente
      await queryClient.invalidateQueries(['creditos']);
      await queryClient.refetchQueries(['creditos']);
      setShowModal(false);
      resetForm();
      alert('Crédito actualizado exitosamente');
    },
    onError: (error) => {
      alert('Error al actualizar crédito: ' + (error.message || ''));
    }
  });

  // Aprobar crédito (solo gerentes)
  const aprobarCreditoMutation = useMutation({
    mutationFn: async (id_credito) => {
      return await creditsService.aprobar(id_credito);
    },
    onSuccess: async () => {
      // Invalidar y refrescar automáticamente todas las queries relacionadas
      await queryClient.invalidateQueries(['creditos']);
      await queryClient.invalidateQueries(['creditos-aprobados']);
      await queryClient.refetchQueries(['creditos']);
      await queryClient.refetchQueries(['creditos-aprobados']);
      alert('Crédito aprobado exitosamente');
      // Forzar recarga completa de la página para asegurar que se vean los cambios
      window.location.reload();
    },
    onError: (error) => {
      alert('Error al aprobar crédito: ' + (error.message || ''));
    }
  });

  // Rechazar crédito (solo gerentes)
  const rechazarCreditoMutation = useMutation({
    mutationFn: async ({ id_credito, motivo }) => {
      return await creditsService.rechazar(id_credito, motivo);
    },
    onSuccess: async () => {
      // Invalidar y refrescar automáticamente todas las queries relacionadas
      await queryClient.invalidateQueries(['creditos']);
      await queryClient.invalidateQueries(['creditos-aprobados']);
      await queryClient.refetchQueries(['creditos']);
      await queryClient.refetchQueries(['creditos-aprobados']);
      alert('Crédito desaprobado');
      // Forzar recarga completa de la página para asegurar que se vean los cambios
      window.location.reload();
    },
    onError: (error) => {
      alert('Error al rechazar crédito: ' + (error.message || ''));
    }
  });

  // Solicitar reprogramación
  const solicitarReprogramacionMutation = useMutation({
    mutationFn: async (data) => {
      return await reprogramacionesService.create(data);
    },
    onSuccess: async (response) => {
      await queryClient.invalidateQueries(['creditos']);
      await queryClient.refetchQueries(['creditos']);
      setShowReprogramacion(false);
      setSelectedCreditoForReprog(null);
      // Mostrar el mensaje que viene del backend
      alert(response.message || 'Reprogramación procesada exitosamente');
    },
    onError: (error) => {
      alert('Error al solicitar reprogramación: ' + (error.message || ''));
    }
  });

  // Eliminar crédito (solo gerentes)
  const eliminarCreditoMutation = useMutation({
    mutationFn: async (id) => {
      return await creditsService.delete(id);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries(['creditos']);
      await queryClient.refetchQueries(['creditos']);
      alert('Crédito eliminado exitosamente');
    },
    onError: (error) => {
      alert('Error al eliminar crédito: ' + (error.message || ''));
    }
  });

  const formatCurrency = (n) => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(Number(n || 0));

  const resetForm = () => {
    setFormData({
      id_cliente: '',
      monto_original: '',
      monto_aprobado: '',
      tasa_interes: '10',
      plazos_meses: '',
      periodo_pago: 'Mensual',
      estado_credito: 'Pendiente',
      mora: 0,
      observaciones: ''
    });
    setEditingCredito(null);
  };

  const handleSubmit = (e) => {
    e.preventDefault();

    if (!editingCredito) {
      // Validaciones creación
      if (!formData.id_cliente || !formData.monto_original || !formData.plazos_meses) {
        alert(t('creditos.validaciones.campo_requerido'));
        return;
      }
      if (parseFloat(formData.monto_original) <= 0) {
        alert(t('creditos.validaciones.monto_invalido', { min: 1, max: 999999 }));
        return;
      }
      if (parseInt(formData.plazos_meses) <= 0) {
        alert(t('creditos.validaciones.plazo_invalido', { min: 1, max: 360 }));
        return;
      }

      // Parse extras opcionales
      const seguros = (formData.seguros_ids_text || '')
        .split(',')
        .map(s => s.trim())
        .filter(Boolean)
        .map(id => ({ id_seguro: Number(id) }))
        .filter(x => !isNaN(x.id_seguro));
      const normalizeDNI = (s) => (s || '').replace(/\D/g, '').trim();
      const isValidDNI = (s) => new RegExp(`^\\d{${DNI_LENGTH}}$`).test(s);

      const typedFiadoresNorm = (formData.fiadores_dnis_text || '')
        .split(',')
        .map(s => normalizeDNI(s))
        .filter(Boolean);
      const typedAvalesNorm = (formData.avales_dnis_text || '')
        .split(',')
        .map(s => normalizeDNI(s))
        .filter(Boolean);

      // Validar formato de DNI (8 dígitos)
      const invalidFi = typedFiadoresNorm.filter(d => !isValidDNI(d));
      const invalidAv = typedAvalesNorm.filter(d => !isValidDNI(d));
      if (invalidFi.length > 0) {
        alert(t('creditos.validaciones.dni_formato_invalido', { length: DNI_LENGTH }) + ': ' + invalidFi.join(', '));
        return;
      }
      if (invalidAv.length > 0) {
        alert(t('creditos.validaciones.dni_formato_invalido', { length: DNI_LENGTH }) + ': ' + invalidAv.join(', '));
        return;
      }

      const fiadoresSet = Array.from(new Set([
        ...typedFiadoresNorm,
        ...selectedFiadores.map(p => p.dni)
      ]));
      const avalesSet = Array.from(new Set([
        ...typedAvalesNorm,
        ...selectedAvales.map(p => p.dni)
      ]));

      if (fiadoresSet.length > MAX_FIADORES) {
        alert(t('creditos.validaciones.max_fiadores', { max: MAX_FIADORES }));
        return;
      }
      if (avalesSet.length > MAX_AVALES) {
        alert(t('creditos.validaciones.max_avales', { max: MAX_AVALES }));
        return;
      }

      // Validar que el cliente no sea fiador ni aval
      const selectedClient = clientes.find(c => String(c.id_cliente) === String(formData.id_cliente));
      const clientDni = normalizeDNI(selectedClient?.dni || '');
      if (clientDni) {
        if (fiadoresSet.includes(clientDni)) {
          alert(t('creditos.validaciones.cliente_como_fiador'));
          return;
        }
        if (avalesSet.includes(clientDni)) {
          alert(t('creditos.validaciones.cliente_como_aval'));
          return;
        }
      }

      const fiadores = fiadoresSet.map(dni => ({ id_persona: dni }));
      const avales = avalesSet.map(dni => ({ id_persona: dni }));

      // Validaciones de duplicados y cruces
      const fiadoresDNIs = fiadores.map(f=>f.id_persona);
      const avalesDNIs = avales.map(a=>a.id_persona);
      const hasDupFiadores = new Set(fiadoresDNIs).size !== fiadoresDNIs.length;
      const hasDupAvales = new Set(avalesDNIs).size !== avalesDNIs.length;
      const cross = fiadoresDNIs.filter(d=> avalesDNIs.includes(d));
      if (hasDupFiadores) {
        alert(t('creditos.validaciones.dni_duplicado_fiadores'));
        return;
      }
      if (hasDupAvales) {
        alert(t('creditos.validaciones.dni_duplicado_avales'));
        return;
      }
      if (cross.length > 0) {
        alert(t('creditos.validaciones.dni_en_ambos_roles') + ': ' + cross.join(', '));
        return;
      }

      const payload = {
        id_cliente: formData.id_cliente,
        monto_original: parseFloat(formData.monto_original),
        monto_aprobado: formData.monto_aprobado ? parseFloat(formData.monto_aprobado) : undefined,
        tasa_interes: formData.tasa_interes ? parseFloat(formData.tasa_interes) : 0,
        plazos_meses: parseInt(formData.plazos_meses, 10),
        periodo_pago: formData.periodo_pago || 'Mensual',
        ...(seguros.length > 0 ? { seguros } : {}),
        ...(fiadores.length > 0 ? { fiadores } : {}),
        ...(avales.length > 0 ? { avales } : {})
      };
      
      console.log('[SEND] Enviando crédito con periodo_pago:', payload.periodo_pago);
      
      createCreditoMutation.mutate(payload);
    } else {
      // Edición rápida: estado_credito y mora
      const payload = {};
      if (formData.estado_credito) payload.estado_credito = formData.estado_credito;
      if (formData.mora !== undefined && formData.mora !== null) payload.mora = parseFloat(formData.mora);
      updateCreditoMutation.mutate({ id_credito: editingCredito.id_credito, payload });
    }
  };

  const handleEdit = (credito) => {
    setEditingCredito(credito);
    setFormData({
      ...formData,
      estado_credito: credito.estado_credito,
      mora: credito.mora || 0,
      observaciones: credito.observaciones || ''
    });
  };

  const handleOpenKardex = (credito) => {
    setSelectedCreditoForKardex(credito);
    setShowKardex(true);
  };

  const generarContrato = async (credito) => {
    try {
      // Obtener datos del contrato desde el backend
      const response = await creditsService.getContrato(credito.id_credito);
      if (!response.success || !response.data) {
        alert('Error al obtener datos del contrato: ' + (response.message || 'Error desconocido'));
        return;
      }

      const datos = response.data;
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF('portrait', 'mm', 'a4');

      // Función auxiliar para calcular ancho de texto
      const getTextWidth = (text, fontSize = 10, font = 'helvetica') => {
        doc.setFont(font, 'normal');
        doc.setFontSize(fontSize);
        return doc.getTextWidth(text);
      };

      // Función auxiliar para convertir número a texto
      const numeroATexto = (num) => {
        if (num === 0) return 'cero';
        if (num < 0) return 'menos ' + numeroATexto(-num);
        
        const unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        const decenas = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        const especiales = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
        
        if (num < 10) return unidades[num];
        if (num < 20) return especiales[num - 10];
        if (num < 100) {
          const d = Math.floor(num / 10);
          const u = num % 10;
          if (u === 0) return decenas[d];
          if (d === 1) return 'dieci' + unidades[u];
          if (d === 2) return 'veinti' + unidades[u];
          return decenas[d] + ' y ' + unidades[u];
        }
        if (num < 1000) {
          const c = Math.floor(num / 100);
          const resto = num % 100;
          let texto = '';
          if (c === 1) {
            if (resto === 0) texto = 'cien';
            else texto = 'ciento';
          } else if (c === 5) texto = 'quinientos';
          else if (c === 7) texto = 'setecientos';
          else if (c === 9) texto = 'novecientos';
          else texto = unidades[c] + 'cientos';
          if (resto > 0) texto += ' ' + numeroATexto(resto);
          return texto;
        }
        if (num < 1000000) {
          const miles = Math.floor(num / 1000);
          const resto = num % 1000;
          let texto = '';
          if (miles === 1) texto = 'mil';
          else texto = numeroATexto(miles) + ' mil';
          if (resto > 0) {
            if (resto < 100) texto += ' ' + numeroATexto(resto);
            else texto += ' ' + numeroATexto(resto);
          }
          return texto;
        }
        // Para números muy grandes, devolver como string
        return num.toString();
      };

      // Función para formatear fecha
      const formatearFecha = (fechaStr) => {
        if (!fechaStr) return '';
        const fecha = new Date(fechaStr);
        const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 
                      'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        const dia = fecha.getDate();
        const mes = meses[fecha.getMonth()];
        const año = fecha.getFullYear();
        return `${dia} días del mes de ${mes} del ${año}`;
      };

      // ========== PRIMERA HOJA ==========
      let y = 25;

      // Título - centrado y en negrita
      doc.setFontSize(12);
      doc.setFont('helvetica', 'bold');
      doc.text('CONTRATO DE PRESTAMO DINERARIO CON RESPALDO DE TITULO VALOR', 105, y, { align: 'center' });
      y += 10;

      // Párrafo introductorio
      doc.setFontSize(10);
      doc.setFont('helvetica', 'normal');
      const nombreAcreedor = `${datos.acreedor.nombres} ${datos.acreedor.apellido_paterno} ${datos.acreedor.apellido_materno || ''}`.trim();
      const estadoCivilAcreedor = datos.acreedor.estado_civil || 'Soltero';
      const direccionAcreedor = datos.acreedor.direccion || 'Jr. Francisco Irazola Nro. 421';
      const distritoAcreedor = datos.acreedor.distrito || 'Satipo';
      const departamentoAcreedor = datos.acreedor.departamento || 'Junín';
      
      const nombreDeudor = `${datos.deudor.nombres} ${datos.deudor.apellido_paterno} ${datos.deudor.apellido_materno || ''}`.trim();
      const estadoCivilDeudor = datos.deudor.estado_civil || 'Soltero';
      const direccionDeudor = datos.deudor.direccion || '';
      const distritoDeudor = datos.deudor.distrito || 'Satipo';
      const departamentoDeudor = datos.deudor.departamento || 'Junín';
      
      // Texto introductorio con formato especial para EL ACREEDOR y EL DEUDOR
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(10);
      
      // Función auxiliar para escribir texto con palabras en negrita y justificado
      const escribirTextoConNegrita = (texto, x, yInicial, maxWidth, palabrasNegrita = ['EL ACREEDOR', 'EL DEUDOR']) => {
        let y = yInicial;
        let lines = doc.splitTextToSize(texto, maxWidth);
        
        for (let i = 0; i < lines.length; i++) {
          let line = lines[i];
          let xPos = x;
          
          // Si es la última línea, no justificar (es estándar en documentos legales)
          let esUltimaLinea = (i === lines.length - 1);
          
          // Buscar si la línea contiene alguna palabra en negrita
          let tieneNegrita = palabrasNegrita.some(palabra => line.includes(palabra));
          
          if (tieneNegrita) {
            // Dividir la línea por todas las palabras en negrita
            let regex = new RegExp(`(${palabrasNegrita.join('|')})`, 'g');
            let parts = line.split(regex).filter(p => p); // Filtrar strings vacíos
            
            // Reconstruir el texto dividiendo en palabras y marcando las negritas
            let palabras = [];
            for (let part of parts) {
              if (palabrasNegrita.includes(part)) {
                palabras.push({ texto: part, esNegrita: true });
              } else {
                // Dividir en palabras individuales preservando espacios
                let palabrasPart = part.split(/(\s+)/);
                for (let p of palabrasPart) {
                  if (p.trim()) {
                    palabras.push({ texto: p, esNegrita: false });
                  } else if (p) {
                    // Es un espacio, lo guardamos pero no lo contamos como palabra
                    palabras.push({ texto: p, esNegrita: false, esEspacio: true });
                  }
                }
              }
            }
            
            // Calcular ancho total de todas las palabras (sin espacios)
            let anchoTotal = 0;
            let numEspacios = 0;
            for (let palabra of palabras) {
              if (palabra.esEspacio) {
                numEspacios++;
                anchoTotal += getTextWidth(' ', 10, 'helvetica');
              } else {
                if (palabra.esNegrita) {
                  doc.setFont('helvetica', 'bold');
                } else {
                  doc.setFont('helvetica', 'normal');
                }
                anchoTotal += getTextWidth(palabra.texto, 10, 'helvetica');
              }
            }
            
            // Calcular espacio extra para justificar
            let espacioDisponible = maxWidth;
            let espacioExtra = espacioDisponible - anchoTotal;
            let espacioPorEspacio = (numEspacios > 0 && !esUltimaLinea) ? espacioExtra / numEspacios : 0;
            
            // Escribir cada elemento con el espaciado calculado
            for (let j = 0; j < palabras.length; j++) {
              let palabra = palabras[j];
              
              if (palabra.esEspacio) {
                // Espacio normal más espacio extra para justificar
                let anchoEspacio = getTextWidth(' ', 10, 'helvetica') + espacioPorEspacio;
                xPos += anchoEspacio;
              } else {
                // Escribir la palabra
                if (palabra.esNegrita) {
                  doc.setFont('helvetica', 'bold');
                } else {
                  doc.setFont('helvetica', 'normal');
                }
                doc.text(palabra.texto, xPos, y);
                xPos += getTextWidth(palabra.texto, 10, 'helvetica');
              }
            }
          } else {
            // Sin palabras en negrita, usar justificación nativa
            doc.setFont('helvetica', 'normal');
            if (esUltimaLinea) {
              doc.text(line, x, y, { maxWidth: maxWidth });
            } else {
              doc.text(line, x, y, { maxWidth: maxWidth, align: 'justify' });
            }
          }
          y += 5;
        }
        
        return y;
      };
      
      // Construir texto completo y escribirlo
      let introAcreedor = `Por el presente documento, EL ACREEDOR: Identificado como Sr. ${nombreAcreedor.toUpperCase()}, ${estadoCivilAcreedor.toLowerCase()}, con DNI Nro. ${datos.acreedor.dni}, residiendo en ${direccionAcreedor}, Distrito y Provincia de ${distritoAcreedor}, departamento de ${departamentoAcreedor}.`;
      y = escribirTextoConNegrita(introAcreedor, 10, y, 190);
      y += 3;
      
      // EL DEUDOR
      let deudorText = `EL DEUDOR: Identificado como Sr. ${nombreDeudor.toUpperCase()}, ${estadoCivilDeudor.toLowerCase()}`;
      if (direccionDeudor) {
        deudorText += `, con DNI Nro. ${datos.deudor.dni}, residiendo en ${direccionDeudor}, Distrito y Provincia de ${distritoDeudor}, departamento de ${departamentoDeudor}.`;
      } else {
        deudorText += `, con DNI Nro. ${datos.deudor.dni}, Distrito y Provincia de ${distritoDeudor}, departamento de ${departamentoDeudor}.`;
      }
      y = escribirTextoConNegrita(deudorText, 10, y, 190);
      y += 3;
      
      doc.setFont('helvetica', 'normal');
      doc.text('Convienen en celebrar el presente contrato de préstamo, sujeto a las siguientes cláusulas:', 10, y, { maxWidth: 190, align: 'justify' });
      y += 12;

      // PRIMERO - Monto del préstamo
      doc.setFont('helvetica', 'bold');
      doc.text('PRIMERO:', 10, y);
      doc.setFont('helvetica', 'normal');
      y += 6;
      
      const monto = datos.credito.monto;
      const montoTexto = numeroATexto(Math.floor(monto)).toUpperCase();
      
      // Texto con EL ACREEDOR y EL DEUDOR en negrita
      let textoPrimero = `EL ACREEDOR otorga a EL DEUDOR un préstamo de S/. ${monto.toFixed(2)} (${montoTexto} y 00/100 nuevos soles). El préstamo es entregado en efectivo y en su totalidad al momento de la firma del presente contrato, para ser invertido como capital de trabajo.`;
      y = escribirTextoConNegrita(textoPrimero, 10, y, 190);
      y += 3;
      
      let textoReconoce = 'EL DEUDOR reconoce haber recibido a su entera satisfacción, siendo prueba de ello su firma al final del presente documento.';
      y = escribirTextoConNegrita(textoReconoce, 10, y, 190);
      y += 3;
      
      doc.setFont('helvetica', 'normal');
      doc.text('Se deja constancia que para el desembolso no se utilizaron otros medios de pago, conforme a la ley Nro. 28194.', 10, y, { maxWidth: 190, align: 'justify' });
      y += 12;

      // SEGUNDO - Condiciones de pago
      doc.setFont('helvetica', 'bold');
      doc.text('SEGUNDO:', 10, y);
      doc.setFont('helvetica', 'normal');
      y += 6;
      
      const tasaInteres = datos.credito.tasa_interes;
      const totalCuotas = datos.credito.total_cuotas;
      
      let textoSegundo = `EL DEUDOR se compromete a pagar el préstamo bajo las siguientes condiciones:`;
      y = escribirTextoConNegrita(textoSegundo, 10, y, 190);
      y += 3;
      
      // Lista con viñetas
      doc.setFont('helvetica', 'normal');
      doc.text('•', 15, y);
      doc.text(`Tasa de interés ${tasaInteres.toFixed(0)}% (${numeroATexto(Math.floor(tasaInteres)).toLowerCase()} por ciento) efectivo mensual.`, 20, y, { maxWidth: 170, align: 'justify' });
      y += 5;
      doc.text('•', 15, y);
      doc.text(`Plazo ${totalCuotas} (${numeroATexto(totalCuotas).toLowerCase()}) cuotas.`, 20, y, { maxWidth: 170, align: 'justify' });
      y += 8;
      
      let textoMora = 'En caso de que EL DEUDOR no cancele las cuotas en las fechas indicadas en su kardex de préstamo, se le cobrará una mora de S/. 1.00 (Un y 00/100 nuevos soles) por cada día de atraso.';
      y = escribirTextoConNegrita(textoMora, 10, y, 190);
      y += 3;
      
      let textoIncurre = 'Si EL DEUDOR incurre en más de 08 (ocho) cuotas vencidas, EL ACREEDOR tiene derecho a exigir la cancelación total del saldo pendiente, más todos los gastos, costos y moras generadas hasta la fecha de exigencia.';
      y = escribirTextoConNegrita(textoIncurre, 10, y, 190);
      y += 3;
      
      let textoAutorizado = 'EL ACREEDOR queda autorizado para iniciar las acciones legales que considere convenientes.';
      y = escribirTextoConNegrita(textoAutorizado, 10, y, 190);
      y += 5;

      // TERCERO - Compromiso del deudor
      doc.setFont('helvetica', 'bold');
      doc.text('TERCERO:', 10, y);
      doc.setFont('helvetica', 'normal');
      y += 6;
      
      let textoTercero = `EL DEUDOR se compromete a cancelar y devolver el préstamo según lo detallado en el Kardex adjunto en la fecha indicada, con un plazo de ${totalCuotas} (${numeroATexto(totalCuotas).toLowerCase()}) cuotas que serán pagadas diariamente incluyendo intereses y capital, según el cronograma establecido.`;
      y = escribirTextoConNegrita(textoTercero, 10, y, 190);
      y += 15;

      // Responsable de cobranza (lado derecho)
      const responsableY = y - 15;
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(9);
      doc.text('RESPONSABLE DE COBRANZA:', 150, responsableY);
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(10);
      doc.text(datos.responsable_cobranza.nombre || 'Moises Mayta M.', 150, responsableY + 5);
      doc.text(`Telf. ${datos.responsable_cobranza.telefono} YAPE`, 150, responsableY + 10);

      // ========== SEGUNDA HOJA ==========
      doc.addPage();
      y = 25;

      // CUARTO
      doc.setFont('helvetica', 'bold');
      doc.text('CUARTO:', 10, y);
      doc.setFont('helvetica', 'normal');
      y += 6;
      
      let textoCuarto = 'EL ACREEDOR otorga a EL DEUDOR el préstamo representado mediante una letra de cambio aceptada por EL DEUDOR, pagadera a favor de EL ACREEDOR. Esta letra de cambio no sustituye la obligación original asumida por EL DEUDOR. Se deja establecido que la eventual extinción, deterioro, sustracción o pérdida del título valor, en ningún caso implicará la extinción de la obligación o de la garantía, manteniéndose la plena validez del préstamo hasta su cancelación. El presente documento, por su naturaleza y contenido ejecutivo, constituye título ejecutivo.';
      y = escribirTextoConNegrita(textoCuarto, 10, y, 190);
      y += 5;

      // QUINTO
      doc.setFont('helvetica', 'bold');
      doc.text('QUINTO:', 10, y);
      doc.setFont('helvetica', 'normal');
      y += 6;
      
      let textoQuinto = 'En caso de incumplimiento del pago, EL ACREEDOR tiene derecho a ejecutar el título valor, incluyendo sus respectivos intereses y moras, por constituir el documento una obligación de pago.';
      y = escribirTextoConNegrita(textoQuinto, 10, y, 190);
      y += 5;

      // SEXTO
      doc.setFont('helvetica', 'bold');
      doc.text('SEXTO:', 10, y);
      doc.setFont('helvetica', 'normal');
      y += 6;
      
      const fechaFirma = formatearFecha(datos.credito.fecha_otorgamiento);
      let textoSexto = `EL ACREEDOR y EL DEUDOR declaran expresamente haber leído, entendido y ratificado la totalidad del contenido del presente contrato, firmándolo de su puño y letra en el Distrito de ${distritoAcreedor} el ${fechaFirma}.`;
      y = escribirTextoConNegrita(textoSexto, 10, y, 190);
      y += 20;

      // Firmas - lado izquierdo (ACREEDOR)
      const firmaY = y;
      
      // Línea para firma del acreedor
      doc.setLineWidth(0.5);
      doc.line(10, firmaY, 90, firmaY);
      y = firmaY + 6;
      
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(9);
      doc.text(nombreAcreedor.toUpperCase(), 10, y);
      y += 5;
      doc.text(`DNI ${datos.acreedor.dni}`, 10, y);
      y += 5;
      doc.setFont('helvetica', 'bold');
      doc.text('ACREEDOR', 10, y);

      // Firmas - lado derecho (EL DEUDOR)
      y = firmaY;
      
      // Línea para firma del deudor
      doc.line(120, y, 200, y);
      y = firmaY + 6;
      
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(9);
      doc.text(nombreDeudor.toUpperCase(), 120, y);
      y += 5;
      doc.text(`DNI ${datos.deudor.dni}`, 120, y);
      y += 5;
      doc.setFont('helvetica', 'bold');
      doc.text('EL DEUDOR', 120, y);

      // Guardar PDF
      doc.save(`Contrato_Credito_${credito.id_credito}_${new Date().getTime()}.pdf`);
      
    } catch (error) {
      console.error('Error al generar contrato:', error);
      alert('Error al generar el contrato: ' + (error.message || 'Error desconocido'));
    }
  };

  const handleAprobarCredito = (credito) => {
    if (!window.confirm(`¿Aprobar el crédito de ${credito.nombres} ${credito.apellido_paterno} por ${formatCurrency(credito.monto_original)}?`)) {
      return;
    }
    aprobarCreditoMutation.mutate(credito.id_credito);
  };

  const handleRechazarCredito = (credito) => {
    const motivo = window.prompt('Ingrese el motivo del rechazo:');
    if (!motivo || motivo.trim() === '') {
      alert('Debe ingresar un motivo para rechazar el crédito');
      return;
    }
    rechazarCreditoMutation.mutate({ id_credito: credito.id_credito, motivo });
  };

  const handleEliminarCredito = (credito) => {
    if (!window.confirm(`[!] ADVERTENCIA: ¿Está seguro de eliminar/cancelar el crédito de ${credito.nombres} ${credito.apellido_paterno}?\n\nNOTA:\n- Si el crédito tiene pagos registrados, se cambiará su estado a "Cancelado" (no se eliminará)\n- Si el crédito NO tiene pagos, se eliminará permanentemente\n\n¿Desea continuar?`)) {
      return;
    }
    eliminarCreditoMutation.mutate(credito.id_credito);
  };

  const handleReprogramar = (credito) => {
    setSelectedCreditoForReprog(credito);
    setShowReprogramacion(true);
  };

  const handleSubmitReprogramacion = (data) => {
    solicitarReprogramacionMutation.mutate(data);
  };

  const renderKardexButton = (credito) => {
    // Solo mostrar el botón de kardex si el crédito está aprobado
    if (credito.estado_credito !== 'Aprobado') {
      return null;
    }
    
    return (
      <button
        onClick={(e) => {
          e.stopPropagation();
          handleOpenKardex(credito);
        }}
        className="btn btn-sm btn-outline-info"
        title="Ver Kardex de Pago"
      >
        <FileText size={16} />
      </button>
    );
  };

  const generarSolicitud = async (credito) => {
    try {
      const response = await creditsService.getSolicitud(credito.id_credito);
      if (!response.success || !response.data) {
        alert('Error al obtener datos de la solicitud: ' + (response.message || 'Error desconocido'));
        return;
      }

      const datos = response.data;
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF('portrait', 'mm', 'a4');

      // Función auxiliar para formatear fecha
      const formatearFecha = (fechaStr) => {
        if (!fechaStr) return '';
        const fecha = new Date(fechaStr);
        const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 
                      'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return `${fecha.getDate()} días del mes de ${meses[fecha.getMonth()]} del ${fecha.getFullYear()}`;
      };

      // Función auxiliar para formatear moneda
      const formatearMoneda = (valor) => {
        if (!valor || valor === 0) return '';
        return valor.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      };

      // Función auxiliar para dibujar una celda con borde
      const dibujarCelda = (x, y, ancho, alto, texto, alineacion = 'left', fontSize = 9, bold = false) => {
        doc.setFontSize(fontSize);
        doc.setFont('helvetica', bold ? 'bold' : 'normal');
        doc.rect(x, y, ancho, alto);
        const padding = 2;
        let textX = x + padding;
        if (alineacion === 'center') {
          textX = x + ancho / 2;
        } else if (alineacion === 'right') {
          textX = x + ancho - padding;
        }
        doc.text(texto || '', textX, y + alto / 2 + 2, { align: alineacion, maxWidth: ancho - padding * 2 });
      };

      let y = 10;

      // Título
      doc.setFontSize(14);
      doc.setFont('helvetica', 'bold');
      doc.text('SOLICITUD DE CREDITO', 105, y, { align: 'center' });
      
      // Código (arriba a la derecha)
      doc.setFontSize(10);
      doc.setFont('helvetica', 'normal');
      doc.text('COD.', 175, y - 3);
      doc.setFont('helvetica', 'bold');
      doc.text(datos.codigo, 200, y - 3, { align: 'right' });
      
      y += 8;

      // ========== SECCIÓN EL CREDITO ==========
      doc.setFontSize(11);
      doc.setFont('helvetica', 'bold');
      doc.text('EL CREDITO', 10, y);
      y += 6;

      const anchoColumna1 = 50;
      const anchoColumna2 = 50;
      const anchoColumna3 = 50;
      const anchoColumna4 = 40;
      const altoFila = 6;
      
      // Fila 1
      dibujarCelda(10, y, anchoColumna1, altoFila, 'MONTO SOLICITADO', 'left', 9, false);
      dibujarCelda(60, y, anchoColumna2, altoFila, `S/ ${formatearMoneda(datos.credito.monto_solicitado)}`, 'left', 9, false);
      dibujarCelda(110, y, anchoColumna3, altoFila, 'NRO. CRDTO.', 'left', 9, false);
      dibujarCelda(160, y, anchoColumna4, altoFila, String(datos.credito.numero_credito), 'left', 9, false);
      y += altoFila;
      
      // Fila 2
      dibujarCelda(10, y, anchoColumna1, altoFila, 'TIPO DE CREDITO', 'left', 9, false);
      dibujarCelda(60, y, anchoColumna2, altoFila, datos.credito.tipo_credito || 'GASTOS PERSONALES', 'left', 9, false);
      dibujarCelda(110, y, anchoColumna3, altoFila, 'INTERES', 'left', 9, false);
      dibujarCelda(160, y, anchoColumna4, altoFila, `${datos.credito.interes}%`, 'left', 9, false);
      y += altoFila;
      
      // Fila 3
      const fechaSolicitud = datos.credito.fecha_solicitud ? 
        new Date(datos.credito.fecha_solicitud).toLocaleDateString('es-PE') : '';
      dibujarCelda(10, y, anchoColumna1, altoFila, 'FECHA DE SOLICITUD', 'left', 9, false);
      dibujarCelda(60, y, anchoColumna2, altoFila, fechaSolicitud, 'left', 9, false);
      dibujarCelda(110, y, anchoColumna3, altoFila, 'CUOTA S/', 'left', 9, false);
      dibujarCelda(160, y, anchoColumna4, altoFila, formatearMoneda(datos.credito.cuota), 'left', 9, false);
      y += altoFila;
      
      // Fila 4
      dibujarCelda(10, y, anchoColumna1, altoFila, 'PLAZO', 'left', 9, false);
      dibujarCelda(60, y, anchoColumna2, altoFila, `${datos.credito.plazo} Cuotas`, 'left', 9, false);
      dibujarCelda(110, y, anchoColumna3, altoFila, 'CONDICION', 'left', 9, false);
      dibujarCelda(160, y, anchoColumna4, altoFila, datos.credito.condicion || 'REPRESTAMO', 'left', 9, false);
      y += altoFila + 3;

      // ========== SECCIÓN EL CLIENTE ==========
      doc.setFontSize(11);
      doc.setFont('helvetica', 'bold');
      doc.text('EL CLIENTE', 10, y);
      y += 6;

      // Nombre completo en celda grande
      const nombreCompleto = `${datos.cliente.apellido_paterno || ''} ${datos.cliente.apellido_materno || ''} ${datos.cliente.nombres || ''}`.trim().toUpperCase();
      dibujarCelda(10, y, 190, altoFila, nombreCompleto, 'left', 9, false);
      y += altoFila;
      
      // Fila con DNI y otros datos
      dibujarCelda(10, y, 30, altoFila, 'DNI:', 'left', 9, false);
      dibujarCelda(40, y, 50, altoFila, datos.cliente.dni || '', 'left', 9, false);
      dibujarCelda(90, y, 40, altoFila, 'ESTADO CIVIL:', 'left', 9, false);
      dibujarCelda(130, y, 70, altoFila, (datos.cliente.estado_civil || 'SOLTERO').toUpperCase(), 'left', 9, false);
      y += altoFila;
      
      // Dirección
      dibujarCelda(10, y, 40, altoFila, 'DIRECCION:', 'left', 9, false);
      dibujarCelda(50, y, 140, altoFila, datos.cliente.direccion || '', 'left', 9, false);
      y += altoFila;
      
      // Ocupación/Trabajo
      dibujarCelda(10, y, 50, altoFila, 'OCUPACION/TRABAJO:', 'left', 9, false);
      dibujarCelda(60, y, 130, altoFila, datos.cliente.ocupacion || '', 'left', 9, false);
      y += altoFila;
      
      // Teléfono, Ocupación, Vivienda, Edad
      dibujarCelda(10, y, 40, altoFila, 'TELEFONOS:', 'left', 9, false);
      dibujarCelda(50, y, 50, altoFila, datos.cliente.telefono || '', 'left', 9, false);
      dibujarCelda(100, y, 40, altoFila, 'OCUPACION:', 'left', 9, false);
      dibujarCelda(140, y, 50, altoFila, (datos.cliente.ocupacion || '').toUpperCase(), 'left', 9, false);
      y += altoFila;
      
      dibujarCelda(10, y, 40, altoFila, 'VIVIENDA:', 'left', 9, false);
      dibujarCelda(50, y, 50, altoFila, (datos.cliente.tipo_vivienda || 'PROPIA').toUpperCase(), 'left', 9, false);
      dibujarCelda(100, y, 40, altoFila, 'EDAD:', 'left', 9, false);
      dibujarCelda(140, y, 50, altoFila, datos.cliente.edad ? String(datos.cliente.edad) : '', 'left', 9, false);
      y += altoFila + 3;

      // Referencial del cónyuge
      if (datos.cliente.estado_civil && (datos.cliente.estado_civil.toUpperCase() === 'CASADO' || datos.cliente.estado_civil.toUpperCase() === 'CONVIVIENTE')) {
        doc.setFontSize(9);
        doc.setFont('helvetica', 'bold');
        doc.text('REFERENCIAL DEL CONYUGE:', 10, y);
        y += 5;
        
        dibujarCelda(10, y, 40, altoFila, 'APELLIDOS:', 'left', 9, false);
        dibujarCelda(50, y, 50, altoFila, datos.conyuge ? `${datos.conyuge.apellido_paterno || ''} ${datos.conyuge.apellido_materno || ''}`.trim() : '', 'left', 9, false);
        dibujarCelda(100, y, 40, altoFila, 'TELEFONO:', 'left', 9, false);
        dibujarCelda(140, y, 50, altoFila, datos.conyuge?.telefono || '', 'left', 9, false);
        y += altoFila;
        
        dibujarCelda(10, y, 40, altoFila, 'OCUPACION:', 'left', 9, false);
        dibujarCelda(50, y, 140, altoFila, datos.conyuge?.ocupacion || '', 'left', 9, false);
        y += altoFila + 3;
      }

      // ========== SECCIÓN EL NEGOCIO ==========
      doc.setFontSize(11);
      doc.setFont('helvetica', 'bold');
      doc.text('EL NEGOCIO', 10, y);
      y += 6;

      dibujarCelda(10, y, 50, altoFila, 'TIPO DE NEGOCIO:', 'left', 9, false);
      dibujarCelda(60, y, 50, altoFila, (datos.negocio.tipo || '').toUpperCase(), 'left', 9, false);
      dibujarCelda(110, y, 40, altoFila, 'PROPIO', 'left', 9, false);
      dibujarCelda(150, y, 50, altoFila, '', 'left', 9, false);
      y += altoFila;
      
      dibujarCelda(10, y, 40, altoFila, 'DIRECCION:', 'left', 9, false);
      dibujarCelda(50, y, 150, altoFila, datos.negocio.direccion || '', 'left', 9, false);
      y += altoFila;
      
      dibujarCelda(10, y, 30, altoFila, 'PISO:', 'left', 9, false);
      dibujarCelda(40, y, 30, altoFila, '1', 'left', 9, false);
      dibujarCelda(70, y, 40, altoFila, 'EMPLEADOS:', 'left', 9, false);
      dibujarCelda(110, y, 30, altoFila, datos.negocio.empleados ? String(datos.negocio.empleados) : '', 'left', 9, false);
      dibujarCelda(140, y, 30, altoFila, 'RUC:', 'left', 9, false);
      dibujarCelda(170, y, 30, altoFila, datos.negocio.ruc || '', 'left', 9, false);
      y += altoFila + 3;

      // ========== REFERENCIAL DE INGRESOS DEL NEGOCIO ==========
      doc.setFontSize(11);
      doc.setFont('helvetica', 'bold');
      doc.text('REFERENCIAL DE INGRESOS DEL NEGOCIO', 10, y);
      y += 6;

      dibujarCelda(10, y, 50, altoFila, 'INVENTARIOS:', 'left', 9, false);
      dibujarCelda(60, y, 40, altoFila, formatearMoneda(datos.referencial_ingresos.inventarios), 'left', 9, false);
      dibujarCelda(100, y, 60, altoFila, 'VENTAS DIARIAS \\ SEMANAL S/', 'left', 9, false);
      const ventas = datos.referencial_ingresos.ventas_diarias || datos.referencial_ingresos.ventas_semanales || 0;
      dibujarCelda(160, y, 40, altoFila, formatearMoneda(ventas), 'left', 9, false);
      y += altoFila;
      
      dibujarCelda(10, y, 50, altoFila, 'TOTAL DE ACTIVOS S/', 'left', 9, false);
      dibujarCelda(60, y, 40, altoFila, formatearMoneda(datos.referencial_ingresos.total_activos), 'left', 9, false);
      dibujarCelda(100, y, 30, altoFila, 'CAJA S/', 'left', 9, false);
      dibujarCelda(130, y, 70, altoFila, formatearMoneda(datos.referencial_ingresos.caja), 'left', 9, false);
      y += altoFila;
      
      dibujarCelda(10, y, 50, altoFila, 'OTROS NEGOCIOS:', 'left', 9, false);
      dibujarCelda(60, y, 140, altoFila, datos.referencial_ingresos.otros_negocios || '', 'left', 9, false);
      y += altoFila;
      
      dibujarCelda(10, y, 50, altoFila, 'DESCRIPCION:', 'left', 9, false);
      dibujarCelda(60, y, 140, altoFila, datos.referencial_ingresos.descripcion || '', 'left', 9, false);
      y += altoFila + 3;

      // ========== REFERENCIAL FINANCIERA ==========
      doc.setFontSize(11);
      doc.setFont('helvetica', 'bold');
      doc.text('REFERENCIAL FINANCIERA', 10, y);
      y += 6;

      dibujarCelda(10, y, 80, altoFila, 'TOTAL DE ENTIDADES FINANCIERAS S/', 'left', 9, false);
      dibujarCelda(90, y, 50, altoFila, formatearMoneda(datos.referencial_financiera.total_entidades), 'left', 9, false);
      dibujarCelda(140, y, 60, altoFila, '', 'left', 9, false);
      y += altoFila;
      
      dibujarCelda(10, y, 50, altoFila, 'PRESTAMISTAS S/', 'left', 9, false);
      dibujarCelda(60, y, 50, altoFila, formatearMoneda(datos.referencial_financiera.prestamistas), 'left', 9, false);
      dibujarCelda(110, y, 40, altoFila, 'TERCEROS:', 'left', 9, false);
      dibujarCelda(150, y, 50, altoFila, formatearMoneda(datos.referencial_financiera.terceros), 'left', 9, false);
      y += altoFila + 3;

      // ========== TEXTO LEGAL ==========
      doc.setFontSize(8);
      doc.setFont('helvetica', 'italic');
      const textoLegal = 'La presente solicitud está sujeta a estudio y verificación de los datos allí consignados, cualquier anomalía o inconsistencia se anulará. Así mismo, no compromete al Empleado la aprobación directa y se reserva el derecho de aceptación o no del crédito.';
      doc.text(textoLegal, 10, y, { maxWidth: 190, align: 'justify' });
      y += 12;

      // ========== FIRMAS ==========
      // Firma del solicitante (izquierda)
      doc.setFontSize(9);
      doc.setFont('helvetica', 'normal');
      doc.line(10, y, 70, y);
      doc.text('FIRMA DEL SOLICITANTE', 10, y + 5);
      doc.text(`DNI: ${datos.cliente.dni}`, 10, y + 10);
      
      // Firma del cónyuge (centro)
      if (datos.cliente.estado_civil && (datos.cliente.estado_civil.toUpperCase() === 'CASADO' || datos.cliente.estado_civil.toUpperCase() === 'CONVIVIENTE')) {
        doc.line(80, y, 140, y);
        doc.text('FIRMA DEL CONYUGE', 80, y + 5);
        doc.text('DNI:', 80, y + 10);
      }
      
      y += 15;

      // ========== INFORMACIÓN DEL CODEUDOR ==========
      if (datos.codeudor) {
        doc.setFontSize(11);
        doc.setFont('helvetica', 'bold');
        doc.text('INFORMACION DEL CODEUDOR', 10, y);
        y += 6;

        dibujarCelda(10, y, 40, altoFila, 'APELLIDOS:', 'left', 9, false);
        const nombreCodeudor = `${datos.codeudor.apellido_paterno || ''} ${datos.codeudor.apellido_materno || ''} ${datos.codeudor.nombres || ''}`.trim().toUpperCase();
        dibujarCelda(50, y, 150, altoFila, nombreCodeudor, 'left', 9, false);
        y += altoFila + 3;

        doc.setFontSize(8);
        doc.setFont('helvetica', 'italic');
        const textoCodeudor = 'En caso de incumplimiento de la presente solicitud por parte del deudor, el codeudor es también un deudor que, por voluntad propia asume ante la ley las mismas responsabilidades y obligaciones del deudor titular del crédito.';
        doc.text(textoCodeudor, 10, y, { maxWidth: 190, align: 'justify' });
        y += 10;

        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.line(10, y, 70, y);
        doc.text('FIRMA DEL CODEUDOR', 10, y + 5);
        doc.text(`DNI: ${datos.codeudor.dni || ''}`, 10, y + 10);
      }

      // Guardar PDF
      doc.save(`Solicitud_Credito_${datos.codigo}_${new Date().getTime()}.pdf`);
      
    } catch (error) {
      console.error('Error al generar solicitud:', error);
      alert('Error al generar la solicitud: ' + (error.message || 'Error desconocido'));
    }
  };

  const renderContratoButton = (credito) => {
    // Solo mostrar el botón de contrato si el crédito está aprobado
    if (credito.estado_credito !== 'Aprobado') {
      return null;
    }
    
    return (
      <button
        onClick={(e) => {
          e.stopPropagation();
          generarContrato(credito);
        }}
        className="btn btn-sm btn-outline-success"
        title="Generar Contrato"
      >
        <FileCheck size={16} />
      </button>
    );
  };

  const renderSolicitudButton = (credito) => {
    // Mostrar el botón de solicitud para todos los créditos
    return (
      <button
        onClick={(e) => {
          e.stopPropagation();
          generarSolicitud(credito);
        }}
        className="btn btn-sm btn-outline-primary"
        title="Generar Solicitud de Crédito"
      >
        <FileText size={16} />
      </button>
    );
  };

  // Filtro local de créditos por búsqueda y estado
  const filteredCreditos = React.useMemo(() => {
    const term = (searchTerm || '').toLowerCase().trim();
    const filtered = (creditos || []).filter((c) => {
      const nombre = `${c.nombres || ''} ${c.apellido_paterno || ''} ${c.apellido_materno || ''}`.toLowerCase();
      const dni = String(c.dni || '');
      const matchText = !term || nombre.includes(term) || dni.includes(term) || String(c.id_credito).includes(term);
      const matchEstado = filterStatus === 'Todos' || c.estado_credito === filterStatus;
      return matchText && matchEstado;
    });
    
    // Ordenar por ID descendente (más recientes primero)
    return filtered.sort((a, b) => (b.id_credito || 0) - (a.id_credito || 0));
  }, [creditos, searchTerm, filterStatus]);

  const renderEstadoBadge = (estado) => {
    const map = {
      'Pendiente': 'warning',
      'Aprobado': 'success',
      'Desaprobado': 'danger',
      'En Mora': 'danger',
      'Cancelado': 'dark',
      'Pagado': 'info'
    };
    const cls = map[estado] || 'secondary';
    return <span className={`badge bg-${cls}`}>{estado}</span>;
  };

  return (
    <div className="container-fluid">
      <div className="row">
        <div className="col-12">
          <div className="card creditos-card">
            <div className="card-header creditos-header d-flex flex-wrap align-items-center justify-content-between gap-2">
              <h5 className="mb-0">Créditos</h5>
              <div className="d-flex flex-wrap gap-2 creditos-controls">
                <div className="input-group">
                  <span className="input-group-text"><Search size={16} /></span>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="Buscar por nombre, DNI o ID"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                  />
                </div>
                <select className="form-select" value={filterStatus} onChange={(e) => setFilterStatus(e.target.value)}>
                  <option value="Todos">Todos</option>
                  <option value="Pendiente">Pendiente</option>
                  <option value="Aprobado">Aprobado</option>
                  <option value="Desaprobado">Desaprobado</option>
                  <option value="En Mora">En Mora</option>
                  <option value="Cancelado">Cancelado</option>
                  <option value="Pagado">Pagado</option>
                </select>
                <button className="btn btn-primary creditos-new-btn" onClick={() => { setShowModal(true); setEditingCredito(null); }}>
                  <Plus size={16} className="me-1" /> Nuevo
                </button>
              </div>
            </div>

            <div className="card-body p-0">
              {isLoading ? (
                <div className="p-4 text-center">Cargando créditos...</div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-hover align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Monto (S/)</th>
                        <th>Tasa (%)</th>
                        <th>Plazo</th>
                        <th>Periodo de Pago</th>
                        <th>Otorgado</th>
                        <th>Vencimiento</th>
                        <th>Por Pagar</th>
                        <th>Próxima Cuota</th>
                        <th>Estado</th>
                        <th style={{width:120}}>Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {filteredCreditos.length === 0 ? (
                        <tr>
                          <td colSpan="13" className="text-center p-4">Sin resultados</td>
                        </tr>
                      ) : (
                        filteredCreditos.map((c) => (
                          <tr key={c.id_credito}>
                            <td>{c.id_credito}</td>
                            <td>{`${c.nombres || ''} ${c.apellido_paterno || ''} ${c.apellido_materno || ''}`}</td>
                            <td>{formatCurrency(c.monto_aprobado ?? c.monto_original)}</td>
                            <td>{Number(c.tasa_interes || 0).toFixed(2)}%</td>
                            <td>{c.plazos_meses} {c.plazos_meses === 1 ? 'mes' : 'meses'}</td>
                            <td>{c.periodo_pago || 'Mensual'}</td>
                            <td>{c.fecha_otorgamiento ? new Date(c.fecha_otorgamiento + 'T12:00:00').toLocaleDateString('es-PE') : '-'}</td>
                            <td>{c.fecha_vencimiento ? new Date(c.fecha_vencimiento + 'T12:00:00').toLocaleDateString('es-PE') : '-'}</td>
                            <td>
                              {(() => {
                                if (c.estado_credito === 'Pagado') {
                                  return <span className="text-success">Pagado</span>;
                                }
                                if (c.estado_credito === 'Cancelado') {
                                  return <span className="text-danger">Cancelado</span>;
                                }
                                if (c.estado_credito === 'Pendiente') {
                                  return <span className="text-warning">Pendiente aprobación</span>;
                                }
                                if (c.estado_credito === 'Desaprobado') {
                                  return <span className="text-danger">Desaprobado</span>;
                                }
                                if (c.estado_credito === 'En Mora') {
                                  return <span className="text-danger">En Mora</span>;
                                }
                                const saldo = Number(c.saldo_pendiente ?? 0);
                                return saldo > 0 ? formatCurrency(saldo) : <span className="text-muted">Sin saldo</span>;
                              })()}
                            </td>
                            <td>
                              {(() => {
                                if (c.estado_credito === 'Pagado') {
                                  return <span className="text-success">Completado</span>;
                                }
                                if (c.estado_credito === 'Cancelado') {
                                  return <span className="text-danger">Cancelado</span>;
                                }
                                if (c.estado_credito === 'Pendiente') {
                                  return <span className="text-warning">Pendiente</span>;
                                }
                                if (c.estado_credito === 'Desaprobado') {
                                  return <span className="text-danger">Desaprobado</span>;
                                }
                                if (c.estado_credito === 'En Mora') {
                                  return <span className="text-danger">En Mora</span>;
                                }
                                return c.proxima_cuota_fecha ? new Date(c.proxima_cuota_fecha).toLocaleDateString('es-PE') : <span className="text-muted">Sin cuotas</span>;
                              })()}
                            </td>
                            <td>{renderEstadoBadge(c.estado_credito)}</td>
                            <td>
                              <div className="d-flex gap-1">
                                {/* Botones de aprobar/rechazar solo para gerentes y créditos pendientes */}
                                {hasPermission('gerente') && c.estado_credito === 'Pendiente' ? (
                                  <>
                                    <button
                                      className="btn btn-sm btn-success"
                                      title="Aprobar crédito"
                                      onClick={(e) => { e.stopPropagation(); handleAprobarCredito(c); }}
                                      disabled={aprobarCreditoMutation.isLoading}
                                    >
                                      <CheckCircle size={16} />
                                    </button>
                                    <button
                                      className="btn btn-sm btn-danger"
                                      title="Rechazar crédito"
                                      onClick={(e) => { e.stopPropagation(); handleRechazarCredito(c); }}
                                      disabled={rechazarCreditoMutation.isLoading}
                                    >
                                      <XCircle size={16} />
                                    </button>
                                  </>
                                ) : (
                                  <>
                                    {/* Botón de editar solo para gerentes */}
                                    {hasPermission('gerente') && (
                                      <button
                                        className="btn btn-sm btn-outline-primary"
                                        title="Editar"
                                        onClick={(e) => { e.stopPropagation(); handleEdit(c); setShowModal(true); }}
                                      >
                                        <Edit size={16} />
                                      </button>
                                    )}
                                    {renderKardexButton(c)}
                                    {renderSolicitudButton(c)}
                                    {renderContratoButton(c)}
                                    {(hasPermission('trabajador') || hasPermission('gerente')) && c.estado_credito === 'Aprobado' && (
                                      <button
                                        className="btn btn-sm btn-outline-warning"
                                        title="Reprogramar"
                                        onClick={(e) => { e.stopPropagation(); handleReprogramar(c); }}
                                      >
                                        <RefreshCw size={16} />
                                      </button>
                                    )}
                                    {/* Botón de eliminar solo para gerentes */}
                                    {hasPermission('gerente') && (
                                      <button
                                        className="btn btn-sm btn-outline-danger"
                                        title="Eliminar crédito permanentemente"
                                        onClick={(e) => { e.stopPropagation(); handleEliminarCredito(c); }}
                                        disabled={eliminarCreditoMutation.isLoading}
                                      >
                                        <Trash2 size={16} />
                                      </button>
                                    )}
                                  </>
                                )}
                              </div>
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            {/* Modal de Kardex */}
            {/* Modal de Kardex */}
            {showKardex && selectedCreditoForKardex && (
              <KardexModal
                isOpen={showKardex}
                onClose={() => setShowKardex(false)}
                credito={selectedCreditoForKardex}
              />
            )}

            {/* Modal de Reprogramación */}
            {showReprogramacion && selectedCreditoForReprog && (
              <ReprogramacionModal
                isOpen={showReprogramacion}
                onClose={() => { setShowReprogramacion(false); setSelectedCreditoForReprog(null); }}
                credito={selectedCreditoForReprog}
                onSubmit={handleSubmitReprogramacion}
                isLoading={solicitarReprogramacionMutation.isLoading}
              />
            )}

            {/* Modal Crear/Editar Crédito */}
            <CreditFormModal
              show={showModal}
              onClose={() => { setShowModal(false); resetForm(); }}
              onSubmit={handleSubmit}
              formData={formData}
              setFormData={setFormData}
              editingCredito={editingCredito}
              clientes={clientes}
              trabajadoresDisponibles={trabajadoresDisponibles}
              segurosCatalog={segurosCatalog}
              hasPermission={hasPermission}
              isLoading={createCreditoMutation.isLoading || updateCreditoMutation.isLoading}
              t={t}
              fiadorQuery={fiadorQuery}
              setFiadorQuery={setFiadorQuery}
              fiadorResults={fiadorResults}
              setFiadorResults={setFiadorResults}
              selectedFiadores={selectedFiadores}
              setSelectedFiadores={setSelectedFiadores}
              avalQuery={avalQuery}
              setAvalQuery={setAvalQuery}
              avalResults={avalResults}
              setAvalResults={setAvalResults}
              selectedAvales={selectedAvales}
              setSelectedAvales={setSelectedAvales}
              peopleService={peopleService}
            />
          </div>
        </div>
      </div>
    </div>
  );
};

export default CreditosPage;

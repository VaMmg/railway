import React, { useState, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { 
  Users, 
  Plus, 
  Search, 
  Eye,
  User,
  CreditCard,
  X,
  Phone,
  Mail,
  MapPin,
  DollarSign,
  UserPlus,
  Trash2,
  CheckCircle
} from 'lucide-react';
import { clientsService, peopleService } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import toast from 'react-hot-toast';
import './ClientesPage.css';

const ClientesPage = () => {
  const { hasPermission } = useAuth();
  const queryClient = useQueryClient();
  
  const [showClientModal, setShowClientModal] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [filters, setFilters] = useState({ estado: '' });
  
  // Formulario unificado para crear cliente (incluye datos de persona)
  const [clientFormData, setClientFormData] = useState({
    // Datos de persona
    dni: '', 
    nombres: '', 
    apellido_paterno: '', 
    apellido_materno: '',
    sexo: 'M', 
    fecha_nacimiento: '', 
    nacionalidad: 'Peruana',
    estado_civil: 'Soltero', 
    telefono: '', 
    correo: '', 
    direccion: '',
    // Datos de cliente
    ingreso_mensual: '', 
    gasto_mensual: '',
    tipo_contrato: 'Indefinido', 
    antiguedad_laboral: 0
  });
  
  const [clientErrors, setClientErrors] = useState({});
  const [showClientDetailModal, setShowClientDetailModal] = useState(false);
  const [clienteDetalle, setClienteDetalle] = useState(null);

  const { data: clientesData, isLoading: loadingClientes } = useQuery({
    queryKey: ['clientes'],
    queryFn: async () => {
      const res = await clientsService.getAll();
      return res?.data || res;
    }
  });

  const { data: personasData, isLoading: loadingPersonas } = useQuery({
    queryKey: ['personas'],
    queryFn: async () => {
      const res = await peopleService.getAll();
      return res?.data || res;
    }
  });

  const clientes = useMemo(() => {
    let data = [];
    if (Array.isArray(clientesData)) data = clientesData;
    else data = clientesData?.clientes || clientesData?.data || [];
    
    // Ordenar por ID descendente (más recientes primero)
    return [...data].sort((a, b) => (b.id_cliente || 0) - (a.id_cliente || 0));
  }, [clientesData]);

  const personas = useMemo(() => {
    let baseList;

    if (Array.isArray(personasData)) {
      baseList = personasData;
    } else if (Array.isArray(personasData?.data)) {
      baseList = personasData.data;
    } else if (Array.isArray(personasData?.data?.data)) {
      baseList = personasData.data.data;
    } else if (Array.isArray(personasData?.personas)) {
      baseList = personasData.personas;
    } else {
      baseList = [];
    }

    return baseList.filter((persona) => persona?.rol_usuario !== 2);
  }, [personasData]);

  const createClientMutation = useMutation({
    mutationFn: async (payload) => clientsService.create(payload),
    onSuccess: async () => {
      // Invalidar y refrescar automáticamente las listas
      await queryClient.invalidateQueries(['clientes']);
      await queryClient.invalidateQueries(['clientes-activos']);
      await queryClient.invalidateQueries(['personas']);
      await queryClient.refetchQueries(['clientes']);
      await queryClient.refetchQueries(['clientes-activos']);
      await queryClient.refetchQueries(['personas']);
      
      setShowClientModal(false);
      resetClientForm();
      toast.success('Cliente creado exitosamente');
    },
    onError: (e) => {
      const errorMsg = e.response?.data?.error || e.message || 'Error al crear cliente';
      toast.error(errorMsg);
      
      // Si es error de duplicado, actualizar también el error del formulario
      if (errorMsg.includes('Duplicate') || errorMsg.includes('duplicado')) {
        setClientErrors({ dni: 'Este DNI ya está registrado como cliente' });
      }
    }
  });

  const deleteClientMutation = useMutation({
    mutationFn: async (idCliente) => clientsService.delete(idCliente),
    onSuccess: async (response) => {
      // Invalidar y refrescar automáticamente ambas cachés
      await queryClient.invalidateQueries(['clientes']);
      await queryClient.invalidateQueries(['clientes-activos']);
      await queryClient.refetchQueries(['clientes']);
      await queryClient.refetchQueries(['clientes-activos']);
      toast.success(response?.message || 'Cliente desactivado');
    },
    onError: (e) => {
      const message = e.message || e?.data?.message || 'Error al desactivar cliente';
      toast.error(message);
    }
  });

  const activateClientMutation = useMutation({
    mutationFn: async (idCliente) => clientsService.update(idCliente, { estado: 'Activo' }),
    onSuccess: async (response) => {
      // Invalidar y refrescar automáticamente ambas cachés
      await queryClient.invalidateQueries(['clientes']);
      await queryClient.invalidateQueries(['clientes-activos']);
      await queryClient.refetchQueries(['clientes']);
      await queryClient.refetchQueries(['clientes-activos']);
      toast.success(response?.message || 'Cliente activado exitosamente');
    },
    onError: (e) => {
      const message = e.message || e?.data?.message || 'Error al activar cliente';
      toast.error(message);
    }
  });

  const detalleClienteMutation = useMutation({
    mutationFn: async (idCliente) => clientsService.getById(idCliente),
    onSuccess: (response) => {
      const detalle = response?.data || response;
      setClienteDetalle(detalle);
      setShowClientDetailModal(true);
    },
    onError: (e) => {
      toast.error(e.message || 'Error al obtener detalles del cliente');
    }
  });

  const formatCurrency = (amount) => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(amount || 0);
  const formatDate = (date) => new Date(date).toLocaleDateString('es-ES');
  
  const resetClientForm = () => {
    setClientFormData({
      // Datos de persona
      dni: '', 
      nombres: '', 
      apellido_paterno: '', 
      apellido_materno: '',
      sexo: 'M', 
      fecha_nacimiento: '', 
      nacionalidad: 'Peruana',
      estado_civil: 'Soltero', 
      telefono: '', 
      correo: '', 
      direccion: '',
      // Datos de cliente
      ingreso_mensual: '', 
      gasto_mensual: '',
      tipo_contrato: 'Indefinido', 
      antiguedad_laboral: 0
    });
    setClientErrors({});
  };

  const openClientModal = () => { resetClientForm(); setShowClientModal(true); };

  // Capitalizar cada palabra (primera letra en mayúscula)
  const capitalizarPalabras = (texto) => {
    return texto
      .split(' ')
      .map(palabra => palabra.charAt(0).toUpperCase() + palabra.slice(1).toLowerCase())
      .join(' ');
  };

  // Calcular edad desde fecha de nacimiento
  const calcularEdad = (fechaNacimiento) => {
    if (!fechaNacimiento) return null;
    const hoy = new Date();
    const nacimiento = new Date(fechaNacimiento);
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const mes = hoy.getMonth() - nacimiento.getMonth();
    if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
      edad--;
    }
    return edad;
  };

  const validateClientForm = () => {
    const newErrors = {};
    
    // Validar DNI
    if (!clientFormData.dni.trim()) {
      newErrors.dni = 'DNI es requerido';
    } else if (!/^\d{8}$/.test(clientFormData.dni)) {
      newErrors.dni = 'DNI debe tener 8 dígitos';
    } else {
      // Verificar si el DNI ya está registrado como cliente
      const existeCliente = clientes.some(c => c.dni === clientFormData.dni);
      if (existeCliente) {
        newErrors.dni = 'Este DNI ya está registrado como cliente';
      }
    }
    
    // Validar nombres
    if (!clientFormData.nombres.trim()) {
      newErrors.nombres = 'Nombres son requeridos';
    } else {
      const palabras = clientFormData.nombres.trim().split(/\s+/);
      if (palabras.length > 5) newErrors.nombres = 'Máximo 5 nombres permitidos';
    }
    
    // Validar apellido paterno
    if (!clientFormData.apellido_paterno.trim()) {
      newErrors.apellido_paterno = 'Apellido paterno es requerido';
    }
    
    // Validar apellido materno (opcional) - sin restricciones adicionales
    
    // Validar edad
    if (clientFormData.fecha_nacimiento) {
      const edad = calcularEdad(clientFormData.fecha_nacimiento);
      if (edad !== null && edad < 18) {
        newErrors.fecha_nacimiento = 'Debe ser mayor de edad (18 años)';
      }
    }
    
    // Validar teléfono
    if (clientFormData.telefono && !/^9\d{8}$/.test(clientFormData.telefono)) {
      newErrors.telefono = 'El celular debe iniciar con 9 y tener 9 dígitos';
    }
    
    // Validar correo
    if (clientFormData.correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(clientFormData.correo)) {
      newErrors.correo = 'Formato de correo inválido';
    }
    
    // Validar ingreso mensual
    if (!clientFormData.ingreso_mensual || Number(clientFormData.ingreso_mensual) <= 0) {
      newErrors.ingreso_mensual = 'Ingreso mensual debe ser mayor a 0';
    }
    
    setClientErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleClientSubmit = (e) => {
    e.preventDefault();
    
    // Validar formulario
    if (!validateClientForm()) {
      toast.error('Por favor corrige los errores en el formulario');
      return;
    }
    
    // Enviar todos los datos (persona + cliente) en una sola operación
    createClientMutation.mutate({
      // Datos de persona
      dni: clientFormData.dni.trim(),
      nombres: clientFormData.nombres.trim(),
      apellido_paterno: clientFormData.apellido_paterno.trim(),
      apellido_materno: (clientFormData.apellido_materno || '').trim(),
      sexo: clientFormData.sexo,
      fecha_nacimiento: clientFormData.fecha_nacimiento || null,
      nacionalidad: clientFormData.nacionalidad.trim(),
      estado_civil: clientFormData.estado_civil.trim(),
      telefono: (clientFormData.telefono || '').trim(),
      correo: (clientFormData.correo || '').trim(),
      direccion: (clientFormData.direccion || '').trim(),
      // Datos de cliente
      ingreso_mensual: Number(clientFormData.ingreso_mensual),
      gasto_mensual: Number(clientFormData.gasto_mensual) || 0,
      tipo_contrato: clientFormData.tipo_contrato,
      antiguedad_laboral: Number(clientFormData.antiguedad_laboral) || 0
    });
  };

  const handleDeleteCliente = (cliente) => {
    if (!cliente?.id_cliente) return;
    if (!window.confirm(`¿Desactivar al cliente "${cliente.nombres || ''} ${cliente.apellido_paterno || ''}"?`)) {
      return;
    }
    deleteClientMutation.mutate(cliente.id_cliente);
  };

  const handleActivateCliente = (cliente) => {
    if (!cliente?.id_cliente) return;
    if (!window.confirm(`¿Activar al cliente "${cliente.nombres || ''} ${cliente.apellido_paterno || ''}"?`)) {
      return;
    }
    activateClientMutation.mutate(cliente.id_cliente);
  };

  const handleViewCliente = (cliente) => {
    if (!cliente?.id_cliente) {
      toast.error('Cliente no válido');
      return;
    }
    detalleClienteMutation.mutate(cliente.id_cliente);
  };

  const closeClientDetailModal = () => {
    setShowClientDetailModal(false);
    setClienteDetalle(null);
  };

  const handleClientChange = (e) => {
    const { name, value } = e.target;
    let newValue = value;
    
    // Validaciones específicas por campo
    if (name === 'dni') {
      newValue = value.replace(/\D/g, '').slice(0, 8);
    } else if (name === 'telefono') {
      newValue = value.replace(/\D/g, '').slice(0, 9);
    } else if (name === 'nombres') {
      // Capitalizar automáticamente y limitar a 5 nombres
      const palabras = value.trim().split(/\s+/);
      if (palabras.length <= 5) {
        newValue = capitalizarPalabras(value);
      } else {
        return; // No permitir más de 5 nombres
      }
    } else if (name === 'apellido_paterno' || name === 'apellido_materno') {
      // Capitalizar automáticamente (permite múltiples palabras como "De La Cruz")
      newValue = capitalizarPalabras(value);
    } else if (name === 'ingreso_mensual' || name === 'gasto_mensual') {
      const v = value.replace(',', '.').replace(/[^0-9.]/g, '');
      const parts = v.split('.');
      newValue = parts.shift() + (parts.length > 0 ? '.' + parts.join('') : '');
    } else if (name === 'antiguedad_laboral') {
      newValue = value.replace(/\D/g, '');
    }
    
    setClientFormData({ ...clientFormData, [name]: newValue });
    if (clientErrors[name]) setClientErrors({ ...clientErrors, [name]: '' });
  };

  const filtered = clientes.filter((c) => {
    const q = searchTerm.toLowerCase();
    const matchesSearch = (c.nombres || '').toLowerCase().includes(q) || (c.apellido_paterno || '').toLowerCase().includes(q) || (c.dni || '').toLowerCase().includes(q);
    const matchesEstado = !filters.estado || c.estado === filters.estado;
    return matchesSearch && matchesEstado;
  });

  return (
    <div className="container-fluid">
      <div className="row">
        <div className="col-12">
          <div className="card">
            <div className="card-header">
              <div className="d-flex justify-content-between align-items-center">
                <h5 className="card-title mb-0">
                  <Users className="me-2" /> Gestión de Clientes
                </h5>
                {hasPermission('trabajador') && (
                  <div className="d-flex gap-2">
                    <button className="btn btn-success" onClick={openClientModal}>
                      <UserPlus size={18} className="me-2" /> Nuevo Cliente
                    </button>
                  </div>
                )}
              </div>
            </div>
            <div className="card-body">
              <div className="clientes-toolbar">
                <div className="clientes-search">
                  <Search size={18} className="clientes-search-icon" />
                  <input
                    type="text"
                    className="clientes-search-input"
                    placeholder="Buscar por nombre o DNI..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                  />
                </div>
                <div className="clientes-filter">
                  <select
                    value={filters.estado}
                    onChange={(e) => setFilters({ ...filters, estado: e.target.value })}
                    className="clientes-filter-select"
                  >
                    <option value="">Todos los estados</option>
                    <option value="Activo">Activos</option>
                    <option value="Inactivo">Inactivos</option>
                  </select>
                </div>
              </div>

              <div className="table-responsive">
                <table className="table table-striped table-hover">
                  <thead className="table-dark">
                    <tr>
                      <th>DNI</th>
                      <th>Nombre Completo</th>
                      <th>Contacto</th>
                      <th>Ingresos</th>
                      <th>Estado</th>
                      <th>Fecha Registro</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {loadingClientes ? (
                      <tr><td colSpan="7" className="text-center">Cargando...</td></tr>
                    ) : filtered.length ? (
                      filtered.map((cliente) => (
                        <tr key={cliente.id_cliente}>
                          <td>{cliente.dni}</td>
                          <td>{`${cliente.nombres || ''} ${cliente.apellido_paterno || ''} ${cliente.apellido_materno || ''}`}</td>
                          <td>
                            <div className="text-sm">
                              {cliente.numero_principal && (<div className="d-flex align-items-center gap-1"><Phone size={14} />{cliente.numero_principal}</div>)}
                            </div>
                          </td>
                          <td>{formatCurrency(cliente.ingreso_mensual)}</td>
                          <td><span className={`badge ${cliente.estado === 'Activo' ? 'bg-success' : 'bg-secondary'}`}>{cliente.estado}</span></td>
                          <td>{formatDate(cliente.fecha_registro)}</td>
                          <td>
                            <div className="btn-group btn-group-sm" role="group">
                              <button
                                className="btn btn-secondary"
                                title="Ver detalles"
                                onClick={() => handleViewCliente(cliente)}
                                disabled={detalleClienteMutation.isLoading}
                              >
                                {detalleClienteMutation.isLoading ? (
                                  <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                ) : (
                                  <Eye size={14} />
                                )}
                              </button>
                              {cliente.estado === 'Inactivo' ? (
                                <button
                                  className="btn btn-outline-success"
                                  title="Activar cliente"
                                  onClick={() => handleActivateCliente(cliente)}
                                  disabled={activateClientMutation.isLoading}
                                >
                                  <CheckCircle size={14} />
                                </button>
                              ) : (
                                <button
                                  className="btn btn-outline-danger"
                                  title="Desactivar cliente"
                                  onClick={() => handleDeleteCliente(cliente)}
                                  disabled={deleteClientMutation.isLoading}
                                >
                                  <Trash2 size={14} />
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr><td colSpan="7" className="text-center text-muted">No se encontraron clientes</td></tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      {showClientModal && createPortal(
        <div className="modal-overlay" onClick={() => { setShowClientModal(false); resetClientForm(); }}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '700px' }}>
            <div className="modal-header">
              <h2>Nuevo Cliente</h2>
              <button type="button" className="modal-close" onClick={() => { setShowClientModal(false); resetClientForm(); }}>
                <X size={24} />
              </button>
            </div>
            
            <form onSubmit={handleClientSubmit}>
              <div className="modal-body">
                <div className="form-section">
                  <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                    <User size={18} style={{ marginRight: '0.5rem' }} />
                    Información Personal
                  </h3>
                  
                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label">
                        <CreditCard size={16} className="inline-block mr-1" />
                        DNI *
                      </label>
                      <input 
                        name="dni" 
                        className={`form-input ${clientErrors.dni ? 'error' : ''}`} 
                        value={clientFormData.dni} 
                        onChange={handleClientChange} 
                        maxLength={8} 
                        required 
                        placeholder="Ingrese 8 dígitos"

                      />
                      {clientErrors.dni && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{clientErrors.dni}</small>}
                    </div>
                    <div className="form-group">
                      <label className="form-label"><User size={16} className="inline-block mr-1" />Nombres *</label>
                      <input name="nombres" className={`form-input ${clientErrors.nombres ? 'error' : ''}`} value={clientFormData.nombres} onChange={handleClientChange} required />
                      {clientErrors.nombres && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{clientErrors.nombres}</small>}
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label">Apellido Paterno *</label>
                      <input name="apellido_paterno" className={`form-input ${clientErrors.apellido_paterno ? 'error' : ''}`} value={clientFormData.apellido_paterno} onChange={handleClientChange} required />
                      {clientErrors.apellido_paterno && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{clientErrors.apellido_paterno}</small>}
                    </div>
                    <div className="form-group">
                      <label className="form-label">Apellido Materno</label>
                      <input name="apellido_materno" className={`form-input ${clientErrors.apellido_materno ? 'error' : ''}`} value={clientFormData.apellido_materno} onChange={handleClientChange} />
                      {clientErrors.apellido_materno && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{clientErrors.apellido_materno}</small>}
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label">Sexo</label>
                      <select name="sexo" className="form-input" value={clientFormData.sexo} onChange={handleClientChange}>
                        <option value="M">Masculino</option>
                        <option value="F">Femenino</option>
                      </select>
                    </div>
                    <div className="form-group">
                      <label className="form-label">Fecha de Nacimiento</label>
                      <input name="fecha_nacimiento" type="date" className={`form-input ${clientErrors.fecha_nacimiento ? 'error' : ''}`} value={clientFormData.fecha_nacimiento} onChange={handleClientChange} />
                      {clientErrors.fecha_nacimiento && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{clientErrors.fecha_nacimiento}</small>}
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label">Nacionalidad</label>
                      <input name="nacionalidad" className="form-input" value={clientFormData.nacionalidad} onChange={handleClientChange} />
                    </div>
                    <div className="form-group">
                      <label className="form-label">Estado Civil</label>
                      <select name="estado_civil" className="form-input" value={clientFormData.estado_civil} onChange={handleClientChange}>
                        <option value="Soltero">Soltero(a)</option>
                        <option value="Casado">Casado(a)</option>
                        <option value="Divorciado">Divorciado(a)</option>
                        <option value="Viudo">Viudo(a)</option>
                      </select>
                    </div>
                  </div>
                </div>

                <div className="form-section">
                  <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                    <Phone size={18} style={{ marginRight: '0.5rem' }} />
                    Información de Contacto
                  </h3>
                  
                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label"><Phone size={16} className="inline-block mr-1" />Celular</label>
                      <input name="telefono" className={`form-input ${clientErrors.telefono ? 'error' : ''}`} value={clientFormData.telefono} onChange={handleClientChange} maxLength={9} />
                      {clientErrors.telefono && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{clientErrors.telefono}</small>}
                    </div>
                    <div className="form-group">
                      <label className="form-label"><Mail size={16} className="inline-block mr-1" />Correo Electrónico</label>
                      <input name="correo" type="email" className={`form-input ${clientErrors.correo ? 'error' : ''}`} value={clientFormData.correo} onChange={handleClientChange} />
                      {clientErrors.correo && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{clientErrors.correo}</small>}
                    </div>
                  </div>

                  <div className="form-group">
                    <label className="form-label"><MapPin size={16} className="inline-block mr-1" />Dirección</label>
                    <textarea name="direccion" className="form-input" value={clientFormData.direccion} onChange={handleClientChange} rows={2} />
                  </div>
                </div>

                <div className="form-section">
                  <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                    <DollarSign size={18} style={{ marginRight: '0.5rem' }} />
                    Información Financiera
                  </h3>
                  
                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label"><DollarSign size={16} className="inline-block mr-1" />Ingreso Mensual (S/.) *</label>
                      <input name="ingreso_mensual" className={`form-input ${clientErrors.ingreso_mensual ? 'error' : ''}`} value={clientFormData.ingreso_mensual} onChange={handleClientChange} required placeholder="1500.00" />
                      {clientErrors.ingreso_mensual && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{clientErrors.ingreso_mensual}</small>}
                    </div>
                    <div className="form-group">
                      <label className="form-label">Gasto Mensual (S/.)</label>
                      <input name="gasto_mensual" className="form-input" value={clientFormData.gasto_mensual} onChange={handleClientChange} placeholder="800.00" />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label">Tipo de Contrato</label>
                      <select name="tipo_contrato" className="form-input" value={clientFormData.tipo_contrato} onChange={handleClientChange}>
                        <option value="Indefinido">Indefinido</option>
                        <option value="Temporal">Temporal</option>
                        <option value="Independiente">Independiente</option>
                      </select>
                    </div>
                    <div className="form-group">
                      <label className="form-label">Antigüedad Laboral (meses)</label>
                      <input name="antiguedad_laboral" className="form-input" value={clientFormData.antiguedad_laboral} onChange={handleClientChange} placeholder="12" />
                    </div>
                  </div>
                </div>
              </div>

              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => { setShowClientModal(false); resetClientForm(); }}>Cancelar</button>
                <button type="submit" className="btn btn-success" disabled={createClientMutation.isLoading}>
                  {createClientMutation.isLoading ? (<><span className="spinner-border spinner-border-sm me-2" role="status" />Creando...</>) : (<><UserPlus size={18} className="me-2" />Crear Cliente</>)}
                </button>
              </div>
            </form>
          </div>
        </div>,
        document.body
      )}

      {showClientDetailModal && createPortal(
        <div className="modal-overlay" onClick={closeClientDetailModal}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '600px' }}>
            <div className="modal-header">
              <h2>Detalle del Cliente</h2>
              <button type="button" className="modal-close" onClick={closeClientDetailModal}>
                <X size={24} />
              </button>
            </div>

            <div className="modal-body">
              <div className="form-section">
                <h3 style={{ fontSize: '1rem', fontWeight: 600, color: '#374151', marginBottom: '1rem' }}>
                  <User size={18} style={{ marginRight: '0.5rem' }} />
                  Información Personal
                </h3>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <strong>DNI:</strong>
                    <div>{clienteDetalle?.dni || 'No disponible'}</div>
                  </div>
                  <div>
                    <strong>Nombre completo:</strong>
                    <div>{`${clienteDetalle?.nombres || ''} ${clienteDetalle?.apellido_paterno || ''} ${clienteDetalle?.apellido_materno || ''}`.trim() || 'No disponible'}</div>
                  </div>
                  <div>
                    <strong>Sexo:</strong>
                    <div>{clienteDetalle?.sexo || 'No registrado'}</div>
                  </div>
                  <div>
                    <strong>Fecha nacimiento:</strong>
                    <div>{clienteDetalle?.fecha_nacimiento ? formatDate(clienteDetalle.fecha_nacimiento) : 'No registrada'}</div>
                  </div>
                </div>
              </div>

              <div className="form-section">
                <h3 style={{ fontSize: '1rem', fontWeight: 600, color: '#374151', marginBottom: '1rem' }}>
                  <Phone size={18} style={{ marginRight: '0.5rem' }} />
                  Contacto
                </h3>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <strong>Teléfono:</strong>
                    <div>{clienteDetalle?.numero_principal || clienteDetalle?.telefono || 'No registrado'}</div>
                  </div>
                  <div>
                    <strong>Correo:</strong>
                    <div>{clienteDetalle?.correo_principal || clienteDetalle?.correo || 'No registrado'}</div>
                  </div>
                  <div className="col-span-2">
                    <strong>Dirección:</strong>
                    <div>{clienteDetalle?.direccion_principal || clienteDetalle?.direccion || 'No registrada'}</div>
                  </div>
                </div>
              </div>

              <div className="form-section">
                <h3 style={{ fontSize: '1rem', fontWeight: 600, color: '#374151', marginBottom: '1rem' }}>
                  <DollarSign size={18} style={{ marginRight: '0.5rem' }} />
                  Información Financiera
                </h3>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <strong>Ingreso mensual:</strong>
                    <div>{formatCurrency(clienteDetalle?.ingreso_mensual || 0)}</div>
                  </div>
                  <div>
                    <strong>Gasto mensual:</strong>
                    <div>{formatCurrency(clienteDetalle?.gasto_mensual || 0)}</div>
                  </div>
                  <div>
                    <strong>Tipo contrato:</strong>
                    <div>{clienteDetalle?.tipo_contrato || 'No registrado'}</div>
                  </div>
                  <div>
                    <strong>Antigüedad laboral (meses):</strong>
                    <div>{clienteDetalle?.antiguedad_laboral ?? 'No registrada'}</div>
                  </div>
                </div>
              </div>
            </div>

            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={closeClientDetailModal}>Cerrar</button>
            </div>
          </div>
        </div>,
        document.body
      )}
    </div>
  );
};

export default ClientesPage;

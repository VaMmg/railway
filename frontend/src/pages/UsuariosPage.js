import React, { useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { UserCog, Plus, Search, Edit, Trash2, User, CreditCard, X, Shield, KeyRound, RotateCcw } from 'lucide-react';
import { peopleService, usersService } from '../services/api';
import { useAuth } from '../contexts/AuthContext';

const UsuariosPage = () => {
  const { hasPermission } = useAuth();
  const queryClient = useQueryClient();

  const [showModal, setShowModal] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const [search, setSearch] = useState('');
  const [errors, setErrors] = useState({});

  const [formData, setFormData] = useState({
    // Requeridos para crear
    dni: '',
    nombres: '',
    apellido_paterno: '',
    apellido_materno: '',
    sexo: 'M',
    fecha_nacimiento: '',
    nacionalidad: 'Peruana',
    estado_civil: 'Soltero',
    nivel_educativo: '',
    // Campos adicionales para tablas relacionadas
    correo: '',
    telefono: '',
    direccion: '',
    // Campos de usuario
    usuario: '',
    rol: 'Trabajador',
    estado_usuario: 'Activo',
  });

  // Cargar USUARIOS para la tabla
  const { data: usuariosData, isLoading: loadingUsuarios } = useQuery({
    queryKey: ['usuarios'],
    queryFn: async () => {
      console.log('[LOAD] Cargando usuarios...');
      const res = await usersService.getAll({ limit: 100 }); // Obtener hasta 100 usuarios
      console.log('[OK] Usuarios cargados:', res);
      return res?.data || res;
    },
    refetchOnWindowFocus: true, // Recargar al volver a la ventana
    staleTime: 0, // Considerar datos obsoletos inmediatamente
  });

  // Cargar PERSONAS para el select del modal
  const { data: personasData, isLoading: loadingPersonas } = useQuery({
    queryKey: ['personas'],
    queryFn: async () => {
      const res = await peopleService.getAll();
      return res?.data || res;
    },
  });

  const usuarios = useMemo(() => {
    if (Array.isArray(usuariosData)) return usuariosData;
    return usuariosData?.usuarios || usuariosData?.data || [];
  }, [usuariosData]);

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

    // Solo mostrar personas sin usuario asignado
    return baseList.filter((persona) => persona?.rol_usuario == null);
  }, [personasData]);

  // Actualizar persona
  const updateMutation = useMutation({
    mutationFn: async ({ id, payload }) => peopleService.update(id, payload),
    onSuccess: async () => {
      await queryClient.refetchQueries(['personas']);
      setShowModal(false);
      resetForm();
      alert('Persona actualizada');
    },
    onError: (e) => alert('Error al actualizar: ' + (e.message || '')),
  });

  const resetForm = () => {
    setEditingUser(null);
    setFormData({
      dni: '',
      nombres: '',
      apellido_paterno: '',
      apellido_materno: '',
      sexo: 'M',
      fecha_nacimiento: '',
      nacionalidad: 'Peruana',
      estado_civil: 'Soltero',
      nivel_educativo: '',
      correo: '',
      telefono: '',
      direccion: '',
      usuario: '',
      rol: 'Trabajador',
      estado_usuario: 'Activo',
    });
    setErrors({});
  };

  const openCreate = () => {
    resetForm();
    setShowModal(true);
  };

  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.dni.trim()) newErrors.dni = 'DNI es requerido';
    else if (!/^\d{8}$/.test(formData.dni)) newErrors.dni = 'DNI debe tener 8 dígitos';
    
    if (!formData.nombres.trim()) newErrors.nombres = 'Nombres son requeridos';
    else if (!/^[A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,4}$/.test(formData.nombres)) newErrors.nombres = 'Solo letras y hasta 5 palabras';
    
    if (!formData.apellido_paterno.trim()) newErrors.apellido_paterno = 'Apellido paterno es requerido';
    else if (!/^[A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,2}$/.test(formData.apellido_paterno)) newErrors.apellido_paterno = 'Solo letras y hasta 3 palabras';
    
    if (formData.apellido_materno && !/^[A-Za-zÁÉÍÓÚáéíóúÑñ]+(?:\s[A-Za-zÁÉÍÓÚáéíóúÑñ]+){0,2}$/.test(formData.apellido_materno)) newErrors.apellido_materno = 'Solo letras y hasta 3 palabras';
    
    if (!formData.fecha_nacimiento) {
      newErrors.fecha_nacimiento = 'Fecha de nacimiento es requerida para generar la contraseña';
    } else {
      const hoy = new Date();
      const fn = new Date(formData.fecha_nacimiento);
      let edad = hoy.getFullYear() - fn.getFullYear();
      const m = hoy.getMonth() - fn.getMonth();
      if (m < 0 || (m === 0 && hoy.getDate() < fn.getDate())) edad--;
      if (edad < 18) newErrors.fecha_nacimiento = 'Debe ser mayor de 18 años';
    }
    
    // Validación de correo (opcional, pero si existe debe ser válido)
    if (formData.correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.correo)) {
      newErrors.correo = 'Formato de correo inválido';
    }
    
    // Validación de teléfono (opcional, pero si existe debe iniciar con 9 y tener 9 dígitos)
    if (formData.telefono && !/^9\d{8}$/.test(formData.telefono)) {
      newErrors.telefono = 'El celular debe iniciar con 9 y tener 9 dígitos';
    }
    
    // Validación de usuario (solo si no está editando)
    if (!editingUser) {
      if (!formData.usuario.trim()) {
        newErrors.usuario = 'Nombre de usuario es requerido (debe ingresar DNI primero)';
      } else if (formData.usuario.length !== 8) {
        newErrors.usuario = 'El usuario debe ser un DNI válido de 8 dígitos';
      }
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleNombreBlur = () => {
    setFormData(prev => ({
      ...prev,
      nombres: prev.nombres
        ? prev.nombres
            .split(' ')
            .filter(Boolean)
            .map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
            .join(' ')
        : ''
    }));
  };

  const handleApellidoBlur = (field) => {
    setFormData(prev => ({
      ...prev,
      [field]: prev[field]
        ? prev[field]
            .split(' ')
            .filter(Boolean)
            .map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
            .join(' ')
        : ''
    }));
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    let newValue = value;
    
    if (name === 'dni') {
      newValue = value.replace(/\D/g, '').slice(0, 8);
      
      // Autocompletar el usuario con el DNI
      if (!editingUser) {
        setFormData(prev => ({
          ...prev,
          dni: newValue,
          usuario: newValue // Usuario = DNI
        }));
        return; // Salir temprano para evitar el setFormData al final
      }
    } else if (name === 'telefono') {
      newValue = value.replace(/\D/g, '').slice(0, 9);
    } else if (name === 'nombres') {
      const hadTrailingSpace = /\s$/.test(value);
      let cleaned = value
        .replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, ' ')
        .replace(/\s+/g, ' ');
      cleaned = cleaned.replace(/^\s+/, '');
      let words = cleaned.split(' ').filter(Boolean);
      if (words.length > 5) words = words.slice(0, 5);
      newValue = words.join(' ') + (hadTrailingSpace && words.length < 5 ? ' ' : '');
    } else if (name === 'apellido_paterno' || name === 'apellido_materno') {
      // Permitir múltiples palabras en apellidos (ej: "De La Cruz")
      const hadTrailingSpace = /\s$/.test(value);
      let cleaned = value
        .replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, ' ')
        .replace(/\s+/g, ' ');
      cleaned = cleaned.replace(/^\s+/, '');
      let words = cleaned.split(' ').filter(Boolean);
      if (words.length > 3) words = words.slice(0, 3); // Máximo 3 palabras por apellido
      newValue = words.join(' ') + (hadTrailingSpace && words.length < 3 ? ' ' : '');
    } else if (name === 'nacionalidad') {
      newValue = value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, '');
    }
    
    setFormData({ ...formData, [name]: newValue });
    if (errors[name]) {
      setErrors({ ...errors, [name]: '' });
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validateForm()) return;
    
    if (!editingUser) {
      try {
        // Generar contraseña con fecha de nacimiento (AAAAMMDD)
        const fechaNac = new Date(formData.fecha_nacimiento);
        const pwd = fechaNac.toISOString().split('T')[0].replace(/-/g, ''); // AAAAMMDD
        
        // Crear usuario Y persona en una sola llamada al backend
        const usuarioPayload = {
          // Datos de usuario
          dni_persona: formData.dni.trim(),
          usuario: formData.usuario.trim(),
          pwd: pwd,
          rol: formData.rol,
          estado: formData.estado_usuario,
          
          // Datos de persona (el backend los manejará)
          nombres: formData.nombres.trim(),
          apellido_paterno: formData.apellido_paterno.trim(),
          apellido_materno: (formData.apellido_materno || '').trim(),
          sexo: formData.sexo,
          fecha_nacimiento: formData.fecha_nacimiento || null,
          nacionalidad: formData.nacionalidad.trim(),
          estado_civil: formData.estado_civil.trim(),
          nivel_educativo: (formData.nivel_educativo || '').trim(),
          correo: (formData.correo || '').trim(),
          telefono: (formData.telefono || '').trim(),
          direccion: (formData.direccion || '').trim(),
        };
        
        await usersService.create(usuarioPayload);
        console.log('[OK] Usuario y persona creados exitosamente');
        
        // Invalidar y refrescar datos para forzar recarga
        await queryClient.invalidateQueries(['personas']);
        await queryClient.invalidateQueries(['usuarios']);
        await queryClient.refetchQueries(['personas']);
        await queryClient.refetchQueries(['usuarios']);
        
        setShowModal(false);
        resetForm();
        alert('Usuario creado exitosamente. Contraseña: ' + pwd);
      } catch (error) {
        console.error('[ERROR] Error:', error);
        alert('Error al crear usuario: ' + (error.message || error.error || 'Error desconocido'));
      }
    } else {
      const payload = {
        nombres: formData.nombres.trim(),
        apellido_paterno: formData.apellido_paterno.trim(),
        apellido_materno: (formData.apellido_materno || '').trim(),
        sexo: formData.sexo,
        fecha_nacimiento: formData.fecha_nacimiento || null,
        nacionalidad: formData.nacionalidad.trim(),
        estado_civil: formData.estado_civil.trim(),
        nivel_educativo: (formData.nivel_educativo || '').trim(),
        correo: (formData.correo || '').trim(),
        telefono: (formData.telefono || '').trim(),
        direccion: (formData.direccion || '').trim(),
      };
      
      updateMutation.mutate({ id: editingUser.dni, payload });
    }
  };



  const handleDeleteUser = (u) => {
    // Evitar eliminar usuarios Admin y Gerente
    if (u.rol === 'Administrador' || u.rol === 'Gerente') {
      alert('No se puede eliminar usuarios con rol Administrador o Gerente. Estos roles son únicos en el sistema.');
      return;
    }
    
    if (!window.confirm(`¿Eliminar usuario "${u.usuario}"?`)) return;
    
    usersService.delete(u.id_usuario || u.usuario).then(() => {
      queryClient.refetchQueries(['usuarios']);
      alert('Usuario eliminado');
    }).catch(e => alert('Error: ' + (e.message || '')));
  };

  const handleResetPassword = (u) => {
    if (!window.confirm(`¿Restablecer la contraseña del usuario "${u.usuario}"?\n\nLa nueva contraseña será generada automáticamente con la fecha de nacimiento.`)) return;
    
    usersService.resetPassword(u.id_usuario).then((response) => {
      if (response.success) {
        alert(`Contraseña restablecida exitosamente.\n\nUsuario: ${u.usuario}\nNueva contraseña: ${response.data.nueva_password}\n\n¡Asegúrate de comunicar esta contraseña al usuario!`);
      } else {
        alert('Error: ' + (response.message || 'No se pudo restablecer la contraseña'));
      }
    }).catch(e => {
      alert('Error al restablecer contraseña: ' + (e.message || e.error || 'Error desconocido'));
    });
  };

  const filtered = usuarios
    .filter((u) => {
      const q = search.toLowerCase();
      const matchesSearch =
        (u.usuario || '').toLowerCase().includes(q) ||
        (u.dni_persona || '').toLowerCase().includes(q) ||
        (u.nombre_completo || u.nombres || '').toLowerCase().includes(q);
      return matchesSearch;
    })
    .sort((a, b) => (b.id_usuario || 0) - (a.id_usuario || 0)); // Ordenar por ID descendente (más recientes primero)

  return (
    <div className="container-fluid">
      <div className="row">
        <div className="col-12">
          <div className="card">
            <div className="card-header">
              <div className="d-flex justify-content-between align-items-center">
                <h5 className="card-title mb-0">
                  <UserCog className="me-2" /> Gestión de Usuarios
                </h5>
                {hasPermission('admin') && (
                  <button className="btn btn-primary" onClick={openCreate}>
                    <Plus size={18} className="me-2" /> Nuevo Usuario
                  </button>
                )}
              </div>
            </div>
            <div className="card-body">
              {/* Filtros */}
              <div className="row mb-3">
                <div className="col-md-12">
                  <div className="input-group">
                    <span className="input-group-text"><Search size={16} /></span>
                    <input
                      type="text"
                      className="form-control"
                      placeholder="Buscar por usuario, DNI o nombre..."
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                    />
                  </div>
                </div>
              </div>

              {/* Tabla */}
              <div className="table-responsive">
                <table className="table table-striped table-hover">
                  <thead className="table-dark">
                    <tr>
                      <th>Usuario</th>
                      <th>DNI Persona</th>
                      <th>Nombre Completo</th>
                      <th>Rol</th>
                      <th>Estado</th>
                      <th>Último Acceso</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {loadingUsuarios ? (
                      <tr><td colSpan="7">Cargando...</td></tr>
                    ) : filtered.length ? (
                      filtered.map((u) => (
                        <tr key={u.id_usuario || u.usuario}>
                          <td><strong>{u.usuario}</strong></td>
                          <td>{u.dni_persona || '-'}</td>
                          <td>{u.nombre_completo || u.nombres || '-'}</td>
                          <td>
                            <span className={`badge ${
                              u.rol === 'Administrador' ? 'bg-danger' :
                              u.rol === 'Gerente' ? 'bg-warning' : 'bg-info'
                            }`}>
                              {u.rol || '-'}
                            </span>
                          </td>
                          <td>
                            <span className={`badge ${u.estado === 'Activo' ? 'bg-success' : 'bg-secondary'}`}>
                              {u.estado || '-'}
                            </span>
                          </td>
                          <td>{u.ultimo_acceso ? new Date(u.ultimo_acceso).toLocaleString() : 'Nunca'}</td>
                          <td>
                            <div className="btn-group btn-group-sm" role="group">
                              {hasPermission('admin') && (
                                <button 
                                  className="btn btn-outline-warning" 
                                  title="Restablecer Contraseña" 
                                  onClick={() => handleResetPassword(u)}
                                >
                                  <RotateCcw size={14} />
                                </button>
                              )}
                              {hasPermission('admin') && u.rol !== 'Administrador' && u.rol !== 'Gerente' ? (
                                <button className="btn btn-outline-danger" title="Eliminar" onClick={() => handleDeleteUser(u)}>
                                  <Trash2 size={14} />
                                </button>
                              ) : (u.rol === 'Administrador' || u.rol === 'Gerente') && hasPermission('admin') ? (
                                <span className="text-muted" style={{ fontSize: '0.75rem', padding: '0.25rem 0.5rem' }} title="Los roles únicos no se pueden eliminar">
                                  [BLOQUEADO]
                                </span>
                              ) : null}
                            </div>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr><td colSpan="7" className="text-center text-muted">No se encontraron usuarios</td></tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Modal Crear/Editar */}
      {showModal && createPortal(
        <div className="modal-overlay" onClick={() => { setShowModal(false); resetForm(); }}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '700px' }}>
            <div className="modal-header">
              <h2>{editingUser ? 'Editar Persona' : 'Nuevo Usuario'}</h2>
              <button type="button" className="modal-close" onClick={() => { setShowModal(false); resetForm(); }}>
                <X size={24} />
              </button>
            </div>
            
            <form onSubmit={handleSubmit}>
              <div className="modal-body">
                {!editingUser ? (
                  <>
                    {/* Información Personal */}
                    <div className="form-section">
                      <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                        <User size={18} style={{ marginRight: '0.5rem' }} />
                        Información Personal
                      </h3>
                      
                      <div className="grid grid-cols-2 gap-4">
                        <div className="form-group">
                          <label className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                            <CreditCard size={16} style={{ flexShrink: 0, color: '#6b7280' }} />
                            <span>DNI *</span>
                          </label>
                          <input
                            name="dni"
                            className={`form-input ${errors.dni ? 'error' : ''}`}
                            value={formData.dni}
                            onChange={handleChange}
                            maxLength={8}
                            required
                            placeholder="Ingrese 8 dígitos"
                          />
                          {errors.dni && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.dni}</small>}
                        </div>

                        <div className="form-group">
                          <label className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                            <User size={16} style={{ flexShrink: 0, color: '#6b7280' }} />
                            <span>Nombres *</span>
                          </label>
                          <input
                            name="nombres"
                            className={`form-input ${errors.nombres ? 'error' : ''}`}
                            value={formData.nombres}
                            onChange={handleChange}
                            onBlur={handleNombreBlur}
                            required
                          />
                          {errors.nombres && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.nombres}</small>}
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-4">
                        <div className="form-group">
                          <label className="form-label">Apellido Paterno *</label>
                          <input
                            name="apellido_paterno"
                            className={`form-input ${errors.apellido_paterno ? 'error' : ''}`}
                            value={formData.apellido_paterno}
                            onChange={handleChange}
                            onBlur={() => handleApellidoBlur('apellido_paterno')}
                            required
                          />
                          {errors.apellido_paterno && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.apellido_paterno}</small>}
                        </div>

                        <div className="form-group">
                          <label className="form-label">Apellido Materno</label>
                          <input
                            name="apellido_materno"
                            className={`form-input ${errors.apellido_materno ? 'error' : ''}`}
                            value={formData.apellido_materno}
                            onChange={handleChange}
                            onBlur={() => handleApellidoBlur('apellido_materno')}
                          />
                          {errors.apellido_materno && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.apellido_materno}</small>}
                        </div>
                      </div>
                    </div>

                    {/* Datos Adicionales */}
                    <div className="form-section">
                      <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                        <User size={18} style={{ marginRight: '0.5rem' }} />
                        Datos Adicionales
                      </h3>
                      
                      <div className="grid grid-cols-2 gap-4">
                        <div className="form-group">
                          <label className="form-label">Sexo *</label>
                          <select
                            name="sexo"
                            className="form-input"
                            value={formData.sexo}
                            onChange={handleChange}
                            required
                          >
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                          </select>
                        </div>

                        <div className="form-group">
                          <label className="form-label">Fecha de Nacimiento</label>
                          <input
                            name="fecha_nacimiento"
                            type="date"
                            className={`form-input ${errors.fecha_nacimiento ? 'error' : ''}`}
                            value={formData.fecha_nacimiento}
                            onChange={handleChange}
                          />
                          {errors.fecha_nacimiento && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.fecha_nacimiento}</small>}
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-4">
                        <div className="form-group">
                          <label className="form-label">Nacionalidad *</label>
                          <input
                            name="nacionalidad"
                            className="form-input"
                            value={formData.nacionalidad}
                            onChange={handleChange}
                            required
                          />
                        </div>

                        <div className="form-group">
                          <label className="form-label">Estado Civil *</label>
                          <select
                            name="estado_civil"
                            className="form-input"
                            value={formData.estado_civil}
                            onChange={handleChange}
                            required
                          >
                            <option value="Soltero">Soltero</option>
                            <option value="Casado">Casado</option>
                            <option value="Divorciado">Divorciado</option>
                            <option value="Viudo">Viudo</option>
                          </select>
                        </div>
                      </div>

                      <div className="form-group">
                        <label className="form-label">Nivel Educativo</label>
                        <select
                          name="nivel_educativo"
                          className="form-input"
                          value={formData.nivel_educativo}
                          onChange={handleChange}
                        >
                          <option value="">Seleccionar...</option>
                          <option value="Primaria">Primaria</option>
                          <option value="Secundaria">Secundaria</option>
                          <option value="Técnico">Técnico</option>
                          <option value="Superior">Superior</option>
                          <option value="Postgrado">Postgrado</option>
                        </select>
                      </div>
                    </div>

                    {/* Información de Contacto */}
                    <div className="form-section">
                      <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                        <User size={18} style={{ marginRight: '0.5rem' }} />
                        Información de Contacto
                      </h3>
                      
                      <div className="grid grid-cols-2 gap-4">
                        <div className="form-group">
                          <label className="form-label">Correo Electrónico</label>
                          <input
                            name="correo"
                            type="email"
                            className={`form-input ${errors.correo ? 'error' : ''}`}
                            value={formData.correo}
                            onChange={handleChange}
                          />
                          {errors.correo && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.correo}</small>}
                        </div>

                        <div className="form-group">
                          <label className="form-label">Celular</label>
                          <input
                            name="telefono"
                            className={`form-input ${errors.telefono ? 'error' : ''}`}
                            value={formData.telefono}
                            onChange={handleChange}
                            maxLength={9}
                          />
                          {errors.telefono && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.telefono}</small>}
                        </div>
                      </div>

                      <div className="form-group">
                        <label className="form-label">Dirección</label>
                        <textarea
                          name="direccion"
                          className="form-input"
                          value={formData.direccion}
                          onChange={handleChange}
                          rows={3}
                        />
                      </div>
                    </div>

                    {/* Datos de Usuario */}
                    <div className="form-section">
                      <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                        <Shield size={18} style={{ marginRight: '0.5rem' }} />
                        Datos de Usuario
                      </h3>
                      
                      <div className="grid grid-cols-2 gap-4">
                        <div className="form-group">
                          <label className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                            <UserCog size={16} style={{ flexShrink: 0, color: '#6b7280' }} />
                            <span>Nombre de Usuario *</span>
                          </label>
                          <input
                            name="usuario"
                            className={`form-input ${errors.usuario ? 'error' : ''}`}
                            value={formData.usuario}
                            onChange={handleChange}
                            placeholder="Se generará automáticamente con el DNI"
                            required
                            readOnly
                            style={{ backgroundColor: '#f3f4f6', cursor: 'not-allowed' }}
                          />
                          {errors.usuario && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.usuario}</small>}
                          <small style={{ color: '#6b7280', fontSize: '0.75rem' }}>
                            El usuario será el mismo que el DNI ingresado
                          </small>
                        </div>

                        <div className="form-group">
                          <label className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                            <Shield size={16} style={{ flexShrink: 0, color: '#6b7280' }} />
                            <span>Rol *</span>
                          </label>
                          <input
                            type="text"
                            className="form-input"
                            value="Trabajador"
                            readOnly
                            style={{ backgroundColor: '#f3f4f6', cursor: 'not-allowed' }}
                          />
                          <small style={{ color: '#6b7280', fontSize: '0.75rem' }}>
                            Los roles Administrador y Gerente son únicos en el sistema
                          </small>
                        </div>
                      </div>

                      <div className="form-group">
                        <label className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <KeyRound size={16} style={{ flexShrink: 0, color: '#6b7280' }} />
                          <span>Contraseña</span>
                        </label>
                        <div className="alert alert-info" style={{ 
                          padding: '0.75rem', 
                          backgroundColor: '#dbeafe', 
                          border: '1px solid #93c5fd',
                          borderRadius: '0.375rem',
                          fontSize: '0.875rem',
                          color: '#1e40af',
                          display: 'flex',
                          alignItems: 'center',
                          gap: '0.5rem'
                        }}>
                          <KeyRound size={14} style={{ flexShrink: 0 }} />
                          <span>La contraseña se generará automáticamente con la fecha de nacimiento (formato: AAAAMMDD)</span>
                        </div>
                      </div>
                    </div>
                  </>
                ) : (
                  <>
                    {/* Edición de Persona - Mismos campos que creación excepto DNI */}
                    <div className="form-section">
                      <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                        <User size={18} style={{ marginRight: '0.5rem' }} />
                        Información Personal
                      </h3>
                      
                      <div className="grid grid-cols-2 gap-4">
                        <div className="form-group">
                          <label className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                            <User size={16} style={{ flexShrink: 0, color: '#6b7280' }} />
                            <span>Nombres *</span>
                          </label>
                          <input
                            name="nombres"
                            className={`form-input ${errors.nombres ? 'error' : ''}`}
                            value={formData.nombres}
                            onChange={handleChange}
                            onBlur={handleNombreBlur}
                            required
                          />
                          {errors.nombres && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.nombres}</small>}
                        </div>

                        <div className="form-group">
                          <label className="form-label">Apellido Paterno *</label>
                          <input
                            name="apellido_paterno"
                            className={`form-input ${errors.apellido_paterno ? 'error' : ''}`}
                            value={formData.apellido_paterno}
                            onChange={handleChange}
                            onBlur={() => handleApellidoBlur('apellido_paterno')}
                            required
                          />
                          {errors.apellido_paterno && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.apellido_paterno}</small>}
                        </div>
                      </div>

                      <div className="form-group">
                        <label className="form-label">Apellido Materno</label>
                        <input
                          name="apellido_materno"
                          className={`form-input ${errors.apellido_materno ? 'error' : ''}`}
                          value={formData.apellido_materno}
                          onChange={handleChange}
                          onBlur={() => handleApellidoBlur('apellido_materno')}
                        />
                        {errors.apellido_materno && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.apellido_materno}</small>}
                      </div>
                    </div>

                    {/* Datos Adicionales */}
                    <div className="form-section">
                      <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                        <User size={18} style={{ marginRight: '0.5rem' }} />
                        Datos Adicionales
                      </h3>
                      
                      <div className="grid grid-cols-2 gap-4">
                        <div className="form-group">
                          <label className="form-label">Sexo *</label>
                          <select
                            name="sexo"
                            className="form-input"
                            value={formData.sexo}
                            onChange={handleChange}
                            required
                          >
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                          </select>
                        </div>

                        <div className="form-group">
                          <label className="form-label">Fecha de Nacimiento</label>
                          <input
                            name="fecha_nacimiento"
                            type="date"
                            className={`form-input ${errors.fecha_nacimiento ? 'error' : ''}`}
                            value={formData.fecha_nacimiento}
                            onChange={handleChange}
                          />
                          {errors.fecha_nacimiento && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.fecha_nacimiento}</small>}
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-4">
                        <div className="form-group">
                          <label className="form-label">Nacionalidad *</label>
                          <input
                            name="nacionalidad"
                            className="form-input"
                            value={formData.nacionalidad}
                            onChange={handleChange}
                            required
                          />
                        </div>

                        <div className="form-group">
                          <label className="form-label">Estado Civil *</label>
                          <select
                            name="estado_civil"
                            className="form-input"
                            value={formData.estado_civil}
                            onChange={handleChange}
                            required
                          >
                            <option value="Soltero">Soltero</option>
                            <option value="Casado">Casado</option>
                            <option value="Divorciado">Divorciado</option>
                            <option value="Viudo">Viudo</option>
                          </select>
                        </div>
                      </div>

                      <div className="form-group">
                        <label className="form-label">Nivel Educativo</label>
                        <select
                          name="nivel_educativo"
                          className="form-input"
                          value={formData.nivel_educativo}
                          onChange={handleChange}
                        >
                          <option value="">Seleccionar...</option>
                          <option value="Primaria">Primaria</option>
                          <option value="Secundaria">Secundaria</option>
                          <option value="Técnico">Técnico</option>
                          <option value="Superior">Superior</option>
                          <option value="Postgrado">Postgrado</option>
                        </select>
                      </div>
                    </div>

                    {/* Información de Contacto */}
                    <div className="form-section">
                      <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                        <User size={18} style={{ marginRight: '0.5rem' }} />
                        Información de Contacto
                      </h3>
                      
                      <div className="grid grid-cols-2 gap-4">
                        <div className="form-group">
                          <label className="form-label">Correo Electrónico</label>
                          <input
                            name="correo"
                            type="email"
                            className={`form-input ${errors.correo ? 'error' : ''}`}
                            value={formData.correo}
                            onChange={handleChange}
                          />
                          {errors.correo && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.correo}</small>}
                        </div>

                        <div className="form-group">
                          <label className="form-label">Celular</label>
                          <input
                            name="telefono"
                            className={`form-input ${errors.telefono ? 'error' : ''}`}
                            value={formData.telefono}
                            onChange={handleChange}
                            maxLength={9}
                          />
                          {errors.telefono && <small style={{ color: '#ef4444', fontSize: '0.875rem' }}>{errors.telefono}</small>}
                        </div>
                      </div>

                      <div className="form-group">
                        <label className="form-label">Dirección</label>
                        <textarea
                          name="direccion"
                          className="form-input"
                          value={formData.direccion}
                          onChange={handleChange}
                          rows={3}
                        />
                      </div>
                    </div>
                  </>
                )}
              </div>

              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => { setShowModal(false); resetForm(); }}>
                  Cancelar
                </button>
                <button type="submit" className="btn btn-primary" disabled={updateMutation.isLoading}>
                  {updateMutation.isLoading ? (
                    <>
                      <span className="spinner-border spinner-border-sm me-2" role="status" />
                      Guardando...
                    </>
                  ) : (
                    <>
                      {editingUser ? <Edit size={18} /> : <Plus size={18} />}
                      {editingUser ? 'Actualizar Persona' : 'Crear Usuario'}
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>,
        document.body
      )}
    </div>
  );
};

export default UsuariosPage;

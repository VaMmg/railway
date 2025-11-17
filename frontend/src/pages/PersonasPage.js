import React, { useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { Users, Plus, Search, Edit, Trash2, User, CreditCard, X, Phone, Mail, MapPin } from 'lucide-react';
import { peopleService } from '../services/api';
import { useAuth } from '../contexts/AuthContext';

const PersonasPage = () => {
  const { hasPermission } = useAuth();
  const queryClient = useQueryClient();

  const [showModal, setShowModal] = useState(false);
  const [editingPersona, setEditingPersona] = useState(null);
  const [search, setSearch] = useState('');

  const [formData, setFormData] = useState({
    dni: '',
    nombres: '',
    apellido_paterno: '',
    apellido_materno: '',
    telefono: '',
    correo: '',
    direccion: '',
    sexo: 'M',
    nacionalidad: 'Peruana',
    fecha_nacimiento: '',
    estado_civil: 'Soltero'
  });

  // Cargar personas
  const { data: personasData, isLoading } = useQuery({
    queryKey: ['personas'],
    queryFn: async () => {
      const res = await peopleService.getAll();
      return res?.data || { personas: res?.personas || [], pagination: res?.pagination || {} };
    },
  });

  const personas = useMemo(() => personasData?.personas || personasData || [], [personasData]);

  // Crear persona
  const createMutation = useMutation({
    mutationFn: async (payload) => peopleService.create(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries(['personas']);
      await queryClient.refetchQueries(['personas']);
      setShowModal(false);
      resetForm();
      alert('Persona creada exitosamente');
    },
    onError: (e) => alert('Error al crear: ' + (e.message || '')),
  });

  // Actualizar persona
  const updateMutation = useMutation({
    mutationFn: async ({ id, payload }) => peopleService.update(id, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries(['personas']);
      await queryClient.refetchQueries(['personas']);
      setShowModal(false);
      resetForm();
      alert('Persona actualizada');
    },
    onError: (e) => alert('Error al actualizar: ' + (e.message || '')),
  });

  // Eliminar persona
  const deleteMutation = useMutation({
    mutationFn: async (id) => peopleService.delete(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries(['personas']);
      await queryClient.refetchQueries(['personas']);
      alert('Persona eliminada');
    },
    onError: (e) => alert('Error al eliminar: ' + (e.message || '')),
  });

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

  const resetForm = () => {
    setEditingPersona(null);
    setFormData({
      dni: '',
      nombres: '',
      apellido_paterno: '',
      apellido_materno: '',
      telefono: '',
      correo: '',
      direccion: '',
      sexo: 'M',
      nacionalidad: 'Peruana',
      fecha_nacimiento: '',
      estado_civil: 'Soltero'
    });
  };

  const openCreate = () => {
    resetForm();
    setShowModal(true);
  };

  const openEdit = (persona) => {
    setEditingPersona(persona);
    setFormData({
      dni: persona.dni || '',
      nombres: persona.nombres || '',
      apellido_paterno: persona.apellido_paterno || '',
      apellido_materno: persona.apellido_materno || '',
      telefono: persona.telefono || '',
      correo: persona.correo || '',
      direccion: persona.direccion || '',
      sexo: persona.sexo || 'M',
      nacionalidad: persona.nacionalidad || 'Peruana',
      fecha_nacimiento: persona.fecha_nacimiento || '',
      estado_civil: persona.estado_civil || 'Soltero'
    });
    setShowModal(true);
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    let newValue = value;
    
    if (name === 'dni') {
      newValue = value.replace(/\D/g, '').slice(0, 8);
    } else if (name === 'telefono') {
      newValue = value.replace(/\D/g, '').slice(0, 9);
    } else if (name === 'nombres') {
      const palabras = value.trim().split(/\s+/);
      if (palabras.length <= 5) {
        newValue = capitalizarPalabras(value);
      } else {
        return;
      }
    } else if (name === 'apellido_paterno' || name === 'apellido_materno') {
      const palabras = value.trim().split(/\s+/);
      if (palabras.length <= 1 || value.endsWith(' ')) {
        newValue = capitalizarPalabras(value);
      } else {
        return;
      }
    }
    
    setFormData({ ...formData, [name]: newValue });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    
    // Validaciones
    if (!formData.dni || !formData.nombres || !formData.apellido_paterno) {
      alert('Completa DNI, nombres y apellido paterno');
      return;
    }
    
    if (!/^\d{8}$/.test(formData.dni)) {
      alert('DNI debe tener 8 dígitos');
      return;
    }
    
    const palabrasNombres = formData.nombres.trim().split(/\s+/);
    if (palabrasNombres.length > 5) {
      alert('Máximo 5 nombres permitidos');
      return;
    }
    
    const palabrasApPaterno = formData.apellido_paterno.trim().split(/\s+/);
    if (palabrasApPaterno.length > 1) {
      alert('Solo se permite un apellido paterno');
      return;
    }
    
    if (formData.apellido_materno) {
      const palabrasApMaterno = formData.apellido_materno.trim().split(/\s+/);
      if (palabrasApMaterno.length > 1) {
        alert('Solo se permite un apellido materno');
        return;
      }
    }
    
    if (formData.fecha_nacimiento) {
      const edad = calcularEdad(formData.fecha_nacimiento);
      if (edad !== null && edad < 18) {
        alert('La persona debe ser mayor de edad (18 años)');
        return;
      }
    }
    
    if (formData.telefono && !/^9\d{8}$/.test(formData.telefono)) {
      alert('El celular debe iniciar con 9 y tener 9 dígitos');
      return;
    }
    
    if (formData.correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.correo)) {
      alert('Formato de correo inválido');
      return;
    }

    const payload = {
      dni: formData.dni.trim(),
      nombres: formData.nombres.trim(),
      apellido_paterno: formData.apellido_paterno.trim(),
      apellido_materno: (formData.apellido_materno || '').trim(),
      telefono: (formData.telefono || '').trim(),
      correo: (formData.correo || '').trim(),
      direccion: (formData.direccion || '').trim(),
      sexo: formData.sexo,
      nacionalidad: formData.nacionalidad.trim(),
      fecha_nacimiento: formData.fecha_nacimiento || null,
      estado_civil: formData.estado_civil || 'Soltero'
    };

    if (!editingPersona) {
      createMutation.mutate(payload);
    } else {
      updateMutation.mutate({ id: editingPersona.id_persona, payload });
    }
  };

  const handleDelete = (persona) => {
    if (!window.confirm(`¿Eliminar a ${persona.nombres} ${persona.apellido_paterno}?`)) return;
    deleteMutation.mutate(persona.id_persona);
  };

  const filtered = personas
    .filter((p) => {
      const q = search.toLowerCase();
      return (
        (p.dni || '').toLowerCase().includes(q) ||
        (p.nombres || '').toLowerCase().includes(q) ||
        (p.apellido_paterno || '').toLowerCase().includes(q) ||
        (p.apellido_materno || '').toLowerCase().includes(q)
      );
    })
    .sort((a, b) => (b.id_persona || 0) - (a.id_persona || 0)); // Ordenar por ID descendente (más recientes primero)

  return (
    <div className="container-fluid">
      <div className="row">
        <div className="col-12">
          <div className="card">
            <div className="card-header">
              <div className="d-flex justify-content-between align-items-center">
                <h5 className="card-title mb-0">
                  <Users className="me-2" /> Gestión de Personas
                </h5>
                <button className="btn btn-primary" onClick={openCreate}>
                  <Plus size={18} className="me-2" /> Nueva Persona
                </button>
              </div>
            </div>
            <div className="card-body">
              {/* Filtro de búsqueda */}
              <div className="row mb-3">
                <div className="col-md-6">
                  <div className="input-group">
                    <span className="input-group-text"><Search size={16} /></span>
                    <input
                      type="text"
                      className="form-control"
                      placeholder="Buscar por DNI o nombre..."
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                    />
                  </div>
                </div>
              </div>

              {/* Tabla */}
              <div className="table-responsive">
                <table className="table table-striped table-hover">
                  <thead className="table-light">
                    <tr>
                      <th>DNI</th>
                      <th>Nombre Completo</th>
                      <th>Teléfono</th>
                      <th>Email</th>
                      <th>Sexo</th>
                      <th>Nacionalidad</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {isLoading ? (
                      <tr><td colSpan="7" className="text-center">Cargando...</td></tr>
                    ) : filtered.length ? (
                      filtered.map((persona) => (
                        <tr key={persona.id_persona}>
                          <td>{persona.dni}</td>
                          <td>{`${persona.nombres || ''} ${persona.apellido_paterno || ''} ${persona.apellido_materno || ''}`}</td>
                          <td>{persona.telefono || '-'}</td>
                          <td>{persona.correo || '-'}</td>
                          <td>{persona.sexo === 'M' ? 'Masculino' : 'Femenino'}</td>
                          <td>{persona.nacionalidad || '-'}</td>
                          <td>
                            <div className="btn-group btn-group-sm" role="group">
                              <button className="btn btn-primary" title="Editar" onClick={() => openEdit(persona)}>
                                <Edit size={14} />
                              </button>
                              <button className="btn btn-outline-danger" title="Eliminar" onClick={() => handleDelete(persona)}>
                                <Trash2 size={14} />
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr><td colSpan="7" className="text-center text-muted">No se encontraron personas</td></tr>
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
              <h2>{editingPersona ? 'Editar Persona' : 'Nueva Persona'}</h2>
              <button type="button" className="modal-close" onClick={() => { setShowModal(false); resetForm(); }}>
                <X size={24} />
              </button>
            </div>
            
            <form onSubmit={handleSubmit}>
              <div className="modal-body">
                {/* Información Personal */}
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
                        className="form-input"
                        value={formData.dni}
                        onChange={handleChange}
                        maxLength={8}
                        required
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">
                        <User size={16} className="inline-block mr-1" />
                        Nombres *
                      </label>
                      <input
                        name="nombres"
                        className="form-input"
                        value={formData.nombres}
                        onChange={handleChange}
                        required
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label">Apellido Paterno *</label>
                      <input
                        name="apellido_paterno"
                        className="form-input"
                        value={formData.apellido_paterno}
                        onChange={handleChange}
                        required
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">Apellido Materno</label>
                      <input
                        name="apellido_materno"
                        className="form-input"
                        value={formData.apellido_materno}
                        onChange={handleChange}
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label">Sexo</label>
                      <select
                        name="sexo"
                        className="form-input"
                        value={formData.sexo}
                        onChange={handleChange}
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
                        className="form-input"
                        value={formData.fecha_nacimiento}
                        onChange={handleChange}
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label">Nacionalidad</label>
                      <input
                        name="nacionalidad"
                        className="form-input"
                        value={formData.nacionalidad}
                        onChange={handleChange}
                      />
                    </div>
                    <div className="form-group">
                      <label className="form-label">Estado Civil</label>
                      <select
                        name="estado_civil"
                        className="form-input"
                        value={formData.estado_civil || 'Soltero'}
                        onChange={handleChange}
                      >
                        <option value="Soltero">Soltero(a)</option>
                        <option value="Casado">Casado(a)</option>
                        <option value="Divorciado">Divorciado(a)</option>
                        <option value="Viudo">Viudo(a)</option>
                      </select>
                    </div>
                  </div>
                </div>

                {/* Información de Contacto */}
                <div className="form-section">
                  <h3 style={{ fontSize: '1rem', fontWeight: '600', color: '#374151', marginBottom: '1rem' }}>
                    <Phone size={18} style={{ marginRight: '0.5rem' }} />
                    Información de Contacto
                  </h3>
                  
                  <div className="grid grid-cols-2 gap-4">
                    <div className="form-group">
                      <label className="form-label">
                        <Phone size={16} className="inline-block mr-1" />
                        Teléfono
                      </label>
                      <input
                        name="telefono"
                        className="form-input"
                        value={formData.telefono}
                        onChange={handleChange}
                        maxLength={9}
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">
                        <Mail size={16} className="inline-block mr-1" />
                        Correo Electrónico
                      </label>
                      <input
                        name="correo"
                        type="email"
                        className="form-input"
                        value={formData.correo}
                        onChange={handleChange}
                      />
                    </div>
                  </div>

                  <div className="form-group">
                    <label className="form-label">
                      <MapPin size={16} className="inline-block mr-1" />
                      Dirección
                    </label>
                    <input
                      name="direccion"
                      className="form-input"
                      value={formData.direccion}
                      onChange={handleChange}
                    />
                  </div>
                </div>
              </div>

              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => { setShowModal(false); resetForm(); }}>
                  Cancelar
                </button>
                <button type="submit" className="btn btn-primary" disabled={createMutation.isLoading || updateMutation.isLoading}>
                  {createMutation.isLoading || updateMutation.isLoading ? (
                    <>
                      <span className="spinner-border spinner-border-sm me-2" role="status" />
                      Guardando...
                    </>
                  ) : (
                    <>
                      {editingPersona ? <Edit size={18} /> : <Plus size={18} />}
                      {editingPersona ? 'Actualizar' : 'Crear Persona'}
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

export default PersonasPage;

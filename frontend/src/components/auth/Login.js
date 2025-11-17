import React, { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { useLanguage } from '../../contexts/LanguageContext';
import toast from 'react-hot-toast';
import { 
  Eye, EyeOff, User, Lock, Building, Loader2
} from 'lucide-react';
import './Login.css';
const Login = () => {
  const { login, isAuthenticated, loading } = useAuth();
  const navigate = useNavigate();
  const { t } = useLanguage();
  const [formData, setFormData] = useState({
    usuario: '',
    password: ''
  });
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  // Redirigir si ya está autenticado
  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  const validateForm = () => {
    if (!formData.usuario.trim()) {
      toast.error(t('login.validaciones.usuario_requerido'));
      return false;
    }

    if (!formData.password) {
      toast.error(t('login.validaciones.password_requerida'));
      return false;
    } else if (formData.password.length < 4) {
      toast.error(t('login.validaciones.password_min_length'));
      return false;
    }

    return true;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }

    setIsLoading(true);
    
    try {
      const result = await login(formData);
      
      console.log('Login result:', result); // Debug
      
      if (!result.success) {
        const errorMessage = result.message || t('comunes.error');
        console.log('Mostrando error:', errorMessage); // Debug
        toast.error(errorMessage, {
          duration: 4000,
          position: 'top-center',
        });
        setIsLoading(false);
        return; // Importante: detener la ejecución aquí
      }
      
      // Redirigir según el rol del usuario
      const userRole = result.user?.nombre_rol?.toLowerCase() || '';
      
      if (userRole.includes('trabajador')) {
        navigate('/cajas', { replace: true });
      } else if (userRole.includes('gerente') || userRole.includes('administrador')) {
        navigate('/dashboard', { replace: true });
      } else {
        navigate('/', { replace: true });
      }
    } catch (error) {
      console.error('Login error:', error);
      toast.error(t('login.error_conexion'), {
        duration: 4000,
        position: 'top-center',
      });
      setIsLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  if (loading) {
    return (
      <div className="login-loading">
        <Loader2 className="animate-spin" size={48} />
        <p>{t('login.verificando_auth')}</p>
      </div>
    );
  }

  return (
    <div className="login-container">
      <div className="login-background"></div>
      
      
      
      <div className="login-card">
        <div className="login-header">
          <div className="login-logo">
            <Building size={48} />
          </div>
          <h1>{t('login.titulo')}</h1>
          <p>{t('login.subtitulo')}</p>
        </div>

        <form onSubmit={handleSubmit} className="login-form">
          <div className="form-group">
            <div className="input-wrapper">
              <User className="input-leading" size={20} />
              <input
                type="text"
                name="usuario"
                id="usuario"
                placeholder={t('login.usuario')}
                value={formData.usuario}
                onChange={handleChange}
                disabled={isLoading}
                autoComplete="username"
                autoFocus
              />
            </div>
          </div>

          <div className="form-group">
            <div className="input-wrapper">
              <Lock className="input-leading" size={20} />
              <input
                type={showPassword ? 'text' : 'password'}
                name="password"
                id="password"
                placeholder={t('login.password')}
                value={formData.password}
                onChange={handleChange}
                disabled={isLoading}
                autoComplete="current-password"
              />
              <button
                type="button"
                className="password-toggle"
                onClick={() => setShowPassword(!showPassword)}
                disabled={isLoading}
              >
                {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
              </button>
            </div>
          </div>

          <button
            type="submit"
            className="login-button"
            disabled={isLoading}
          >
            {isLoading ? (
              <>
                <Loader2 className="animate-spin" size={20} />
                {t('login.iniciando_sesion')}
              </>
            ) : (
              t('login.iniciar_sesion')
            )}
          </button>
        </form>
      </div>
    </div>
  );
};

export default Login;
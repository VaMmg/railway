import React, { useState } from 'react';
import { useQuery, useQueryClient } from 'react-query';
import { dashboardService, paymentsService } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import { useAutoRefresh } from '../hooks/useAutoRefresh';
import { 
  Users, 
  CreditCard, 
  DollarSign, 
  TrendingUp, 
  AlertTriangle,
  Clock,
  CheckCircle,
  XCircle,
  TrendingDown,
  Activity,
  FileText
} from 'lucide-react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, PieChart, Pie, Cell, ResponsiveContainer, BarChart, Bar } from 'recharts';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import './Dashboard.css';

const Dashboard = () => {
  const { hasPermission } = useAuth();
  const queryClient = useQueryClient();
  const isGerente = hasPermission('gerente');
  const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);

  // Auto-refresh cada 20 segundos
  useAutoRefresh(() => {
    queryClient.invalidateQueries(['dashboardStats']);
    queryClient.invalidateQueries(['pagosRecientes']);
  }, 20000, autoRefreshEnabled);

  const { data: stats, isLoading, error } = useQuery(
    'dashboardStats',
    dashboardService.getStats,
    {
      refetchInterval: 30000,
    }
  );

  // Cargar actividades recientes solo para gerentes
  const { data: actividadesData } = useQuery(
    'actividadesRecientes',
    () => paymentsService.getAll({ limit: 10, page: 1 }),
    {
      enabled: isGerente,
      refetchInterval: 60000,
    }
  );

  const actividadesRecientes = actividadesData?.data?.pagos || [];

  const COLORS = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b'];

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-PE', {
      style: 'currency',
      currency: 'PEN'
    }).format(amount || 0);
  };

  const formatNumber = (number) => {
    return new Intl.NumberFormat('es-PE').format(number || 0);
  };

  if (isLoading) {
    return (
      <div className="dashboard-loading">
        <div className="loading-spinner"></div>
        <p>Cargando estadísticas...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="dashboard-error">
        <AlertTriangle size={48} />
        <h2>Error al cargar estadísticas</h2>
        <p>{error.message}</p>
      </div>
    );
  }

  const dashboardData = stats?.data || {};

  return (
    <div className="dashboard-modern">
      {/* Header */}
      <div className="dashboard-header-modern">
        <div>
          <h1>Panel de Control</h1>
          <p>Resumen general del sistema de créditos</p>
        </div>
        <div className="header-date">
          <Activity size={20} />
          <span>{format(new Date(), "EEEE, d 'de' MMMM yyyy", { locale: es })}</span>
        </div>
      </div>

      {/* Stats Cards - Mejoradas */}
      <div className="stats-grid-modern">
        <div className="stat-card-modern clients-card">
          <div className="stat-card-header">
            <div className="stat-icon-modern">
              <Users size={24} />
            </div>
            <span className="stat-badge">Total</span>
          </div>
          <div className="stat-body">
            <h2>{formatNumber(dashboardData.clientes?.total)}</h2>
            <p>Clientes</p>
          </div>
          <div className="stat-footer">
            <CheckCircle size={16} />
            <span>{formatNumber(dashboardData.clientes?.activos)} activos</span>
          </div>
        </div>

        <div className="stat-card-modern credits-card">
          <div className="stat-card-header">
            <div className="stat-icon-modern">
              <CreditCard size={24} />
            </div>
            <span className="stat-badge">Vigentes</span>
          </div>
          <div className="stat-body">
            <h2>{formatNumber(dashboardData.creditos?.total)}</h2>
            <p>Créditos</p>
          </div>
          <div className="stat-footer">
            <Activity size={16} />
            <span>{formatNumber(dashboardData.creditos?.vigentes)} en curso</span>
          </div>
        </div>

        <div className="stat-card-modern money-card">
          <div className="stat-card-header">
            <div className="stat-icon-modern">
              <DollarSign size={24} />
            </div>
            <span className="stat-badge">Cartera</span>
          </div>
          <div className="stat-body">
            <h2>{formatCurrency(dashboardData.creditos?.monto_total_aprobado)}</h2>
            <p>Monto Total</p>
          </div>
          <div className="stat-footer">
            <TrendingUp size={16} />
            <span>{formatCurrency(dashboardData.creditos?.monto_vigente)} vigente</span>
          </div>
        </div>

        <div className="stat-card-modern payments-card">
          <div className="stat-card-header">
            <div className="stat-icon-modern">
              <TrendingUp size={24} />
            </div>
            <span className="stat-badge">Este Mes</span>
          </div>
          <div className="stat-body">
            <h2>{formatCurrency(dashboardData.pagos_mes?.monto_total)}</h2>
            <p>Pagos Recibidos</p>
          </div>
          <div className="stat-footer">
            <CheckCircle size={16} />
            <span>{formatNumber(dashboardData.pagos_mes?.total_pagos)} transacciones</span>
          </div>
        </div>
      </div>

      {/* Alert Cards - Mejoradas */}
      <div className="alerts-grid-modern">
        <div className="alert-card-modern warning-card">
          <div className="alert-icon-wrapper warning">
            <AlertTriangle size={24} />
          </div>
          <div className="alert-content-modern">
            <h3>Cuotas Vencidas</h3>
            <div className="alert-stats">
              <span className="alert-number">{formatNumber(dashboardData.cuotas_vencidas?.total)}</span>
              <span className="alert-label">cuotas</span>
            </div>
            <div className="alert-amount">{formatCurrency(dashboardData.cuotas_vencidas?.monto)}</div>
          </div>
        </div>

        <div className="alert-card-modern info-card">
          <div className="alert-icon-wrapper info">
            <Clock size={24} />
          </div>
          <div className="alert-content-modern">
            <h3>Próximas Cuotas</h3>
            <div className="alert-stats">
              <span className="alert-number">{formatNumber(dashboardData.proximas_cuotas?.total)}</span>
              <span className="alert-label">en 7 días</span>
            </div>
            <div className="alert-amount">{formatCurrency(dashboardData.proximas_cuotas?.monto)}</div>
          </div>
        </div>

        <div className="alert-card-modern success-card">
          <div className="alert-icon-wrapper success">
            <CheckCircle size={24} />
          </div>
          <div className="alert-content-modern">
            <h3>Tasa de Cobro</h3>
            <div className="alert-stats">
              <span className="alert-number">
                {dashboardData.pagos_mes?.total_pagos && dashboardData.proximas_cuotas?.total 
                  ? Math.round((dashboardData.pagos_mes.total_pagos / (dashboardData.pagos_mes.total_pagos + dashboardData.proximas_cuotas.total)) * 100)
                  : 0}%
              </span>
              <span className="alert-label">efectividad</span>
            </div>
            <div className="alert-amount">Este mes</div>
          </div>
        </div>
      </div>

      {/* Charts Section - Mejorada */}
      <div className="charts-grid-modern">
        <div className="chart-card-modern large">
          <div className="chart-header-modern">
            <div>
              <h3>Evolución de Créditos</h3>
              <p>Cantidad y monto por mes</p>
            </div>
          </div>
          <div className="chart-content-modern">
            <ResponsiveContainer width="100%" height={320}>
              <BarChart data={dashboardData.creditos_por_mes || []}>
                <defs>
                  <linearGradient id="colorCantidad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#667eea" stopOpacity={0.8}/>
                    <stop offset="95%" stopColor="#667eea" stopOpacity={0.3}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                <XAxis 
                  dataKey="mes" 
                  tickFormatter={(value) => {
                    try {
                      return format(parseISO(value), 'MMM', { locale: es });
                    } catch {
                      return value;
                    }
                  }}
                  stroke="#888"
                />
                <YAxis stroke="#888" />
                <Tooltip 
                  contentStyle={{ 
                    background: 'white', 
                    border: '1px solid #e0e0e0',
                    borderRadius: '8px',
                    boxShadow: '0 4px 6px rgba(0,0,0,0.1)'
                  }}
                  formatter={(value, name) => [
                    name === 'cantidad' ? formatNumber(value) : formatCurrency(value),
                    name === 'cantidad' ? 'Cantidad' : 'Monto'
                  ]}
                />
                <Bar dataKey="cantidad" fill="url(#colorCantidad)" radius={[8, 8, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="chart-card-modern">
          <div className="chart-header-modern">
            <div>
              <h3>Estados de Créditos</h3>
              <p>Distribución actual</p>
            </div>
          </div>
          <div className="chart-content-modern">
            <ResponsiveContainer width="100%" height={320}>
              <PieChart>
                <Pie
                  data={dashboardData.estados_creditos || []}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({ estado_credito, cantidad, percent }) => 
                    `${estado_credito}: ${cantidad} (${(percent * 100).toFixed(0)}%)`
                  }
                  outerRadius={100}
                  fill="#8884d8"
                  dataKey="cantidad"
                  nameKey="estado_credito"
                >
                  {(dashboardData.estados_creditos || []).map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip 
                  contentStyle={{ 
                    background: 'white', 
                    border: '1px solid #e0e0e0',
                    borderRadius: '8px',
                    boxShadow: '0 4px 6px rgba(0,0,0,0.1)'
                  }}
                />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>

      {/* Bottom Grid - Top Clients y Actividades Recientes */}
      <div className="bottom-grid-modern">
        {/* Top Clients */}
        {dashboardData.top_clientes && dashboardData.top_clientes.length > 0 && (
          <div className="top-clients-modern">
            <div className="section-header-modern">
              <h3>Principales Clientes</h3>
              <p>Por monto de crédito</p>
            </div>
            <div className="clients-grid-modern">
              {dashboardData.top_clientes.slice(0, 5).map((client, index) => (
                <div key={index} className="client-card-modern">
                  <div className="client-rank">#{index + 1}</div>
                  <div className="client-info-modern">
                    <h4>{client.nombres} {client.apellido_paterno}</h4>
                    <p className="client-dni">DNI: {client.dni}</p>
                  </div>
                  <div className="client-amount-modern">
                    <span className="amount-label">Monto Total</span>
                    <span className="amount-value">{formatCurrency(client.monto_total)}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Actividades Recientes - Solo para Gerentes */}
        {isGerente && actividadesRecientes.length > 0 && (
          <div className="recent-activities-modern">
            <div className="section-header-modern">
              <h3>Actividades Recientes</h3>
              <p>Últimos pagos registrados</p>
            </div>
            <div className="activities-list-modern">
              {actividadesRecientes.map((pago) => (
                <div key={pago.id_pago} className="activity-item-modern">
                  <div className="activity-icon-modern">
                    <FileText size={20} />
                  </div>
                  <div className="activity-content-modern">
                    <div className="activity-header-modern">
                      <h4>{pago.nombres} {pago.apellido_paterno}</h4>
                      <span className="activity-amount">{formatCurrency(pago.monto_pagado)}</span>
                    </div>
                    <div className="activity-details-modern">
                      <span className="activity-credit">Crédito #{pago.id_credito}</span>
                      <span className="activity-separator">•</span>
                      <span className="activity-date">
                        {format(new Date(pago.fecha_pago + 'T00:00:00'), "d 'de' MMM, yyyy", { locale: es })}
                      </span>
                    </div>
                    {pago.referencia_pago && (
                      <div className="activity-reference">
                        <span className="reference-label">Ref:</span>
                        <span className="reference-value">{pago.referencia_pago}</span>
                      </div>
                    )}
                  </div>
                  <div className="activity-status-modern">
                    <CheckCircle size={18} color="#10b981" />
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default Dashboard;

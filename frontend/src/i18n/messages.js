// Simple i18n helper
// Usage: import { t } from './messages'; t('max_fiadores', { max: 3 })

const messages = {
  es: {
    required_fields: 'Por favor complete los campos obligatorios',
    amount_gt_zero: 'El monto original debe ser mayor a 0',
    plazo_gt_zero: 'El plazo debe ser mayor a 0 meses',
    dni_invalid_fiadores: 'DNIs inválidos en fiadores (deben tener {length} dígitos): {list}',
    dni_invalid_avales: 'DNIs inválidos en avales (deben tener {length} dígitos): {list}',
    dup_fiadores: 'Hay DNIs duplicados en fiadores',
    dup_avales: 'Hay DNIs duplicados en avales',
    cross_roles: 'Un DNI no puede ser fiador y aval a la vez: {list}',
    max_fiadores: 'Máximo permitido de fiadores es {max}',
    max_avales: 'Máximo permitido de avales es {max}',
    client_cannot_be_fiador: 'El cliente no puede ser fiador de su propio crédito',
    client_cannot_be_aval: 'El cliente no puede ser aval de su propio crédito',
    cronograma_only_approved: 'El cronograma solo está disponible para créditos aprobados',
  },
  en: {
    required_fields: 'Please complete all required fields',
    amount_gt_zero: 'Original amount must be greater than 0',
    plazo_gt_zero: 'Term must be greater than 0 months',
    dni_invalid_fiadores: 'Invalid DNIs in guarantors (must have {length} digits): {list}',
    dni_invalid_avales: 'Invalid DNIs in cosigners (must have {length} digits): {list}',
    dup_fiadores: 'There are duplicate DNIs in guarantors',
    dup_avales: 'There are duplicate DNIs in cosigners',
    cross_roles: 'The same DNI cannot be both guarantor and cosigner: {list}',
    max_fiadores: 'Maximum allowed guarantors is {max}',
    max_avales: 'Maximum allowed cosigners is {max}',
    client_cannot_be_fiador: 'Client cannot be a guarantor of their own loan',
    client_cannot_be_aval: 'Client cannot be a cosigner of their own loan',
    cronograma_only_approved: 'Schedule is only available for approved credits',
  }
};

const getLocale = () => (process.env.REACT_APP_LOCALE || 'es').toLowerCase();

export function t(key, params = {}) {
  const locale = messages[getLocale()] ? getLocale() : 'es';
  let template = messages[locale][key] || messages['es'][key] || key;
  Object.entries(params).forEach(([k, v]) => {
    template = template.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v));
  });
  return template;
}

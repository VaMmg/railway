# 🚀 Sistema de Créditos - Despliegue en Railway

Este proyecto está listo para desplegarse en Railway de forma gratuita.

## 📋 ¿Qué es Railway?

Railway es una plataforma que te permite subir aplicaciones web a internet de forma sencilla. Ofrece:
- ✅ $5 USD gratis al mes (sin tarjeta de crédito)
- ✅ Despliegue automático desde GitHub
- ✅ Base de datos MySQL incluida
- ✅ URLs públicas automáticas
- ✅ SSL/HTTPS gratis

## 🎯 Archivos Importantes

| Archivo | Descripción |
|---------|-------------|
| `PASOS_RAPIDOS.md` | **EMPIEZA AQUÍ** - Guía paso a paso (20 min) |
| `CHECKLIST_RAILWAY.md` | Lista de verificación para marcar |
| `RAILWAY_DEPLOYMENT.md` | Guía detallada completa |
| `verificar_railway.bat` | Script para verificar que todo esté listo |

## ⚡ Inicio Rápido

### 1. Verifica que todo esté listo

```bash
verificar_railway.bat
```

### 2. Sigue la guía rápida

Abre `PASOS_RAPIDOS.md` y sigue los pasos. En 20 minutos tendrás tu sistema en la nube.

### 3. Usa el checklist

Marca cada paso completado en `CHECKLIST_RAILWAY.md`

## 🏗️ Arquitectura del Despliegue

```
Railway Project
├── MySQL Database (Base de datos)
├── Backend Service (PHP + Apache)
│   └── URL: https://backend-xxx.railway.app
└── Frontend Service (React + Serve)
    └── URL: https://frontend-xxx.railway.app
```

## 💰 Costos

- **Plan Gratuito**: $5 USD de crédito al mes
- **Uso estimado**: ~$3-4 USD/mes para este proyecto
- **Suficiente para**: Desarrollo, pruebas, y uso moderado en producción

## 🔧 Tecnologías

- **Frontend**: React 18 + React Router
- **Backend**: PHP 8.2 + Apache
- **Base de Datos**: MySQL 8.0
- **Despliegue**: Docker + Railway

## 📚 Documentación Adicional

- [Documentación de Railway](https://docs.railway.app)
- [Railway CLI](https://docs.railway.app/develop/cli)
- [Preguntas Frecuentes](https://railway.app/help)

## 🆘 Soporte

Si tienes problemas:

1. Revisa `RAILWAY_DEPLOYMENT.md` sección "Solución de Problemas"
2. Verifica los logs en Railway Dashboard
3. Asegúrate de que todas las variables de entorno estén configuradas
4. Revisa que los 3 servicios estén corriendo (luz verde)

## 📝 Notas Importantes

- Las URLs de Railway son públicas, cualquiera con el link puede acceder
- Railway reinicia servicios automáticamente si fallan
- Los cambios en GitHub se despliegan automáticamente
- Puedes pausar servicios para ahorrar créditos
- El plan gratuito es suficiente para empezar

## 🎓 Próximos Pasos Después del Despliegue

1. Configura un dominio personalizado (opcional)
2. Configura backups automáticos de la base de datos
3. Monitorea el uso de recursos en Railway
4. Considera actualizar al plan de pago si necesitas más recursos

## ✨ ¡Listo para Empezar!

Abre `PASOS_RAPIDOS.md` y comienza tu despliegue. En 20 minutos tu sistema estará en la nube. 🚀

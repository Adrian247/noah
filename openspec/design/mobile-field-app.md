# App de campo — Phoenix (diseño)

Fase 2 — Flutter. Referencia arquitectónica: [mobile-sync.md](../architecture/mobile-sync.md).

## Navegación móvil

```mermaid
flowchart TB
  Tabs[Tab bar]
  Tabs --> Hoy[Hoy / Asignadas]
  Tabs --> Cola[Cola sync]
  Tabs --> Perfil[Perfil]

  Hoy --> Detalle[Detalle rutina]
  Detalle --> Form[Formulario dinámico]
  Form --> Evidencia[Cámara / galería]
  Form --> Tiempos[Registro tiempos]
  Form --> Fin[Finalizar y firmar]
```

## Pantallas

| Pantalla | Contenido |
|----------|-----------|
| Lista “Hoy” | Rutinas asignadas, badge offline/sync/error |
| Detalle | Activo, sitio, tipo, instrucciones |
| Formulario | Render desde definición JSON (mismos tipos que web) |
| Cámara | Compresión ligera, cola de subida |
| Finalizar | Resumen + firma + confirmación local |
| Cola sync | Eventos pendientes, reintentar, último error |

## Offline

- SQLite: rutinas, respuestas, eventos outbox, catálogos cacheados.
- Indicador global: verde (sync OK), amarillo (pendiente), rojo (error persistente).
- Finalizar rutina **nunca** bloqueado por red.

## Tiempos

- Cronómetro opcional por rutina o por tarea (configurable en tipo de rutina).
- Persistir duración en ejecución para costos.

## Seguridad

- PIN o biometría opcional al abrir app (empresa configurable).
- Logout revoca token y limpia datos sensibles según política.

## Diferencias con web

| Capacidad | Web | Móvil |
|-----------|-----|-------|
| Diseñar formularios | Sí | No |
| Ejecutar rutina | Sí (limitado) | Sí (principal) |
| Validar | Sí | No (v1) |
| Facturar | Sí | No |

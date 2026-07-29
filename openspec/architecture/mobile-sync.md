# Mobile Sync — Phoenix

La aplicación móvil será offline-first (ADR-004).

El servidor es source of truth.

La sincronización es mediante **eventos**, no réplica de tablas completas.

El motor deberá:

- Resolver conflictos
- Mantener idempotencia
- Registrar auditoría
- Sincronizar fotografías (metadatos + upload a storage)
- Sincronizar firmas
- Sincronizar definiciones de formularios y catálogos publicados

API orientativa: `POST /api/sync` con lote de cambios del dispositivo y respuesta `serverChanges`.

Diseño UX: [design/mobile-field-app.md](../design/mobile-field-app.md).

# Implementación — Noah (estado)

## Backend

| Área | Estado |
|------|--------|
| Identity, companies, memberships, Sanctum | Implementado |
| Catálogo, insumos, activos, **proveedores**, **sitios CRUD** | Implementado |
| Forms / report versions + API diseño | Implementado MVP |
| AI Gateway + PromptTemplate + AiInvocation | Implementado (local + OpenAI opcional) |
| Workflow runtime v1 + diseño visual (layout) | Implementado MVP |
| Evento `RoutineValidated` → PDF (cola) + borrador factura | Implementado |
| Reportes PDF (DomPDF, job `GenerateRoutineReportJob`) | Implementado v1 async |
| Ejecución con `responses`, `consumptions`, **evidencias** | Implementado |
| **Mobile Sync API** (`POST /sync`) | Implementado v1 |
| Facturación borrador / emitir + **módulo** (config, detalle, `docs/BILLING.md`) | Implementado v1 |
| Auditoría append-only + API | Implementado v1 |
| Email pendiente validación (supervisores) | Implementado v1 (Mailpit) |
| **Dashboard summary API** | Implementado |
| **Usuarios empresa (lectura admin)** | Implementado |
| **`noah:ensure-demo`** en arranque Docker | Implementado |
| **`noah:refresh-demo`** (seed + permisos + credenciales) | Implementado — ver `docs/DEMO_ENV.md` |

## Frontend

| Pantalla | Estado |
|----------|--------|
| Login, dashboard, **shell glass + nav por flujos** | Implementado |
| **Facturación**: hub, detalle, configuración IVA/mano de obra | Implementado |
| Lista rutina **filtros + alta**, detalle (formulario, validar, PDF) | Implementado |
| Catálogo equipos, insumos, activos, **proveedores**, **sitios** | Implementado |
| Diseño: formularios, reportes, workflows (MVP) | Implementado |
| Tipos de rutina | Implementado |
| Facturación, auditoría, **usuarios (admin)** | Implementado |

## Pruebas

- Automatizadas: `docker compose exec app php artisan test`
- Manuales: [PRUEBAS_MANUALES.md](PRUEBAS_MANUALES.md)

## Cambios archivados

Ver [openspec/archive/README.md](../openspec/archive/README.md) (001–015).

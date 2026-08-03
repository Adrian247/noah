# Implementación — Phoenix (estado)

## Backend

| Área | Estado |
|------|--------|
| Identity, companies, memberships, Sanctum | Implementado |
| Catálogo, insumos, activos, **proveedores**, **sitios CRUD** | Implementado |
| Forms / report versions + API diseño | Implementado MVP |
| AI Gateway v2 + laravel/ai + tools read-only + PromptTemplate + AiInvocation | Implementado (local grounding + Google/OpenAI vía Laravel AI SDK) |
| Workflow runtime v1 + diseño visual (layout, **alta/duplicar**, validador v1) | Implementado MVP |
| **Workflow v2** (facturación en grafo, `PendingBilling`, `correlation_id`) | Implementado |
| **Workflow v3** (plantillas, configuración, borrador/publicado, transiciones editables) | Implementado |
| **Workflow block designer** (036) | Implementado (bloques + compilador + cliente en borrador) |
| Evento `RoutineValidated` → PDF (cola) + borrador factura | Implementado |
| Reportes PDF (Browsershot/Chromium + fallback DomPDF, job `GenerateRoutineReportJob`) | Implementado — ADR-005 |
| Ejecución con `responses`, `consumptions`, **evidencias** | Implementado |
| **Mobile Sync API** (`POST /sync`) | Implementado v1 |
| Facturación borrador / emitir + **módulo** (config, detalle, `docs/BILLING.md`) | Implementado v1 |
| Auditoría append-only + API | Implementado v1 |
| Email pendiente validación (supervisores) | Implementado v1 (Mailpit) |
| **Dashboard summary API** | Implementado |
| **Usuarios empresa (lectura admin)** | Implementado |
| **`phoenix:ensure-demo`** en arranque Docker | Implementado |
| **`phoenix:refresh-demo`** (seed + permisos + credenciales) | Implementado — ver `docs/DEMO_ENV.md` |
| **RBAC global** (rol + `extra_permissions`; menú derivado; ADR-012) | Implementado — cambio 034 |
| **Plantilla de roles (plataforma)** | `GET/PUT /platform/role-permissions`, UI Plataforma — cambio 035 |
| **Tipos de equipo e insumo** (`equipment_types`, `supply_types`, fichas por tipo) | Implementado — cambio 037 |
| **Formularios por uso** (`FormUsage`: rutina, equipo, insumo) | Implementado — cambio 039 |
| **Inventario Dom-G** (seed desde Excel promocional/textil) | Implementado — `DomGInventorySpreadsheetSeeder` |
| **Fase 4 plataforma** (webhooks, automatización, dashboard prefs, insights IA) | Implementado MVP — cambio 040 |
| **Portal cliente** (`/portal/*`, API `portal/*`, rol `client`) | Implementado v1 |
| **Multi-tenant plataforma** (`/platform/tenants`, assume) | Implementado — cambio 036-platform-tenant-admin |
| **Vinculación activo–cliente** por serie | Implementado |
| **Rutina demo** (`POST /routines/demo`, sin rutina en seed) | Implementado |
| **Mantenimiento predictivo** (rutinas aplicadas → riesgo, opt-in entrenamiento, semver de algoritmo, OEM↔catálogo, tools del asistente, `/api/v1/predictive/*`, UI `/app/predictive`) | Implementado — cambio 046, ADR-013; ver [PREDICTIVE_MAINTENANCE.md](PREDICTIVE_MAINTENANCE.md) |
| **Servicio ML** `ml/phoenix-predict` (FastAPI + GBDT, opcional) | Implementado — apagado por defecto, degrada al motor de PHP |

## Frontend

| Pantalla | Estado |
|----------|--------|
| Login, dashboard, **shell glass + nav por flujos** | Implementado |
| **Facturación**: hub, detalle, configuración IVA/mano de obra | Implementado |
| Lista rutina **filtros + alta**, detalle (formulario, validar, PDF) | Implementado |
| Catálogo equipos, insumos, activos, **proveedores**, **sitios** | Implementado |
| Diseño: formularios, reportes, workflows (MVP) | Implementado |
| Tipos de rutina | Implementado |
| Facturación, auditoría, **usuarios (admin, rol + permisos extra)** | Implementado |
| **Portal cliente** (facturas, rutinas, detalle) | Implementado |
| Detalle rutina: **timeline auditoría** + **evidencias fotográficas** | Implementado |
| **Integraciones** (webhooks + automatización) | Implementado — `/app/integrations` |
| **Insights IA** (asistente FAB, OCR/narrativa en rutina y activos) | Implementado — sin ruta `/app/insights` dedicada |
| Dashboard: **widgets configurables** por usuario | Implementado |
| **Tour de inicio** (spotlight + voz MP3 precargada) | Implementado — ver [ONBOARDING_TOUR.md](ONBOARDING_TOUR.md) |

## Pruebas

- Automatizadas: `docker compose exec app php artisan test`
- Ciclo rutina / portal / auditoría: `RoutineLifecycleCycleTest`
- Fase 4 plataforma: `Phase4PlatformCapabilitiesTest`
- Manuales: [PRUEBAS_MANUALES.md](PRUEBAS_MANUALES.md)

## Cambios archivados

Ver [openspec/archive/README.md](../openspec/archive/README.md) (001–042).

# Tareas 032 — Ciclo rutina, portal cliente, trazabilidad

Orden sugerido: **A → B → C → D → E**. Marcar `[x]` al cerrar cada ítem.

---

## Fase A — Workflow v2 y correlación de auditoría

### Dominio y migraciones

- [x] ADR-012 (portal cliente) y ADR-013 o sección en ADR-012 para `correlation_id`.
- [x] Migración: `workflow_instances.correlation_id` (UUID), `audit_entries.correlation_id` + índice `(company_id, correlation_id)`.
- [x] `RoutineStatus::PendingBilling` + actualizar transiciones en código y UI (`StatusBadge`, filtros).
- [x] `WorkflowRuntime::defaultDefinition()` v2 (`billing_review`, `invoice_issued` → `complete`).
- [x] Ajustar `WorkflowDefinitionValidator` para grafo v2 (mantener compatibilidad documentada v1 → v2).
- [x] Comando `phoenix:migrate-workflow-definitions-v2` (empresa o global).
- [x] `onApproved`: transición a `billing_review`, `RoutineStatus::PendingBilling`, disparar `routine_validated` (PDF + borrador) sin `complete`.
- [x] Nuevo `onInvoiceIssued` (o hook post-`issue`) → transición `invoice_issued`, `RoutineStatus::Invoiced`, `complete`.
- [x] Propagar `correlation_id` en `AuditLogger`, transiciones workflow, rechazo, validación, emisión.

### API y UI interna

- [x] Detalle rutina: paso workflow `billing_review`, CTA según rol.
- [x] Timeline / panel auditoría filtrable por `correlation_id` (detalle rutina o modal).
- [x] Tests: `WorkflowRuntimeTest`, flujo rechazo → reenvío, aprobación → pending_billing.

---

## Fase B — Facturación en el flujo

### Modelo y API

- [x] Migración campos entrega en `invoices`: `notify_client_on_issue`, `client_portal_visible`, `delivery_deferred`, `delivered_to_client_at` (y/o JSON `delivery_options` si se prefiere un solo campo).
- [x] `PATCH` prefactura acepta flags; validación coherente al emitir.
- [x] `InvoiceController::issue` exige rutina en `pending_billing` (o paso workflow activo) salvo override admin documentado.
- [x] Tras emitir: invocar transición workflow + job email condicional.

### UI y notificaciones

- [x] `InvoiceDetailPage`: checkboxes pre-emisión (notificar, visible portal, diferir envío).
- [x] Mailable / notification «factura disponible» (enlace portal o adjunto v1).
- [x] Actualizar `WorkflowDesignerPage` textos y layout 4 nodos por defecto.
- [x] Tests: `InvoicePrefacturaApiTest`, emisión con flags, email fake.

---

## Fase C — Vinculación equipo–cliente

- [x] Migración `asset_client_assignments`.
- [x] Modelo + relaciones `Asset`, `Client`.
- [x] API: listar/crear/finalizar asignación (`company.module` catálogo o activos write).
- [x] UI activos: modal vincular cliente + validación serie.
- [x] Tests Feature: serie incorrecta → 422; rutina visible solo con asignación activa (preparación portal).

---

## Fase D — Portal cliente

### Identity

- [x] `MembershipRole::Client` (o equivalente) + permisos `portal.*` en `PhoenixPermission` y bootstrap.
- [x] `company_memberships.client_id` (nullable, FK).
- [x] Seed: `cliente@pyro-systems.com` vinculado a cliente demo con activo asignado.

### API portal

- [x] Rutas `portal/invoices`, `portal/invoices/{id}`, `portal/invoices/{id}/download`.
- [x] Rutas `portal/assets`, `portal/routines`, `portal/routines/{id}` (historial ejecuciones, factura si visible).
- [x] Middleware `RequireClientPortalContext` (usuario con `client_id`, sin `X-Company-Id` alterno).

### Frontend portal

- [x] Router `/portal/*`, layout mínimo, login compartido o redirect si rol cliente.
- [x] Páginas: listado facturas, descarga; listado equipos; detalle rutina + historial.
- [x] Tests Feature: usuario A no ve facturas de usuario B; factura no visible → 404.

---

## Fase E — Rutina demo bajo demanda

- [x] Quitar bloque `Routine::create` de `PhoenixDemoSeeder` (mantener `ensureInstance` solo si queda alguna rutina — no debería).
- [x] Servicio `DemoRoutineFactory`: rutina + ejecución borrador o assigned con `responses` fake + fotos placeholder.
- [x] `POST /api/v1/routines/demo` — solo administrador.
- [x] `routines.is_demo` boolean (opcional métricas).
- [x] `RoutinesPage`: botón «Generar rutina demo» visible solo admin.
- [x] Actualizar `docs/DEMO_ENV.md`, `PRUEBAS_MANUALES.md`, tests comando seed.

---

## Cierre OpenSpec

- [x] Actualizar `openspec/domain/workflows.md` y glosario.
- [x] `docs/IMPLEMENTATION.md` con portal + workflow v2.
- [x] Archivar carpeta a `openspec/archive/032-routine-lifecycle-client-portal/` al mergear.

---

## Checklist de regresión manual (extracto)

- [x] Técnico envía rutina demo generada (`RoutineLifecycleCycleTest`).
- [x] Supervisor rechaza con motivo; técnico corrige y reenvía (`RoutineLifecycleCycleTest`).
- [x] Facturación emite con «visible en portal» sin email; cliente ve y descarga (`RoutineLifecycleCycleTest`).
- [x] Facturación emite con «notificar email»; Mailpit recibe (`RoutineLifecycleCycleTest` + entorno demo).
- [x] Auditoría: un solo `correlation_id` agrupa eventos del ciclo (`RoutineLifecycleCycleTest`).

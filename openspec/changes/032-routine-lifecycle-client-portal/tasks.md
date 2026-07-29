# Tareas 032 — Ciclo rutina, portal cliente, trazabilidad

Orden sugerido: **A → B → C → D → E**. Marcar `[x]` al cerrar cada ítem.

---

## Fase A — Workflow v2 y correlación de auditoría

### Dominio y migraciones

- [ ] ADR-012 (portal cliente) y ADR-013 o sección en ADR-012 para `correlation_id`.
- [ ] Migración: `workflow_instances.correlation_id` (UUID), `audit_entries.correlation_id` + índice `(company_id, correlation_id)`.
- [ ] `RoutineStatus::PendingBilling` + actualizar transiciones en código y UI (`StatusBadge`, filtros).
- [ ] `WorkflowRuntime::defaultDefinition()` v2 (`billing_review`, `invoice_issued` → `complete`).
- [ ] Ajustar `WorkflowDefinitionValidator` para grafo v2 (mantener compatibilidad documentada v1 → v2).
- [ ] Comando `phoenix:migrate-workflow-definitions-v2` (empresa o global).
- [ ] `onApproved`: transición a `billing_review`, `RoutineStatus::PendingBilling`, disparar `routine_validated` (PDF + borrador) sin `complete`.
- [ ] Nuevo `onInvoiceIssued` (o hook post-`issue`) → transición `invoice_issued`, `RoutineStatus::Invoiced`, `complete`.
- [ ] Propagar `correlation_id` en `AuditLogger`, transiciones workflow, rechazo, validación, emisión.

### API y UI interna

- [x] Detalle rutina: paso workflow `billing_review`, CTA según rol.
- [x] Timeline / panel auditoría filtrable por `correlation_id` (detalle rutina o modal).
- [ ] Tests: `WorkflowRuntimeTest`, flujo rechazo → reenvío, aprobación → pending_billing.

---

## Fase B — Facturación en el flujo

### Modelo y API

- [ ] Migración campos entrega en `invoices`: `notify_client_on_issue`, `client_portal_visible`, `delivery_deferred`, `delivered_to_client_at` (y/o JSON `delivery_options` si se prefiere un solo campo).
- [ ] `PATCH` prefactura acepta flags; validación coherente al emitir.
- [ ] `InvoiceController::issue` exige rutina en `pending_billing` (o paso workflow activo) salvo override admin documentado.
- [ ] Tras emitir: invocar transición workflow + job email condicional.

### UI y notificaciones

- [ ] `InvoiceDetailPage`: checkboxes pre-emisión (notificar, visible portal, diferir envío).
- [ ] Mailable / notification «factura disponible» (enlace portal o adjunto v1).
- [ ] Actualizar `WorkflowDesignerPage` textos y layout 4 nodos por defecto.
- [ ] Tests: `InvoicePrefacturaApiTest`, emisión con flags, email fake.

---

## Fase C — Vinculación equipo–cliente

- [ ] Migración `asset_client_assignments`.
- [ ] Modelo + relaciones `Asset`, `Client`.
- [ ] API: listar/crear/finalizar asignación (`company.module` catálogo o activos write).
- [ ] UI activos: modal vincular cliente + validación serie.
- [ ] Tests Feature: serie incorrecta → 422; rutina visible solo con asignación activa (preparación portal).

---

## Fase D — Portal cliente

### Identity

- [ ] `MembershipRole::Client` (o equivalente) + permisos `portal.*` en `PhoenixPermission` y bootstrap.
- [ ] `company_memberships.client_id` (nullable, FK).
- [ ] Seed: `cliente@pyro-systems.com` vinculado a cliente demo con activo asignado.

### API portal

- [ ] Rutas `portal/invoices`, `portal/invoices/{id}`, `portal/invoices/{id}/download`.
- [ ] Rutas `portal/assets`, `portal/routines`, `portal/routines/{id}` (historial ejecuciones, factura si visible).
- [ ] Middleware `RequireClientPortalContext` (usuario con `client_id`, sin `X-Company-Id` alterno).

### Frontend portal

- [ ] Router `/portal/*`, layout mínimo, login compartido o redirect si rol cliente.
- [x] Páginas: listado facturas, descarga; listado equipos; detalle rutina + historial.
- [ ] Tests Feature: usuario A no ve facturas de usuario B; factura no visible → 404.

---

## Fase E — Rutina demo bajo demanda

- [ ] Quitar bloque `Routine::create` de `PhoenixDemoSeeder` (mantener `ensureInstance` solo si queda alguna rutina — no debería).
- [ ] Servicio `DemoRoutineFactory`: rutina + ejecución borrador o assigned con `responses` fake + fotos placeholder.
- [ ] `POST /api/v1/routines/demo` — solo administrador.
- [ ] `routines.is_demo` boolean (opcional métricas).
- [ ] `RoutinesPage`: botón «Generar rutina demo» visible solo admin.
- [x] Actualizar `docs/DEMO_ENV.md`, `PRUEBAS_MANUALES.md`, tests comando seed.

---

## Cierre OpenSpec

- [ ] Actualizar `openspec/domain/workflows.md` y glosario.
- [x] `docs/IMPLEMENTATION.md` con portal + workflow v2.
- [ ] Archivar carpeta a `openspec/archive/032-routine-lifecycle-client-portal/` al mergear.

---

## Checklist de regresión manual (extracto)

- [ ] Técnico envía rutina demo generada.
- [ ] Supervisor rechaza con motivo; técnico corrige y reenvía.
- [ ] Facturación emite con «visible en portal» sin email; cliente ve y descarga.
- [ ] Facturación emite con «notificar email»; Mailpit recibe (entorno demo).
- [ ] Auditoría: un solo `correlation_id` agrupa >10 eventos del ciclo.

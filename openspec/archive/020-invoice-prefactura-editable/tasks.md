# Tareas 020 — Prefactura editable

## Modelo y migraciones

- [ ] `invoice_lines.line_type`, `sort_order`, `source_consumption_id`, `metadata` (JSONB)
- [ ] `invoices.tax_rate_snapshot`, `invoices.client_id` (si 019 no llegó antes)
- [ ] Enum `InvoiceLineType`

## Servicios

- [ ] Refactor `InvoiceDraftService` → solo líneas sugeridas + tipos
- [ ] `InvoiceDraftEditor` / `InvoiceTotalsCalculator`
- [ ] Validaciones draft vs issued

## Permisos

- [ ] `billing.draft.edit` en catálogo y roles Facturación + Administrador
- [ ] Middleware en rutas de edición

## API

- [ ] `PUT /billing/invoices/{id}/draft` (líneas + cliente opcional)
- [ ] Ampliar `GET` detalle factura
- [ ] Tests Feature + unitarios totales

## UI

- [ ] `InvoiceDetailPage`: editor si `draft`, lectura si `issued`
- [ ] UX agregar/quitar líneas MO e insumos
- [ ] Textos de ayuda en `BillingSettingsPage`

## Cierre

- [ ] `docs/BILLING.md`, pruebas manuales
- [ ] Archivar `openspec/changes/020-invoice-prefactura-editable/`

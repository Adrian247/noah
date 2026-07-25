# Facturación — Noah

## Qué hace el módulo

Al **validar** una rutina se crea un **borrador de factura** (`Invoice` en estado `draft`) con líneas **sugeridas** desde la ejecución. El usuario de facturación edita la **prefactura** antes de **emitir**.

## Prefactura (borrador editable)

| Acción | Permiso |
|--------|---------|
| Ver listado y detalle | Miembro de la empresa |
| **Editar** líneas, cliente, precios | `billing.draft.edit` |
| **Emitir** | `billing.issue` (requiere **cliente** asignado) |

### Tipos de línea

| Tipo | Origen |
|------|--------|
| `supply` | Consumos de la ejecución (precio unitario editable solo en esta factura) |
| `labor` | Sugerida desde duración × tarifa empresa, o líneas manuales (personas, horas, $/h) |
| `other` | Conceptos adicionales |

### Totales

```
line_total = cantidad × precio_unitario (por línea)
subtotal = Σ line_total
IVA = subtotal × tax_rate_snapshot
total = subtotal + IVA
```

`tax_rate_snapshot` se fija al crear/actualizar el borrador (tasa de empresa en ese momento).

## Cliente de facturación

Catálogo **Clientes** (`clients.manage` / `clients.view`). Obligatorio en prefactura antes de emitir.

## Configuración por empresa

En **Facturación → Configuración** (`billing.settings`):

- **Tarifa sugerida mano de obra** — prellena líneas MO al crear borrador; `0` = sin línea MO automática.
- **Tasa IVA** — usada en snapshot del borrador.

Fallback: `NOAH_BILLING_LABOR_RATE`, `NOAH_BILLING_TAX_RATE` en `config/noah.php`.

## API

- `GET/PUT /billing/settings`
- `GET /billing/invoices`, `GET /billing/invoices/{id}`
- `PUT /billing/invoices/{id}/draft` — prefactura
- `POST /billing/invoices/{id}/issue`
- `GET/POST/PUT/DELETE /clients`

## Emitir

`POST /api/v1/billing/invoices/{id}/issue` asigna número, marca `issued` y la rutina pasa a estado facturado.

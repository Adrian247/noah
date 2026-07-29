# Facturación — Phoenix

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

### Referencia personalizada

Campo opcional `custom_reference` (máx. 128 caracteres) en la prefactura. Sirve para identificar la factura ante el cliente (proyecto, OC, etc.) además del folio interno y del `id`. Aparece en el PDF, listados, correo de emisión y en el nombre del paquete ZIP.

### Evidencias y factura SAT

En prefactura se pueden adjuntar:

- **Evidencias de respaldo** (`supporting`) — imágenes o documentos.
- **Reporte de inspección** (`routine_report`) — PDF generado al validar la rutina (se incluye en `reportes/` del ZIP).
- **Factura SAT (CFDI)** (`sat_cfdi`) — un solo archivo activo por factura (XML/PDF).

Adjuntar reporte: `POST /billing/invoices/{id}/evidences` con `kind=routine_report` y `generated_report_id` (reporte en estado `ready` de la misma rutina).

## Entrega al cliente (emitida)

Al habilitar **visible en portal** y/o **notificar por email**, el artefacto entregado es un **ZIP** que incluye:

- PDF actual de la prefactura/factura emitida.
- Carpeta `reportes/` con informes de rutina adjuntos.
- Carpeta `evidencias/` con respaldos.
- Carpeta `sat/` con el CFDI si existe.

Descargas:

- Staff: `GET /billing/invoices/{id}/package` (factura emitida).
- Portal cliente: `GET /portal/invoices/{id}/download` (misma estructura, solo si `client_portal_visible`).

El adjunto del correo `ClientInvoiceIssuedMail` usa el mismo paquete ZIP.


En **Facturación → Configuración** (`billing.settings`):

- **Tarifa sugerida mano de obra** — prellena líneas MO al crear borrador; `0` = sin línea MO automática.
- **Tasa IVA** — usada en snapshot del borrador.

Fallback: `PHOENIX_BILLING_LABOR_RATE`, `PHOENIX_BILLING_TAX_RATE` en `config/phoenix.php`.

## API

- `GET/PUT /billing/settings`
- `GET /billing/invoices`, `GET /billing/invoices/{id}`
- `PUT /billing/invoices/{id}/draft` — prefactura (líneas, cliente, `custom_reference`, flags de entrega)
- `POST /billing/invoices/{id}/issue`
- `POST /billing/invoices/{id}/deliver` — entrega al cliente tras emisión (email y/o portal; auditoría en el hilo del workflow)
- `GET /billing/invoices/{id}/package` — ZIP de entrega (emitidas)
- Evidencias: `GET/POST/DELETE /billing/invoices/{id}/evidences`, descarga por evidencia
- `GET/POST/PUT/DELETE /clients`
- Portal: `GET /portal/invoices`, `GET /portal/invoices/{id}/download` (ZIP)

## Emitir

`POST /api/v1/billing/invoices/{id}/issue` asigna número, marca `issued` y la rutina pasa a estado facturado.

# Integraciones — Phoenix

## Estado

**MVP Fase 4 implementado** — webhooks salientes y reglas de automatización operativa.

## Webhooks salientes

| Evento | Cuándo se dispara |
|--------|-------------------|
| `routine.validated` | Supervisor valida servicio |
| `routine.rejected` | Supervisor rechaza servicio |
| `invoice.issued` | Emisión de factura |
| `inventory.low_stock` | Movimiento deja insumo bajo mínimo |
| `*` | Todos los eventos (suscripción comodín) |

**Entrega HTTP POST** con cuerpo JSON:

```json
{
  "event": "routine.validated",
  "occurred_at": "2026-08-01T12:00:00+00:00",
  "data": { "routine_id": 1, "status": "validated", "asset_tag": "TAG-001" }
}
```

Cabeceras: `X-Phoenix-Event`, `X-Phoenix-Delivery`, `X-Phoenix-Signature` (HMAC-SHA256 del cuerpo con el secreto de la suscripción).

Destinos recomendados: endpoint propio, n8n, Zapier/Make o herramientas de inspección como webhook.site. Cada consumidor transforma el JSON a su formato (Slack, Teams, ERP, etc.).

API: `GET/POST/PUT/DELETE /api/v1/integrations/webhooks` (módulo `integrations`).

## Automatización

Reglas por empresa con `trigger_type` (mismos eventos) y acciones JSON:

- `log` — registro en log de aplicación
- `webhook` — reenvío vía `WebhookDispatcher`

API: `GET/POST/PUT/DELETE /api/v1/automation/rules`.

## Principio

Integraciones viven en adapters/servicios (`OperationalEventBridge`, `WebhookDispatcher`); agregados de dominio no dependen de URLs externas.

## Candidatos futuros

| Sistema | Propósito |
|---------|-----------|
| Proveedor PAC / SAT | Timbrado fiscal |
| ERP contable | Exportación de facturas |
| SSO corporativo | SAML/OIDC |

## Referencias

- Cambio OpenSpec: `openspec/archive/040-phase-4-platform/`
- Eventos de dominio: [domain-events.md](domain-events.md)

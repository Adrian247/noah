# 016 — Módulo de facturación (configuración y visibilidad)

## Problema

El cálculo de borradores (insumos, IVA, mano de obra opcional) existía en backend pero **no había módulo visible**: el usuario no sabía qué reglas aplicaban ni cómo ajustarlas por empresa.

## Objetivo

- Pantalla **Facturación** como hub: listado, detalle de borrador, desglose subtotal/IVA/líneas.
- **Configuración por empresa**: tarifa hora mano de obra, tasa IVA (admin/facturación).
- API `GET/PUT /billing/settings` y documentación de reglas de cálculo.
- El servicio `InvoiceDraftService` prioriza valores de empresa sobre `config/noah.php`.

## Alcance v1

- No PAC / XML fiscal.
- No edición manual de líneas en UI (solo lectura + emitir).

## Criterios de aceptación

- Admin o facturación puede ver y editar tarifas.
- Supervisor ve facturas en detalle de rutina pero no emite ni configura.
- Documento `docs/BILLING.md` explica la fórmula.

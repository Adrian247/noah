# Integraciones — Phoenix

## Estado

Placeholder — sin integraciones activas en fase documental.

## Principio

Phoenix no depende de sistemas externos para su MVP. Cualquier integración futura:

1. Se documenta aquí con contrato (API, eventos, datos).
2. Vive detrás de adapters en el monolito.
3. No introduce acoplamiento en agregados de Maintenance o Billing.

## Candidatos futuros (no comprometidos)

| Sistema | Propósito |
|---------|-----------|
| Proveedor PAC / SAT | Timbrado fiscal (México u otro país) |
| ERP contable | Exportación de facturas |
| Email transaccional | Envío de reportes y facturas |
| SSO corporativo | SAML/OIDC |

## Referencias cruzadas

Ninguna integración con otros productos internos está planificada; Phoenix es dominio propio.

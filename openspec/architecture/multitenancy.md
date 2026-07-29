# Multitenancy — Phoenix

ADR: [ADR-009](../decisions/ADR-009-multitenancy.md).

## Modelo

- **Shared database, shared schema**, discriminador `company_id` en tablas de negocio.
- Tablas globales mínimas (users) con membresías.

## Aislamiento

- Global scope Eloquent o middleware que inyecta `company_id` en queries.
- Tests de regresión: usuario A no lee datos de empresa B.

## Identificación de tenant

| Fase | Mecanismo |
|------|-----------|
| Piloto | Subdominio opcional o solo selector post-login |
| SaaS | `{tenant}.phoenix.app` o dominio custom CNAME |

## Límites por plan (futuro)

- Usuarios, activos, almacenamiento IA — enforcement en aplicación (Pennant o tabla `company_limits`).

## Datos compartidos

- Catálogo de tipos de campo y componentes de reporte: código de plataforma, no por tenant.
- Definiciones de formulario/reporte: por tenant.

## Backup y export

- Export por empresa para portabilidad (GDPR-style delete/export — fase posterior).

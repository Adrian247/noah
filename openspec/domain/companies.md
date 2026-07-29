# Dominio — Companies (Phoenix)

## Responsabilidad

Tenant (empresa), sitios físicos/lógicos y configuración organizacional.

## Agregados

### Company

- Nombre legal, RFC o identificador fiscal (opcional hasta billing).
- Configuración: moneda por defecto, zona horaria, logo (ref storage).
- Estado: activa, suspendida.

### Site

- Pertenece a una empresa.
- Nombre, dirección, geolocalización opcional.
- Activos y rutinas se asocian a sitio.

## Multitenancy

Todo recurso de negocio incluye `company_id`. Queries sin scope de empresa son prohibidas en aplicación.

## Invariantes

- No eliminar empresa con datos operativos sin proceso de archivado (soft-delete).
- Sitio no compartido entre empresas.

## Eventos

- `CompanyCreated`, `SiteCreated`, `CompanySettingsUpdated`

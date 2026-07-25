# 019 — Módulo de clientes (catálogo comercial)

## Problema

Noah modela **empresa operadora**, sitios, activos y proveedores, pero no el **cliente al que se factura** el servicio de mantenimiento. En [billing.md](../../domain/billing.md) la factura prevé “cliente (referencia futura)”; hoy `invoices` solo enlazan `routine_id` sin receptor de cobro.

Sin catálogo de clientes:

- No hay datos fiscales/comerciales reutilizables (razón social, RFC, dirección).
- No se puede asignar un destinatario al borrador ni a la rutina de forma consistente.
- Reportes y plantillas que usan `{{cliente}}` carecen de fuente de verdad.

## Objetivo

- **Mantenedor de clientes** por empresa (multi-tenant), CRUD completo en v1.
- Acceso restringido a quien tenga permiso **`clients.manage`** (por defecto solo **Administrador**; asignable vía roles Spatie).
- Base para vincular **borrador de factura** y, opcionalmente, **rutinas** al cliente de cobro (enlace en propuesta **020**).

## Alcance del dominio

### Agregado `Client` (nombre técnico: `Client` / tabla `clients`)

| Campo | Uso |
|-------|-----|
| `company_id` | Tenant |
| `code` | Código interno opcional (único por empresa) |
| `legal_name` | Razón social / nombre fiscal |
| `trade_name` | Nombre comercial (UI) |
| `tax_id` | RFC u identificador fiscal (país-agnóstico en v1) |
| `billing_email` | Contacto facturación |
| `billing_address` | Dirección fiscal (texto o JSON estructurado en v2) |
| `currency` | Opcional; default moneda empresa |
| `is_active` | Baja lógica |
| `notes` | Observaciones internas |

**No** es el “cliente final” del portal de consulta ([target-users.md](../../vision/target-users.md)); es el **cliente de facturación** de la empresa mantenedora.

### Relaciones (v1 / v1.1)

| Relación | v1 | v1.1 (recomendado en misma entrega si hay tiempo) |
|----------|----|-----------------------------------------------------|
| `invoices.client_id` | Opcional nullable | Obligatorio antes de **emitir** (regla de negocio) |
| `routines.client_id` | — | Default al crear borrador de factura |

## Permisos y autorización

| Permiso | Descripción | Roles plantilla v1 |
|---------|-------------|-------------------|
| `clients.manage` | CRUD clientes | Administrador |
| `clients.view` | Solo lectura (selector en facturación) | Facturación (opcional v1.1) |

- Rutas API bajo middleware `company` + `company.permission:clients.manage` (mutaciones y listado completo).
- Lectura para selectores: `clients.view` o `clients.manage`.
- Menú: **Catálogos → Clientes** (o **Administración → Clientes** si se prefiere separar de operación; recomendación: **Catálogos**, junto a proveedores).

## API propuesta (`/api/v1`)

| Método | Ruta | Permiso |
|--------|------|---------|
| GET | `/clients` | `clients.view` \| `clients.manage` |
| GET | `/clients/{client}` | idem |
| POST | `/clients` | `clients.manage` |
| PUT | `/clients/{client}` | `clients.manage` |
| DELETE | `/clients/{client}` | `clients.manage` (soft: `is_active=false` preferido) |

Respuestas JSON con paginación/búsqueda por `legal_name`, `code`, `tax_id`.

## UI (Vue)

- Lista con búsqueda, estado activo/inactivo.
- Formulario crear/editar (validación RFC formato laxo en v1).
- Sin importación masiva ni integración PAC en v1.

## Auditoría

- `client.created`, `client.updated`, `client.deactivated` en `audit_entries`.

## Criterios de aceptación

1. Solo usuarios con `clients.manage` (o administrador con ese permiso) pueden crear/editar/desactivar.
2. Supervisor/técnico reciben 403 en mutaciones.
3. Clientes de empresa A no visibles con `X-Company-Id` de B.
4. Tests feature CRUD + permisos.
5. Glosario actualizado con **Cliente (facturación)**.

## Fuera de alcance v1

- Portal “cliente final”.
- Múltiples contactos, contratos marco, listas de precios por cliente.
- Sincronización con ERP externo.

## Dependencias

- RBAC Spatie ([ADR-011](../../decisions/ADR-011-rbac-spatie-teams.md)): registrar permisos y asignar a rol Administrador en `CompanyAuthorizationService`.
- Opcional coordinación con **020** para `client_id` en factura.

## Documentación a actualizar al implementar

- `openspec/domain/billing.md` — referencia `Client`.
- Nuevo `openspec/domain/clients.md` (o sección en catálogos).
- `docs/PRUEBAS_MANUALES.md` — sección clientes.

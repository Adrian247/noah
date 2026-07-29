# Dominio — Identity (Phoenix)

## Responsabilidad

Autenticación, identidad de usuario y asignación de roles por empresa. No contiene lógica de rutinas ni facturación.

## Agregados

### User

- Email único global (o único por tenant según decisión de login — recomendado: email global, membresías por empresa).
- Estado: activo, invitado, suspendido.
- Preferencias de locale y zona horaria.

### Membership

- `user_id`, `company_id`, conjunto de roles.
- Un usuario puede pertenecer a varias empresas (selector de contexto en UI).

### Role

- Nombre, descripción, lista de permisos (referencia o tabla pivote).

## Flujos

- Invitación por email → aceptación → membresía activa.
- Login → token → contexto `company_id` seleccionado o último usado.

## Invariantes

- Toda acción de negocio exige usuario autenticado y membresía en la empresa del recurso.
- Permisos se evalúan en capa de aplicación (policies), no solo en UI.

## Eventos

- `UserInvited`, `UserActivated`, `MembershipGranted`, `MembershipRevoked`

## Fuera de alcance

- SSO SAML/OIDC — fase posterior ([integrations.md](../architecture/integrations.md)).

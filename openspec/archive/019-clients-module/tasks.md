# Tareas 019 — Módulo de clientes

## Dominio y datos

- [ ] Migración `clients` + modelo `Client` (`BelongsToCompany`)
- [ ] Permisos `clients.manage`, `clients.view` en `PhoenixPermission` y mapa de roles
- [ ] `phoenix:bootstrap-permissions` / seeder actualiza roles

## API

- [ ] `ClientController` CRUD + validación
- [ ] Rutas con `company.permission`
- [ ] Auditoría en mutaciones
- [ ] Tests Feature (admin OK, supervisor 403)

## UI

- [ ] `ClientsPage.vue` (patrón `SuppliersPage`)
- [ ] Ruta `/app/catalog/clients`, icono en `AppShell`
- [ ] Visibilidad menú según `clients.manage` o `clients.view`

## Integración facturación (mínima)

- [ ] Migración `invoices.client_id` nullable (FK)
- [ ] Mostrar selector de cliente en detalle de borrador (solo si 020 no lo cubre antes)

## Cierre

- [ ] Glosario + pruebas manuales
- [ ] Archivar a `openspec/archive/019-clients-module/`

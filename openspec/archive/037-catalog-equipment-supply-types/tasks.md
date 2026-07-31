# 037 — Tareas

## Fase 0 — Referencias

- [x] Intención producto: `references/intencion-producto.md`.
- [x] Ficha L200 2018.
- [x] Catálogo insumos normalizado + formulario vehículo normalizado.

## Fase 1 — Backend tipos de equipo

- [x] Migración `equipment_types` (company_id, code, name, description, default_form_definition_id nullable, sort_order).
- [x] Modelo `EquipmentType`, relación `hasMany` CatalogItem.
- [x] `EquipmentTypeController` CRUD + política/permisos `catalog.manage`.
- [x] Migración `catalog_items.equipment_type_id` (restrict on delete).
- [x] Validación en destroy si hay `catalog_items` referenciando.
- [x] Rutas API bajo `/catalog/equipment-types`.
- [x] Tests feature CRUD + delete bloqueado.

## Fase 2 — Backend tipos de insumo

- [x] Migración `supply_types` (misma forma que equipment_types sin form).
- [x] Modelo `SupplyType`, relación `hasMany` SupplyItem.
- [x] `SupplyTypeController` CRUD.
- [x] Migración `supply_items.supply_type_id`.
- [x] Rutas `/catalog/supply-types`.
- [x] Tests feature.

## Fase 3 — Extender CRUD equipos e insumos

- [x] `CatalogItemController`: validar `equipment_type_id`, incluir tipo en JSON index/show.
- [x] `SupplyItemController`: validar `supply_type_id`, incluir tipo en JSON.
- [x] Actualizar tests existentes de catálogo/insumos.

## Fase 4 — Frontend

- [x] `EquipmentTypesPage.vue` — CRUD completo.
- [x] `SupplyTypesPage.vue` — CRUD completo.
- [x] `CatalogItemsPage.vue` — selector tipo de equipo, columna tipo, filtro.
- [x] `SuppliesPage.vue` — selector tipo de insumo, columna tipo, filtro.
- [x] `AppShell` + `router/index.ts` — rutas y navegación.
- [x] `useModuleAccess` map de rutas si aplica.

## Fase 5 — Formulario normalizado tipo Vehículo

- [x] Implementar esquema en `references/formulario-vehiculo-normalizado.md` → `FormDefinition` slug `inspeccion-vehiculo-v1`, publicar v1.
- [x] Reutilizar catálogos de opciones del seeder donde aplique.
- [x] `equipment_types.vehiculo.default_form_definition_id` → nuevo formulario.
- [x] Tipo de rutina demo → `FormVersion` normalizada; reporte demo alinear campos clave o nota en docs.
- [x] Actualizar `VehicleDemoFormResponses` y tests de ejecución de rutina.
- [x] `docs/PRUEBAS_MANUALES.md` — flujo con formulario normalizado + L200.

## Fase 6 — Demo seed

- [x] Sembrar tipos de equipo: Vehículo, Motores, Bombas.
- [x] Sembrar tipos/ítems según `catalogo-insumos-normalizado.md` (`specifications` marca/OEM).
- [x] CatalogItem **VEH-L200-2018** + `specifications` de la ficha.
- [x] Asset **L200-2018-DEMO**.
- [x] Cuatro `supply_items` de la ficha con `supply_type_id`; migrar/retirar `FIL-ACE-PREM` duplicado.
- [x] Ejecutar `phoenix:refresh-demo` y verificar credenciales en `docs/DEMO_ENV.md`.

## Fase 7 — Documentación y cierre

- [x] Actualizar `openspec/domain/catalogs.md` y glosario.
- [x] Actualizar `docs/IMPLEMENTATION.md`.
- [x] Marcar tareas y archivar change a `openspec/archive/037-...` al merge.

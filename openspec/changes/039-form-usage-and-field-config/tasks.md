# 039 — Tareas

## Fase 1 — Dominio y migraciones

- [x] Enum `App\Enums\FormUsage` (`routine`, `equipment`, `supply`).
- [x] Migración `form_definitions.usage` (NOT NULL tras backfill, default temporal `routine`).
- [x] Backfill: rutina por defecto; formularios referenciados por `equipment_types.default_form_definition_id` → `equipment`.
- [x] Migración `supply_types.default_form_definition_id` (FK `form_definitions`, restrict).
- [x] Actualizar `FormDefinition` (`fillable`, cast a enum).
- [x] Actualizar `SupplyType` (fillable, relación `defaultFormDefinition`).
- [x] Documentación: `forms-engine.md`, `catalogs.md`, glosario.

## Fase 2 — API formularios

- [x] `POST /design/forms`: validar `usage` requerido.
- [x] Index/show: incluir `usage` (y label opcional).
- [x] Index: filtro query `usage` (opcional, para selectores).
- [x] `DELETE /design/forms/{form}`: comprobar referencias (routine_types, equipment_types, supply_types, ejecuciones/versiones en uso).
- [x] Auditoría `form.deleted`.
- [x] Tests `FormDesignerApiTest` / nuevo `FormDefinitionDeleteTest`.

## Fase 3 — API tipos de catálogo

- [x] `EquipmentTypeController`: validar `default_form_definition_id` con `usage = equipment` y misma empresa.
- [x] `SupplyTypeController`: aceptar `default_form_definition_id` con `usage = supply`.
- [x] JSON index/show con `default_form_definition` anidado (paridad con equipos).
- [x] Tests en `EquipmentSupplyTypesApiTest` (o archivo dedicado).

## Fase 4 — UI formularios

- [x] `FormsListPage`: badge/columna uso; alta con `MaterialSelect` de uso.
- [x] Eliminar con confirmación y manejo de 422.
- [x] `RoutineTypesPage`: filtrar catálogo de formularios por `usage = routine`.
- [x] `FormDesignerPage` / cabecera: mostrar uso (solo lectura).

## Fase 5 — UI configuración de campos

- [x] Reestructurar `FormFieldConfigPage` en secciones **Imágenes** y **Catálogo de opciones**.
- [x] Encabezado de sección catálogos + botón **Nuevo catálogo** a la derecha.
- [x] Lista colapsable (acordeón); detalle actual al expandir.
- [x] Creación de catálogo vía modal o panel dedicado (consistente con otros CRUD).

## Fase 6 — UI tipos de equipo e insumo

- [x] `EquipmentTypesPage`: `MaterialSelect` de formulario (`usage=equipment`) en modal.
- [x] `SupplyTypesPage`: columna + selector formulario (`usage=supply`).
- [x] Cargar opciones vía API filtrada o filtro cliente documentado.

## Fase 7 — Demo y cierre

- [x] Ajustar `NoahDemoSeeder` (usos correctos en formularios demo).
- [x] `php artisan noah:refresh-demo` y verificar tipos L200 / rutina demo.
- [ ] Actualizar `docs/IMPLEMENTATION.md` o `PRUEBAS_MANUALES.md` (crear con uso, eliminar, tipos).
- [ ] Revisar copy del tour 038 si menciona creación de formularios (opcional).

# 039 — Uso de formulario (Rutina / Equipo / Insumo), eliminación y UX de configuración de campos

## Contexto

Hoy los **formularios** (`form_definitions`) son genéricos: al crearlos solo se indica nombre (y slug implícito). No hay distinción de **para qué contexto de negocio** sirven, lo que dificulta enlazarlos de forma segura a **tipos de rutina**, **tipos de equipo** o **tipos de insumo**.

En catálogo, el tipo de equipo ya tiene columna `default_form_definition_id` (037), pero la UI de **Tipos de equipo** aún no permite elegir formulario en el modal; **tipos de insumo** no tienen análogo.

La **configuración de campos** (`/app/design/forms/settings`, change 023) mezcla imágenes y catálogos de opciones en un flujo largo: todos los catálogos se muestran expandidos y el botón de alta no sigue el patrón “acción principal a la derecha del encabezado” usado en equipos, insumos y tipos.

No existe **eliminar** un formulario desde el listado de Diseño → Formularios.

## Objetivo

1. Al **crear** un formulario, exigir el **uso** (tipo de contexto): **Rutina**, **Equipo** o **Insumo** (v1 cerrado a estos tres).
2. Permitir **eliminar** formularios con reglas de integridad claras.
3. Reorganizar **Configuración de campos** en secciones (**Imágenes**, **Catálogo de opciones**) y mejorar la UX del listado de catálogos (colapsable + CTA alineado).
4. Completar CRUD de **tipos de equipo** e **insumo** para asociar un formulario del uso correspondiente.

## Alcance funcional

### 1. Uso del formulario (`form_usage`)

| Valor almacenado | Etiqueta UI | Consumidores previstos |
|------------------|-------------|-------------------------|
| `routine` | Rutina | Tipos de rutina (`routine_types.form_version_id` → definición con este uso) |
| `equipment` | Equipo | Tipos de equipo (`equipment_types.default_form_definition_id`) |
| `supply` | Insumo | Tipos de insumo (`supply_types.default_form_definition_id`) |

**Modelo**

- Migración: columna `usage` en `form_definitions` (`string`, índice por `company_id` + `usage`).
- Enum PHP `FormUsage` (`Routine`, `Equipment`, `Supply`) con valores snake en BD.
- **Inmutable después de crear** en v1 (evita referencias cruzadas ambiguas); cambio futuro vía duplicar formulario.
- **Backfill** en migración: formularios existentes → `routine`, salvo los enlazados como `default_form_definition_id` de un tipo de equipo → `equipment`; si en demo hay formulario solo de insumos, asignar `supply` manualmente en seeder.

**API**

- `POST /design/forms`: body incluye `usage` (required, enum).
- `GET /design/forms` y `GET /design/forms/{id}`: devuelven `usage` y etiqueta legible.
- Opcional v1: query `?usage=equipment` en index para poblar selectores sin filtrar en cliente.

**UI — listado y alta**

- `FormsListPage`: columna o badge de uso; modal/panel de creación con `MaterialSelect` de uso (obligatorio).
- `RoutineTypesPage`: selector de versión de formulario solo sobre definiciones con `usage = routine` (y versión publicada, como hoy).
- Diseñador de formulario: mostrar uso en cabecera (solo lectura).

### 2. Eliminar formulario

**API:** `DELETE /design/forms/{form}` con permiso `design_forms` write y rol diseñador (mismo criterio que publicar).

**Bloqueos (422, mensaje en español):**

| Referencia | Acción |
|------------|--------|
| `routine_types` cuya `form_version` pertenece a la definición | No eliminar |
| `equipment_types.default_form_definition_id` | No eliminar |
| `supply_types.default_form_definition_id` | No eliminar |
| Ejecuciones / respuestas históricas ligadas a versiones de la definición | No eliminar (integridad operativa) |

Si no hay bloqueos: eliminar en cascada controlada **versiones** (`form_versions`) y la definición; auditar `form.deleted`.

**UI:** acción **Eliminar** en fila del listado (confirmación); deshabilitar o ocultar si API devuelve `in_use` en un endpoint de prechequeo opcional `GET .../forms/{id}/delete-impact` (nice-to-have; si no, manejar error 422).

### 3. Configuración de campos — UX

Ruta sin cambio: `/app/design/forms/settings`.

**Estructura de página**

1. **Sección Imágenes** — panel actual (tamaño máximo KB, MIME, guardar). Título de sección visible; sin mezclar con catálogos.
2. **Sección Catálogo de opciones** — encabezado de sección + **botón “Nuevo catálogo”** alineado a la **derecha** (mismo layout que `PageHeader` + `AppButton` en `EquipmentTypesPage` / `CatalogItemsPage`).

**Listado de catálogos**

- Cada catálogo = **fila colapsada** (nombre, slug, contador de opciones, acciones Editar / Eliminar en la fila).
- **Click** en la fila (o en chevron) **expande** el detalle actual: nombre editable, tabla valor / nombre / descripción, filas añadir/quitar, Guardar / Cancelar.
- Solo un catálogo expandido a la vez (acordeón) para reducir ruido visual.
- El formulario inline de **crear** catálogo puede abrirse en modal o panel expandible bajo el botón “Nuevo catálogo” (preferir modal si coincide con otros CRUD).

**Sin cambio funcional** de API 023 salvo ajustes menores de presentación; mismos endpoints de catálogos y settings.

### 4. Tipo de equipo — formulario asociado

**Backend (ya parcial en 037):** validar en store/update que `default_form_definition_id`, si viene, pertenezca a la empresa actual y `form_definitions.usage = equipment`.

**UI `EquipmentTypesPage`:**

- `MaterialSelect` en modal crear/editar: “Formulario de ficha (equipo)”, opciones desde `GET /design/forms?usage=equipment` (solo definiciones; usar versión publicada como hint en subtítulo si aplica).
- Mantener columna en tabla.

### 5. Tipo de insumo — formulario asociado

**Backend**

- Migración: `supply_types.default_form_definition_id` nullable, FK `form_definitions`, `nullOnDelete` o `restrict` (alinear con equipos: **restrict** + mensaje al borrar formulario en uso).
- `SupplyType` fillable + relación `defaultFormDefinition`.
- `SupplyTypeController`: validación `usage = supply` en el formulario referenciado; incluir relación en index/show.

**UI `SupplyTypesPage`:**

- Columna “Formulario de ficha (insumo)”.
- Selector en modal igual que tipos de equipo, filtrado `usage=supply`.

**Demo:** opcional en seeder asignar formulario de insumo si existe plantilla demo; no obligatorio para cerrar 039.

## Navegación y permisos

- Sin módulos nuevos: `design_forms`, `catalog_items`, `catalog_supplies` según pantalla.
- Tour/onboarding: actualizar paso de formularios si se menciona “crear sin tipo” (tarea menor en 038 o nota en tasks).

## Modelo de dominio y documentación

Actualizar:

- [`openspec/architecture/forms-engine.md`](../../architecture/forms-engine.md) — dimensión **uso** de la definición.
- [`openspec/domain/catalogs.md`](../../domain/catalogs.md) — formulario por defecto en tipo de insumo.
- [`openspec/glossary/ubiquitous-language.md`](../../glossary/ubiquitous-language.md) — *Uso de formulario* (Rutina / Equipo / Insumo).

## Fuera de alcance (039)

- Captura en ficha de **equipo** o **insumo** en catálogo usando el formulario asociado al tipo (solo enlace en maestro de tipos).
- Cambiar `usage` después de crear.
- Formularios con uso múltiple o “genérico”.
- Eliminar versiones publicadas individualmente sin eliminar la definición.
- Rediseño completo del diseñador de esquema (`FormDesignerPage`).

## Criterios de aceptación

1. Crear formulario exige uso Rutina, Equipo o Insumo; el listado lo muestra.
2. Eliminar formulario sin referencias funciona; con tipo de rutina, tipo de equipo/insumo o ejecuciones asociadas devuelve error claro.
3. Configuración de campos tiene dos secciones claras; catálogos en lista colapsable; “Nuevo catálogo” a la derecha del título de sección.
4. Tipo de equipo: se puede elegir y guardar un formulario con uso Equipo; validación rechaza formulario de Rutina.
5. Tipo de insumo: mismo patrón con uso Insumo.
6. Tipos de rutina solo ofrecen formularios con uso Rutina.
7. Tests feature: crear con `usage`, delete bloqueado/permitido, validación FK en equipment/supply types, filtro opcional en index.

## Riesgos y dependencias

- **Migración backfill:** revisar demo (`inspeccion-vehiculo-v1` → `equipment`; formularios de rutina → `routine`).
- **Eliminación:** definir consulta eficiente de “en uso” (joins a `routine_types`, tipos de catálogo, ejecuciones).
- Coherencia con 037: la columna en equipo ya existe; este change cierra el circuito producto + insumo.

## Referencias en código actual

- Formularios: `FormDefinitionController`, `FormsListPage.vue`, `FormDesignerPage.vue`.
- Configuración: `FormFieldConfigPage.vue`, `FormOptionCatalogController`, `FormDesignSettingsController`.
- Tipos: `EquipmentTypeController`, `EquipmentTypesPage.vue`, `SupplyTypeController`, `SupplyTypesPage.vue`.
- Rutinas: `RoutineTypesPage.vue`, `RoutineType` + `form_version_id`.

# 037 — Tipos de equipo, tipos de insumo, formulario vehículo y datos demo

## Contexto

Hoy el catálogo tiene **Equipos** (`catalog_items`) e **Insumos** (`supply_items`) planos, sin clasificación por familia. El demo ya incluye un formulario publicado *Revisión mayor vehículo — agencia premium* (`revision-mayor-vehiculo-premium`) y un activo genérico `VEH-SUV-PREM`, pero no modela **tipos de equipo** ni **tipos de insumo**, ni el caso de negocio **Mitsubishi L200**.

**Referencias:** [`references/intencion-producto.md`](references/intencion-producto.md) (aclaración). Ficha L200 → **datos a registrar** ([`ficha_tecnica_l200_2018.md`](references/ficha_tecnica_l200_2018.md)). **Insumos:** catálogo **normalizado** desde la ficha ([`catalogo-insumos-normalizado.md`](references/catalogo-insumos-normalizado.md)). **Formulario tipo Vehículo:** plantilla **normalizada** inspirada en ficha + demo, no copia literal ([`formulario-vehiculo-normalizado.md`](references/formulario-vehiculo-normalizado.md)).

## Objetivo

Para esta versión, dentro de **Catálogos**:

1. Submódulo CRUD **Tipos de equipo** con valores iniciales: **Vehículo**, **Motores**, **Bombas**.
2. Submódulo CRUD **Tipos de insumo** + **catálogo normalizado** de ítems (marca/OEM en `specifications`) desde la ficha.
3. Enlazar equipos a tipo de equipo; tipo **Vehículo** → formulario normalizado de inspección.
4. Enlazar insumos a tipo de insumo.
5. **Formulario vehículo:** crear/publicar esquema normalizado (`inspeccion-vehiculo-v1`); demo premium actual solo como **referencia** de patrones Noah.
6. **Datos demo:** L200 2018 (specs del MD) + activo + insumos de la ficha.

## Alcance funcional

### 1. Tipos de equipo (`equipment_types`)

| Campo | Descripción |
|-------|-------------|
| `code` | Slug único por empresa (`vehiculo`, `motor`, `bomba`) |
| `name` | Etiqueta UI: Vehículo, Motores, Bombas |
| `description` | Opcional |
| `default_form_definition_id` | Para **Vehículo**, el formulario **normalizado** de inspección (ver §5) |
| `sort_order` | Orden en listados |

**API:** `GET/POST/PUT/DELETE /catalog/equipment-types` con `company.module:catalog_items` read/write (mismo permiso que equipos en v1).

**UI:** Catálogos → **Tipos de equipo** (`/app/catalog/equipment-types`), patrón `CatalogItemsPage` (lista, modal crear/editar, eliminar con validación si hay equipos referenciando).

**Semilla demo:** tres registros fijos por empresa demo.

### 2. Equipos de catálogo (extensión)

- Migración: `equipment_type_id` nullable → `equipment_types`, `nullOnDelete` o `restrict` (preferir **restrict** si hay ítems; al eliminar tipo, 422).
- `CatalogItem` CRUD: selector de tipo de equipo obligatorio en UI (default Vehículo en demo).
- Filtro opcional por tipo en listado de equipos.

### 3. Tipos de insumo (`supply_types`) y catálogo inicial

Misma forma que tipos de equipo: `code`, `name`, `description`, `sort_order`.

**API:** `GET/POST/PUT/DELETE /catalog/supply-types` con `catalog_supplies` read/write.

**UI:** Catálogos → **Tipos de insumo** (`/app/catalog/supply-types`).

**Semilla:** [`references/catalogo-insumos-normalizado.md`](references/catalogo-insumos-normalizado.md) — tipos `filtros`, `frenos`, `suspension`, `fluidos`; cuatro ítems de la ficha con `specifications.marca` / `referencia_oem`.

### 4. Insumos (extensión)

- Migración: `supply_type_id` → `supply_types`; `supply_items.specifications` JSON para marca/OEM (si no existe columna, usar JSON existente o ampliar migración).
- `SupplyItem` CRUD: tipo obligatorio; UI opcional para marca y referencia OEM.
- Filtro por tipo en listado.

### 5. Formulario normalizado — tipo **Vehículo**

- Diseño: [`references/formulario-vehiculo-normalizado.md`](references/formulario-vehiculo-normalizado.md).
- Crear `FormDefinition` (slug `inspeccion-vehiculo-v1`), publicar `FormVersion` v1 en seeder.
- Inspiración: sistemas de la ficha L200 + patrones del demo `revision-mayor-vehiculo-premium` (sin bloque premium completo).
- `equipment_types.vehiculo.default_form_definition_id` → este formulario.
- Tipo de rutina demo enlazado a esta versión; actualizar tests (`VehicleDemoFormResponses`).
- El formulario premium antiguo puede permanecer en BD como legado o dejarse de usar en rutina demo (decisión en implementación).

### 6. Registros de prueba (demo)

| Entidad | Criterio |
|---------|----------|
| **Tipo de equipo** | Vehículo, Motores, Bombas |
| **CatalogItem** | Mitsubishi L200 **2018** (`VEH-L200-2018`); `specifications` según [`ficha_tecnica_l200_2018.md`](references/ficha_tecnica_l200_2018.md) |
| **Asset** | `L200-2018-DEMO`, catálogo L200 2018 |
| **Tipos de insumo** | [`catalogo-insumos-normalizado.md`](references/catalogo-insumos-normalizado.md) |
| **SupplyItem** | Cuatro refacciones de la ficha + tipos asignados; sustituir/actualizar `FIL-ACE-PREM` |

Reemplazar o complementar el ítem genérico `VEH-SUV-PREM` según decisión en implementación (recomendado: **migrar activo demo a L200** y mantener SUV como segundo ítem opcional).

## Navegación y permisos

- `AppShell` → grupo **Catálogos**: entradas **Tipos de equipo** y **Tipos de insumo** (iconos acordes).
- `NoahModuleCatalog`: no hace falta módulo nuevo si se reutiliza `catalog_items` / `catalog_supplies`; documentar en `openspec/frontend/navigation.md`.
- `router/index.ts`: rutas nuevas con `moduleId` existente.

## Modelo de dominio

Actualizar [`openspec/domain/catalogs.md`](../../domain/catalogs.md):

- **EquipmentType** — clasificación de plantillas de equipo.
- **SupplyType** — clasificación de insumos.
- Glosario: *Tipo de equipo*, *Tipo de insumo*.

## Fuera de alcance (037)

- Formularios distintos por tipo Motor/Bomba (solo vehículo en esta entrega).
- Versionado automático de formulario al cambiar tipo de equipo.
- Consumo de insumos filtrado por tipo en ejecución de rutina (solo maestros).
- Importación masiva CSV.

## Criterios de aceptación

1. Admin demo puede CRUD tipos de equipo e insumo y ver los tres tipos de equipo sembrados.
2. Crear/editar equipo exige tipo; listado muestra tipo.
3. Crear/editar insumo exige tipo; listado muestra tipo.
4. Tipo **Vehículo** enlazado al **formulario normalizado** de inspección; rutina demo ejecutable con ese esquema.
5. Tras `noah:refresh-demo`, existe **Mitsubishi L200** en catálogo y activo asociado; insumos de prueba del MD cargados.
6. Tests feature API + al menos un test de validación “no borrar tipo en uso”.

## Riesgos y dependencias

- Eliminar tipos con referencias debe devolver mensaje claro en español (422).

## Referencias en código actual

- Formulario vehículo: `database/seeders/NoahDemoSeeder.php` (`revision-mayor-vehiculo-premium`).
- Respuestas test: `tests/Support/VehicleDemoFormResponses.php`.
- UI equipos/insumos: `CatalogItemsPage.vue`, `SuppliesPage.vue`.

# 023 — Configuración de campos de formulario

## Objetivo

Submódulo bajo Diseño → Formularios para reglas transversales de captura en campo y diseño.

## Alcance

1. **Catálogos de opciones** (`FormOptionCatalog`): listas reutilizables para campos `select` / `options`.
2. **Política de imágenes** por empresa: tamaño máximo (KB) y MIME permitidos.
3. API CRUD catálogos + GET/PUT configuración de formularios.
4. El diseñador de formulario consume catálogos y política al definir campos.

## Modelo de datos

- `form_option_catalogs`: `company_id`, `name`, `slug`, `options` JSON `[{value, label}]`.
- `companies`: `form_max_image_size_kb`, `form_allowed_image_mimes` (JSON).

## API

- `GET/POST /design/forms/option-catalogs`
- `PUT/DELETE /design/forms/option-catalogs/{id}`
- `GET/PUT /design/forms/settings` (imagen)

## UI

- Página `/app/design/forms/settings` (enlace desde listado de formularios).
- Material inputs; mismo patrón `portal-page` que catálogos.

## Criterios de aceptación

- Administrador crea catálogo y lo asigna a un campo `select` en el diseñador.
- Campos `photo` respetan límites al subir en rutina.

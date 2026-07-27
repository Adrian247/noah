# 024 — Ejecución de rutina según formulario

## Objetivo

Al capturar datos en rutina, validar y renderizar según el esquema publicado y la configuración de formularios.

## Alcance

1. **Validación servidor** (`FormResponseValidator`): requeridos, tipos, selects contra catálogo, fotos contra política.
2. **Renderer** ampliado: `select`/`options`, `photo` (carga con preview), `required`, Material UI.
3. **Carga de imagen** por campo: `POST /routines/{routine}/form-field-upload` → path en `responses[field_key]`.
4. **Reportes**: `FormFieldCatalog` y `ReportHtmlBuilder` leen valores de `responses` e imágenes.

## Flujo

1. Rutina carga `routine_type.form_version.schema` + settings + catálogos referenciados.
2. Técnico completa `DynamicFormRenderer`; sube fotos antes/durante envío.
3. `POST executions` valida y persiste `responses` JSON.

## Criterios de aceptación

- Campo obligatorio vacío → 422 con mensaje por campo.
- Imagen fuera de política → rechazada en upload.
- Select solo acepta valores del catálogo enlazado.

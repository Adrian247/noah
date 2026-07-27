# 027 — Reportes: descripción, vista previa en vivo, portada y tipografía

## Objetivo

Mejorar el **diseño de plantillas de reporte** y el **listado**: metadatos de plantilla, preview fiel al borrador actual, página de presentación, formato de texto enriquecido, tarjetas con miniatura, y corrección de campos/valores en preview y PDF.

## Contexto actual

- `report_templates` + `report_template_versions` (`components` JSON, `page_settings` JSON).
- Diseñador: `ReportDesignerPage.vue`; preview vía `GET /design/reports/{id}/preview` leyendo **borrador persistido en BD**.
- El `watch` en el cliente llama a preview al editar, pero **no guarda antes** → la vista previa no refleja cambios en memoria (gap UX principal).
- `ReportHtmlBuilder::buildPreview()` usa datos mock fijos; componentes `paragraph` / `image` con claves que no coinciden con el formulario demo (`foto_evidencia` vs `foto_equipo`, etc.) → **no se ven campos ni valores**.
- Markdown casero en `ReportMarkdown` (`**`, `__`, `nl2br`).
- Listado: tabla en `ReportsListPage.vue`.

## Alcance funcional

### 1. Descripción de plantilla (no impresa)

- Campo `description` en `report_templates` (texto, máx. 2000 caracteres, nullable).
- Editable en diseñador y al crear plantilla; **no** se incluye en HTML/PDF.
- API: `PUT /design/reports/{id}` acepta `name` y `description`; `index` y `show` exponen `description`.
- Listado: descripción en **tooltip / overlay al hover** en tarjeta (ver §7).

### 2. Vista previa en vivo

- Nuevo endpoint **`POST /design/reports/{id}/preview`** (o `POST .../preview/draft`) con cuerpo:
  - `components`, `page_settings`, opcional `sample_context` (ids de tipo de rutina / formulario para datos de ejemplo).
- Respuesta: `text/html` (igual que hoy).
- Cliente: debounce **400–600 ms** al cambiar componentes, tipografía, portada o estilos; enviar estado **actual del borrador en memoria** sin exigir «Guardar».
- Mantener `GET .../preview` para miniaturas en listado (último borrador guardado o última publicada).

### 3. Página de presentación / portada

Extender `page_settings` con bloque `cover_page`:

```json
{
  "enabled": true,
  "title": "Informe de mantenimiento",
  "subtitle": "{{company}} · {{asset_tag}}",
  "body": "Texto opcional (markdown)",
  "show_date": true,
  "layout": "centered"
}
```

- Placeholders: `{{company}}`, `{{routine_id}}`, `{{asset_tag}}` (mismos que header/footer).
- En PDF: primera página sin header/footer de documento (o header mínimo); contenido principal inicia en página 2; numeración respeta `page_number.start_at` (portada cuenta o no — **decisión:** portada es página 1; contador visible empieza según `start_at`, por defecto 2 si numeración activa).

### 4. Formato de texto por componente

Nuevos tipos / variantes en `components`:

| Tipo | Uso | Default tamaño (pt en PDF / rem en preview) |
|------|-----|-----------------------------------------------|
| `title` | Título principal | 22 pt |
| `subtitle` | Subtítulo | 16 pt |
| `paragraph` | Campo de formulario o texto | 11 pt (hereda cuerpo) |
| `text` | Texto libre (markdown / rich) | 11 pt |

Propiedades opcionales por componente (además de `text` / `field`):

- `align`: `left` \| `center` \| `right`
- `color`: hex `#RRGGBB` (paleta acotada en UI)
- `size_pt`: override numérico (título/subtítulo/párrafo)

**Configuración global** en `page_settings.typography`:

```json
{
  "title_pt": 22,
  "subtitle_pt": 16,
  "body_pt": 11
}
```

Los componentes sin `size_pt` usan la escala global.

### 5. Texto enriquecido / markdown (UX)

**Frontend (diseñador)**

- Adoptar editor **TipTap** (`@tiptap/vue-3` + extensiones mínimas: bold, italic, underline, heading 2/3, bullet list, link opcional deshabilitado en v1).
- Persistir en componente `text` (y cuerpos de portada) como **Markdown** (salida TipTap → markdown) para una sola fuente de verdad.

**Backend (PDF y preview)**

- Sustituir/ampliar `ReportMarkdown` con **`league/commonmark`** (ya alineado con ecosistema Laravel) + reglas de sanitización HTML (`strip_tags` permitidos).
- Paridad visual aproximada entre iframe preview y DomPDF (fuentes ya configuradas en 025).

**Migración:** textos existentes con `**` / `__` siguen funcionando; conversión automática no requerida.

### 6. Corrección: campos configurados y valores

**Causas identificadas**

1. Preview lee BD, no el estado del editor.
2. Datos de ejemplo en `buildPreview()` no incluyen claves de campos del formulario publicado vinculado al tipo de rutina que usa la plantilla.

**Solución**

- `ReportHtmlBuilder::buildPreview($components, $pageSettings, $sampleResponses = [])`.
- `FormFieldCatalog` o servicio nuevo `ReportSampleDataFactory`: a partir de formulario publicado del tipo de rutina asociado (si existe en diseñador de tipos), generar `sampleResponses` por tipo de campo (texto, número, select, photo path mock o placeholder).
- En diseñador de reporte: cargar campos del **formulario del tipo de rutina** que referencia esta plantilla (si hay varios, el del primer tipo encontrado + aviso en UI).
- Componente `paragraph`: mostrar valor de `sampleResponses[field]`; `image`: miniatura placeholder o imagen demo si path vacío.
- En generación real (`build()`): leer `execution.responses` con mismas reglas que 026 (string / objeto / array de fotos).

### 7. Listado en tarjetas

Reemplazar lista en `ReportsListPage.vue` por **grid de tarjetas**:

- Miniatura: iframe/img desde `GET /design/reports/{id}/preview` escalado (o endpoint `GET .../thumbnail` que devuelve HTML recortado — v1: iframe en tarjeta con `pointer-events: none` y escala CSS).
- Texto: nombre, «En uso: vN publicada», «Borrador: vM».
- Hover: overlay con `description` (2–4 líneas, ellipsis); efecto **enfoque** (scale 1.02, sombra, `ring`, ligero blur del fondo de la grilla — acorde a `design-system.md` glass).
- Clic en tarjeta → diseñador.

### 8. API / datos

- Migración: `report_templates.description` nullable text.
- Validación `updateComponents`: tipos de componente ampliados, `page_settings.cover_page`, `page_settings.typography`.
- Índice listado: incluir `description`, URLs de preview opcional.

## Criterios de aceptación

1. Descripción guardada y visible al hover en tarjetas; ausente en PDF.
2. Al editar título/componente en diseñador, preview se actualiza en <1 s sin guardar (debounced POST).
3. Portada configurable aparece en preview y PDF.
4. Título/subtítulo/párrafo con alineación, color y tamaños (global + override).
5. Editor rich text usable para bloques `text`; PDF renderiza listas y negritas.
6. Componentes «párrafo (campo)» e «imagen (campo)» muestran etiqueta y valor de ejemplo coherente con formulario publicado.
7. PDF de rutina real sigue mostrando valores de ejecución y fotos (incl. galería 026).
8. Tests API: preview POST, description CRUD, sample field rendering smoke.

## Dependencias

- **026** para formato de respuestas foto múltiple en `ReportHtmlBuilder`.
- **GD** en imagen PHP (DomPDF) — ya requerido.

## Fuera de alcance

- Arrastrar componentes en canvas WYSIWYG completo.
- Versionado visual diff entre versiones.

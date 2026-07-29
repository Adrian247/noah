# 026 — Campos imagen: galería y descripciones

## Objetivo

Extender el diseño y la ejecución de formularios para soportar **varias imágenes por campo** y **descripciones opcionales u obligatorias**, configurables en el diseñador sin cambiar el resto de tipos de campo.

## Contexto actual

- Tipo `photo` en esquema JSON de `form_versions.schema`.
- Subida única vía `POST /routines/{routine}/form-field-upload`; en `responses` se guarda un **string** (ruta en disco).
- Política global de imagen por empresa (`form_max_image_size_kb`, `form_allowed_image_mimes`) en `FormDesignSettings`.
- UI: `FormDesignerPage.vue`, `DynamicFormRenderer.vue`, validación en `FormResponseValidator`.

## Alcance funcional

### 1. Configuración en diseñador (campo `photo`)

Nuevas propiedades en cada campo del esquema:

| Propiedad | Tipo | Default | Reglas |
|-----------|------|---------|--------|
| `allow_multiple` | bool | `false` | Si es `true`, el campo acepta varias imágenes. |
| `max_images` | int | `4` | Solo si `allow_multiple`; mínimo **1**, máximo sugerido **10** (límite configurable en `config/phoenix.php` si hace falta). |
| `caption_enabled` | bool | `false` | Muestra campo de descripción por imagen. |
| `caption_required` | bool | `false` | Solo si `caption_enabled`; exige texto no vacío por imagen. |

**UX diseñador**

- Al elegir tipo «Imagen», panel de opciones: checkbox «Permitir varias imágenes» → si activo, número «Máximo de imágenes» (default 4, validar ≥ 1).
- Checkbox «Añadir descripción» → si activo, checkbox «Descripción obligatoria».
- Si `allow_multiple` es falso, ocultar `max_images`; descripción sigue siendo por la única imagen cuando `caption_enabled`.

### 2. Captura en rutina (`DynamicFormRenderer`)

- **Una imagen:** flujo actual + textarea de descripción si `caption_enabled`.
- **Varias imágenes:** lista de ítems `{ path, caption? }` con botón «Añadir imagen» hasta `max_images`; quitar ítem; reordenar opcional (fase 2: drag; v1 con orden de subida).
- Validación cliente: tamaño/MIME antes de subir (como hoy).
- Estado vacío: si `required` y `allow_multiple`, exigir al menos 1 imagen.

### 3. API y almacenamiento

**Formato en `routine_executions.responses[field_key]`**

- Modo simple (retrocompatible): `string` — ruta del archivo.
- Modo con descripción (una imagen): objeto `{ "path": "...", "caption": "..." }` o mantener string si no hay caption (migración suave).
- Modo múltiple: array de objetos `[{ "path", "caption?" }, ...]`.

**Recomendación v1:** normalizar siempre a **array de objetos** cuando `allow_multiple` o `caption_enabled`; conservar lectura de **string** legado en validador, reportes y evidencias.

**Subida**

- Opción A (recomendada): mismo endpoint, una petición por archivo; el cliente ensambla el array en `responses` al enviar ejecución.
- Opción B: endpoint batch `files[]` — solo si el volumen lo justifica.

**Validación (`FormResponseValidator`)**

- Contar ítems vs `max_images` y `required`.
- Validar `caption` si `caption_required`.
- Cada `path` debe existir bajo prefijo de evidencia de la rutina (misma empresa).

### 4. Reportes y PDF (dependencia 027)

- `ReportHtmlBuilder` debe resolver valores `photo` como string, objeto o array y renderizar galería con pies de foto (`caption`).
- Documentar en 027; 026 entrega contrato de datos estable.

## Modelo de datos

- Sin migración obligatoria: todo en JSON del esquema y de `responses`.
- Opcional: documentar en `openspec/domain/` fragmento de esquema de campo `photo`.

## API (diseño)

- Sin endpoints nuevos obligatorios si se mantiene subida unitaria.
- Extender documentación de `PUT /design/forms/{form}/schema` con validación server-side de las nuevas claves en campos `photo`.
- `GET /design/forms/{form}`: sin cambio de forma; el esquema ya incluye campos.

## UI / diseño

- Reutilizar estilos `portal-media-upload` (login/portal) para tarjetas de imagen en captura.
- Accesibilidad: etiquetas por imagen, mensajes de error por campo.

## Criterios de aceptación

1. Administrador configura campo imagen múltiple (default máx. 4) y descripción obligatoria en el diseñador.
2. Técnico sube 1–N imágenes en rutina; no puede superar el máximo; descripciones validadas según configuración.
3. Ejecución con campo requerido sin imágenes → 422 con mensaje claro.
4. Esquemas existentes con `photo` y rutas string siguen funcionando sin migración de datos.
5. Tests: unit (`FormResponseValidator`), feature (upload + ejecución con array y captions).

## Riesgos y decisiones

| Tema | Decisión |
|------|----------|
| Tamaño total vs por archivo | v1: límite **por archivo** (política empresa); documentar posible límite agregado en backlog. |
| Orden de imágenes | Orden del array en JSON. |
| Eliminación de archivo al quitar ítem en UI | v1: no borrar en storage hasta enviar ejecución o job de limpieza (documentar). |

## Fuera de alcance

- Recorte/edición de imagen in-browser.
- OCR o IA sobre descripciones.

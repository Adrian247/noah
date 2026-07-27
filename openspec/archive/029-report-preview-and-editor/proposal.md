# 029 — Reportes: vista previa, editor y maquetación

## Objetivo

Corregir la experiencia de **vista previa** (listado, diseñador, portada) y evolucionar el **editor de contenido** hacia un stack profesional (WYSIWYG / Markdown con barra completa). Incluye **divisores** y ajustes de cabecera/pie. Complemento transversal: **Material inputs** (ver 030).

> Nota: el encabezado del pedido menciona «formularios»; los ítems listados aplican al módulo **Diseño → Reportes** y al shell UI. Formularios no cambia de alcance salvo datos mostrados en párrafos del reporte.

## Problemas actuales (diagnóstico)

| Síntoma | Causa probable |
|---------|----------------|
| Miniatura del listado con scroll | El iframe renderiza el documento completo; el navegador habilita scroll interno. |
| Campos vacíos / «[Sin valor]» en plantilla demo | Muestra de datos no enlazada al formulario publicado del tipo de rutina, o borrador sin `POST preview`. |
| Markdown limitado | `MarkdownEditor` casero (4 botones); no cumple barra rica (alineación, código, tablas, etc.). |
| Portada «fusionada» con cuerpo | Una sola columna HTML sin hojas A4 simuladas en modo preview. |
| Cabecera/pie «pegados al panel» | `position: fixed` en HTML de preview dentro de iframe pequeño; en PDF es correcto, en preview no. |

## Alcance

### 1. Miniaturas en listado (P0 — hecho en código)

- Query `GET .../preview?thumbnail=1`: HTML con `overflow: hidden` y altura fija; sin banner naranja.
- Tarjeta: `overflow: hidden`, iframe `scrolling="no"`, escala CSS.

### 2. Modo preview por páginas (P0)

- Clase `body.report-preview`: fondo gris, hojas `.report-page` (min-height A4, sombra, separación vertical).
- Portada = **hoja 1**; contenido = **hoja 2+** con `page-break-after` para PDF.
- Cabecera/pie en preview: **estáticos dentro de cada hoja**, no `position: fixed`.

### 3. Portada — omitir cabecera y pie (P1)

- `page_settings.cover_page.omit_header_footer` (bool, default `true` en portada).
- En hoja de portada no renderizar header/footer globales; en hoja de contenido sí (según toggles existentes).

### 4. Componente divisor (P1)

- Tipo `divider` en `components`: línea horizontal configurable (`style`: `solid` \| `dashed`, `margin_pt`).
- UI: botón «+ Divisor» en diseñador; preview y PDF.

### 5. Editor rich / Markdown (P2)

**Recomendación:** [@tiptap/vue-3](https://tiptap.dev/) + extensiones:

- StarterKit (negrita, cursiva, listas, encabezados)
- TextAlign (izquierda, centro, derecha)
- Code / CodeBlock (bloques de código; resaltar con **lowlight** o highlight.js)
- Table (tablas; útil para datos tipo «mysql» / grillas)
- Persistencia: HTML sanitizado o Markdown vía `@tiptap/extension-markdown` / export manual

Alternativa más ligera: [Toast UI Editor](https://github.com/nhn/tuieditor) (modo WYSIWYG + Markdown).

Criterio: misma salida en `ReportMarkdown` / HTML inline para DomPDF.

### 6. Párrafos de formulario en preview (P1)

- `ReportSampleDataFactory`: siempre rellenar claves referenciadas en `components`.
- Opcional: mostrar `«Etiqueta del campo»: valor` usando esquema publicado (no asteriscos de «required» del diseñador de formularios).

### 7. PDF vs preview

| Aspecto | Preview (iframe) | PDF |
|---------|------------------|-----|
| Header/footer | Bloque en hoja | `position: fixed` + script DomPDF |
| Portada | Hoja independiente scroll | `page-break-after: always` |
| Miniatura | `thumbnail=1` | N/A |

## API

- `GET /design/reports/{id}/preview?thumbnail=1`
- Sin cambio de contrato en `POST preview` (borrador en vivo).

## Criterios de aceptación

1. Tarjetas del listado sin barras de scroll visibles.
2. Con portada activa, el diseñador muestra dos «hojas» al hacer scroll en la preview.
3. Portada puede omitir cabecera/pie; contenido las respeta según configuración.
4. Plantilla demo muestra valores de ejemplo en todos los párrafos configurados.
5. Divisor visible en preview y PDF.
6. (Fase 2) Editor con alineación, código y tablas integrado en bloques `text`.

## Dependencias

- 027 (diseñador avanzado) — base actual.
- 030 — corrección Material inputs en todo el sistema.

## Fuera de alcance

- Syntax highlighting SQL específico «MySQL» como lenguaje de negocio (sí code blocks genéricos).
- Paginación automática del cuerpo en preview (solo portada + cuerpo como hojas lógicas v1).

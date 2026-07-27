# 025 — Diseñador de reportes avanzado

## Objetivo

Reportes personalizables con vista previa, tipografía, encabezado/pie, numeración e imágenes del formulario.

## Alcance

1. **Nombre editable** de plantilla (`PUT /design/reports/{id}`).
2. **Vista previa HTML** (`GET /design/reports/{id}/preview`) con datos de ejemplo.
3. **`page_settings`**: fuente (Roboto, Source Sans 3), header/footer HTML, contador de páginas con `start_at`.
4. **Componentes**: título, párrafo (campo o texto con markdown ligero), imagen (campo `photo` del formulario).
5. **Estilos inline** en componentes: negrita, subrayado vía markdown (`**`, `__`).
6. UI ancho completo; panel lateral de vista previa.

## PDF

- `ReportHtmlBuilder` genera HTML con fuentes Google (fallback DejaVu), header/footer fijos, script DomPDF para número de página.

## Criterios de aceptación

- Usuario cambia nombre sin recrear plantilla.
- Preview refleja borrador actual en <2s (datos mock).
- PDF emitido incluye imágenes de rutina cuando el componente referencia campo foto.

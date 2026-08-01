# Dynamic Report Engine — Phoenix

Los reportes no son documentos fijos.

Los reportes son árboles de componentes (JSON) → HTML → PDF.

## Motor actual (fase de pruebas)

- **PDF:** DomPDF (`barryvdh/laravel-dompdf`) + `page_script` para encabezado/pie/numeración.
- **Objetivo ADR-005:** Chromium / Browsershot; aún no adoptado. Hasta migrar, la estabilidad se apoya en el contrato de portada y probes.

## Contrato de portada (`schema_version` ≥ 1)

Congelado para reducir roturas:

1. Si `cover_page.enabled`: la portada es **siempre** la hoja 1.
2. En esa hoja **no** se dibuja encabezado ni pie DomPDF (`omit_header_footer` forzado a `true` al normalizar).
3. El cuerpo vive en `.report-pdf-main` desde la hoja 2.
4. `page_number.start_at` por defecto **2** con portada.
5. `ReportCoverRenderer` concentra el HTML/CSS de portada; `ReportPageSettingsNormalizer` normaliza al guardar/aplicar preset/renderizar.
6. En el diseñador, con portada activa la vista previa usa **el mismo HTML→PDF** que producción (no el preview HTML paralelo).

## Pipeline

```
JSON (components + page_settings)
  → ReportPageSettingsNormalizer
  → ReportHtmlBuilder (+ ReportCoverRenderer)
  → DomPDF
```

No utilizar plantillas Word como motor principal.

Diseño del editor: [design/report-designer.md](../design/report-designer.md).

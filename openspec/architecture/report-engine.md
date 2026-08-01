# Dynamic Report Engine — Phoenix

Los reportes no son documentos fijos.

Los reportes son árboles de componentes (JSON) → HTML → PDF.

## Motor PDF (ADR-005)

- **Preferido:** Chromium vía Spatie Browsershot (`PHOENIX_REPORTS_PDF_DRIVER=auto|browsershot`).
- **Fallback:** DomPDF si Chromium no está disponible o Browsershot falla (`pdf_fallback_dompdf`).
- Con portada activa, Browsershot genera **dos PDFs** (portada sin chrome + cuerpo con H/F) y los fusiona (`iio/libmergepdf`) para respetar el contrato de portada.

## Contrato de portada (`schema_version` ≥ 1)

1. Si `cover_page.enabled`: la portada es **siempre** la hoja 1.
2. En esa hoja **no** se dibuja encabezado ni pie.
3. El cuerpo empieza en la hoja 2.
4. `page_number.start_at` por defecto **2** con portada.
5. `ReportCoverRenderer` + `ReportPageSettingsNormalizer` + `ReportPdfRenderer`.

## Pipeline

```
JSON (components + page_settings)
  → ReportPageSettingsNormalizer
  → ReportHtmlBuilder (+ ReportCoverRenderer) → ReportPdfDocument
  → ReportPdfRenderer (Browsershot | DomPDF)
```

No utilizar plantillas Word como motor principal.

Diseño del editor: [design/report-designer.md](../design/report-designer.md).

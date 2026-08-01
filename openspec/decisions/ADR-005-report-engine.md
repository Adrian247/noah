# ADR-005 — Motor de reportes por componentes

## Estado

Aceptada.

## Contexto

Reportes altamente personalizables (marca, tipografía, pie, numeración).

## Decisión

Plantillas como árbol JSON de componentes; render HTML → PDF vía Chromium (Browsershot). No Word/DomPDF como eje.

## Consecuencias

- Inversión en diseñador visual web.
- Calidad PDF superior y alineada a CSS.
- Implementación: `ReportPdfRenderer` (Browsershot) con fallback DomPDF; Chromium en imagen Docker (`PHOENIX_REPORTS_*`).

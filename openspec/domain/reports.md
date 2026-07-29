# Dominio — Reports (Phoenix)

Vista de dominio del **Dynamic Report Engine**. Motor: [report-engine.md](../architecture/report-engine.md). UX: [report-designer.md](../design/report-designer.md).

## Agregados

### ReportTemplate

- Identidad por empresa; enlazada desde RoutineType.

### ReportTemplateVersion

- Árbol de componentes JSON.
- Datos de página: tamaño (A4), márgenes, fuente por defecto.

### GeneratedReport

- Instancia: `routine_id` o `execution_id`, `template_version_id`, `storage_file_id` (PDF), `generated_at`, estado (pending, ready, failed).

## Generación

1. Workflow o usuario solicita generación.
2. Motor resuelve bindings campo → componente.
3. Job async produce PDF y dispara `ReportGenerated`.

## Invariantes

- Plantilla publicada inmutable; rutinas nuevas usan versión vigente al crearse.
- Regenerar PDF no altera datos de ejecución; nueva instancia GeneratedReport o versión de documento (política: una versión “oficial” post-validación).

## Eventos

- `ReportTemplatePublished`, `ReportGenerationRequested`, `ReportGenerated`, `ReportGenerationFailed`

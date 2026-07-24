# Versionado — Noah

## API

- Prefijo de ruta `/api/v1`.
- Cambios breaking → `/api/v2`; v1 soportada al menos 6 meses tras anuncio (SaaS).

## Metadatos configurables

| Artefacto | Versionado |
|-----------|------------|
| Formulario | FormVersion publicada; rutinas enlazan versión al crear |
| Plantilla reporte | ReportTemplateVersion |
| Workflow | WorkflowDefinition version |
| Prompt IA | PromptTemplate version |

Regla: **ejecuciones en curso** no cambian de versión de formulario/plantilla hasta nueva rutina o política explícita de migración (futuro).

## Móvil

- App envía `schema_version` y `app_version` en sync.
- Servidor rechaza sync si app obsoleta (configurable).

## Base de datos

- Migraciones Laravel secuenciales; sin edición de migraciones ya desplegadas.

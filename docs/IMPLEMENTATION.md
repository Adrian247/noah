# Implementación — Noah (estado)

## Backend

| Área | Estado |
|------|--------|
| Identity, companies, memberships, Sanctum | Implementado |
| Catálogo, insumos, activos (CRUD API) | Implementado |
| Forms / report versions + API diseño | Implementado MVP |
| AI Gateway + PromptTemplate + AiInvocation | Implementado (local + OpenAI opcional) |
| Workflow runtime v1 + diseño visual (layout) | Implementado MVP |
| Evento `RoutineValidated` → PDF (cola) + borrador factura | Implementado |
| Reportes PDF (DomPDF, job `GenerateRoutineReportJob`) | Implementado v1 async |
| Ejecución con `responses` y `consumptions` | Implementado |
| Facturación borrador / emitir (sin PAC) | Implementado v1 |
| Auditoría append-only + API | Implementado v1 |
| Email pendiente validación (supervisores) | Implementado v1 (Mailpit) |

## Frontend

| Pantalla | Estado |
|----------|--------|
| Login, dashboard, shell + company switch | Implementado |
| Lista y detalle rutina (formulario, consumos, workflow, validar/rechazar, PDF) | Implementado |
| Catálogo equipos, insumos, activos (alta, edición, borrado) | Implementado |
| Diseño: formularios, reportes, workflows (MVP) | Implementado |
| Tipos de rutina (workflow enlazable) | Implementado |
| Facturación (listar, emitir borrador) | Implementado |
| Auditoría (listado) | Implementado |

## Variables de entorno IA

```env
NOAH_AI_PROVIDER=local
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
```

## Usuarios demo (seed)

| Email | Rol |
|-------|-----|
| admin@noah.local | Administrador |
| supervisor@noah.local | Supervisor |
| tecnico@noah.local | Técnico |
| facturacion@noah.local | Facturación |

Contraseña: `password`

## Cambios archivados

Ver [openspec/archive/README.md](../openspec/archive/README.md).

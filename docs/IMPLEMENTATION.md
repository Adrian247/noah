# Implementación — Noah (estado)

## Backend

| Área | Estado |
|------|--------|
| Identity, companies, memberships, Sanctum | Implementado |
| Catálogo, insumos, activos, sitios | API implementada |
| Forms / report versions (JSON) | Modelo + seed; sin diseñador UI |
| AI Gateway + PromptTemplate + AiInvocation | Implementado (local + OpenAI opcional) |
| Evento `RoutineValidated` → PDF + borrador factura | Implementado |
| Reportes PDF (DomPDF, HTML por componentes) | Implementado v1 |
| Ejecución con `responses` y `consumptions` | Implementado |
| Facturación borrador / emitir (sin PAC) | Implementado v1 |
| Workflow designer | Pendiente |
| Auditoría de entidades críticas | Pendiente |

## Frontend

| Pantalla | Estado |
|----------|--------|
| Login, dashboard, shell + company switch | Implementado |
| Lista y detalle rutina (formulario dinámico, consumos, validar, PDF) | Implementado |
| Catálogo equipos, insumos, activos | Implementado |
| Tipos de rutina (lectura) | Implementado |
| Facturación (listar, emitir borrador) | Implementado |
| Diseñadores form/reporte | Pendiente |

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

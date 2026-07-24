# Implementación — Noah (estado)

## Backend

| Área | Estado |
|------|--------|
| AI Gateway + PromptTemplate + AiInvocation | Implementado (local + OpenAI opcional) |
| Evento `RoutineValidated` → PDF + borrador factura | Implementado |
| Reportes PDF (DomPDF, HTML por componentes) | Implementado v1 |
| Facturación borrador / emitir (sin PAC) | Implementado v1 |
| Workflow designer | Pendiente |

## Frontend

| Pantalla | Estado |
|----------|--------|
| Detalle rutina (ejecutar, validar, PDF) | Implementado |
| Facturación (listar, emitir borrador) | Implementado |

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

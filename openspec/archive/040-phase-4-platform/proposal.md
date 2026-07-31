# 040 — Fase 4 plataforma avanzada

## Objetivo

Entregar capacidades de plataforma avanzada (MVP): webhooks salientes, reglas de automatización, dashboard configurable por usuario, insights/IA operativa y cuotas IA por empresa.

## Alcance

- Tablas: `webhook_subscriptions`, `automation_rules`, `dashboard_preferences`, cuotas IA en `companies`
- Eventos operativos: `routine.validated`, `routine.rejected`, `invoice.issued`, `inventory.low_stock`
- API REST + UI Vue (Integraciones, Insights, widgets dashboard)
- UI evidencias en detalle de rutina
- Tests `Phase4PlatformCapabilitiesTest`

## Fuera de alcance (roadmap posterior)

- Rule engine visual completo
- RAG / chatbot avanzado
- Dashboard drag-and-drop
- PAC fiscal producción

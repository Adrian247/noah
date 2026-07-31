# 042 — Roadmap pendientes (PAC, RAG, automatización visual, webhooks)

## Objetivo

Cerrar ítems presupuestados fuera del despliegue: timbrado fiscal PAC, asistente RAG, rule engine visual, probar webhook, duración en PDF.

## Alcance

- FiscalAdapter (sandbox + PAC México HTTP) integrado en emisión
- Configuración fiscal en Facturación → Configuración
- `POST /integrations/webhooks/{id}/test`
- UI builder de reglas de automatización
- Insights assistant con contexto operativo + OpenAI vía AI Gateway
- `duration_minutes` en PDF como hh:mm
- Roadmap Fase 3 móvil marcada completada (v0.7)

## Fuera de alcance

- Despliegue producción / push remoto
- Play Store AAB
- Reordenar fotos en galería móvil (iteración menor)

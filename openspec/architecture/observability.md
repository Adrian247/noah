# Observabilidad — Phoenix

## Logging

- Structured JSON logs (request_id, company_id, user_id).
- Niveles: error en fiscal/sync/PDF; info en transiciones workflow.

## Métricas (mínimo producción)

- Latencia p95 por ruta API.
- Jobs: PDF, IA, email — éxito/fallo/duración.
- Cola: depth, age.

## Trazas

- OpenTelemetry compatible (fase código); correlación request → job.

## Herramientas (sugeridas)

| Área | Opción |
|------|--------|
| APM | Laravel Nightwatch / Pulse |
| Logs | Stack centralizado (Loki, CloudWatch, etc.) |
| Uptime | Health `/up` + checks externos |

## Alertas

- Cola atascada > N minutos.
- Tasa error PDF > umbral.
- Sync failures por dispositivo (dashboard admin futuro).

## Privacidad

- No loguear cuerpo completo de prompts con PII; usar hash o truncado.

Ver NFR: [non-functional-requirements.md](../vision/non-functional-requirements.md).
